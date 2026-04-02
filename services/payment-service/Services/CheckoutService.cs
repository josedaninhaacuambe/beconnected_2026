using System.Text.Json;
using Beconnect.PaymentService.Data;
using Beconnect.PaymentService.Models;
using Microsoft.EntityFrameworkCore;
using StackExchange.Redis;

namespace Beconnect.PaymentService.Services;

public class CheckoutService
{
    private readonly PaymentDbContext  _db;
    private readonly EMolaService      _emola;
    private readonly IDatabase         _redis;
    private readonly ILogger<CheckoutService> _logger;
    private readonly HttpClient        _stockClient;
    private readonly HttpClient        _laravelClient;

    private const string IdempotencyPrefix = "checkout:idem:";
    private const int    LockTtlSeconds    = 30;

    public CheckoutService(
        PaymentDbContext db,
        EMolaService emola,
        IConnectionMultiplexer redis,
        ILogger<CheckoutService> logger,
        IHttpClientFactory httpFactory)
    {
        _db            = db;
        _emola         = emola;
        _redis         = redis.GetDatabase();
        _logger        = logger;
        _stockClient   = httpFactory.CreateClient("StockService");
        _laravelClient = httpFactory.CreateClient("LaravelApp");
    }

    /// <summary>
    /// Ponto de entrada principal. Garante idempotência e executa o checkout completo.
    /// </summary>
    public async Task<CheckoutResult> InitiateAsync(CheckoutInitiateRequest req)
    {
        // ─── 1. Idempotência via Redis (SET NX EX) ────────────────────────────
        var idemKey = $"{IdempotencyPrefix}{req.IdempotencyKey}";
        var lockVal = await _redis.StringSetAsync(idemKey, "processing", TimeSpan.FromSeconds(LockTtlSeconds), When.NotExists);

        if (!lockVal)
        {
            // Já existe — devolve resultado anterior se existir em DB
            var existing = await _db.CheckoutRequests
                .FirstOrDefaultAsync(c => c.IdempotencyKey == req.IdempotencyKey);

            if (existing != null)
                return new CheckoutResult { CheckoutId = existing.Id, Status = existing.Status, AlreadyProcessed = true };

            // Ainda em processamento — cliente deve aguardar
            return new CheckoutResult { Status = "processing", AlreadyProcessed = true };
        }

        // ─── 2. Persistir CheckoutRequest em DB ──────────────────────────────
        var checkoutReq = new CheckoutRequest
        {
            IdempotencyKey = req.IdempotencyKey,
            UserId         = req.UserId,
            Status         = "processing",
            PaymentMethod  = req.PaymentMethod,
            Amount         = req.Cart.Subtotal,
            Phone          = req.Phone,
            CartSnapshot   = JsonSerializer.Serialize(req.Cart),
        };

        _db.CheckoutRequests.Add(checkoutReq);
        await _db.SaveChangesAsync();

        try
        {
            // ─── 3. Reservar stock no Stock Service (Java) ────────────────────
            var stockReserved = await ReserveStockAsync(req.Cart, checkoutReq.Id);
            if (!stockReserved.Success)
            {
                await FailCheckout(checkoutReq, stockReserved.ErrorMessage ?? "Stock insuficiente.");
                return new CheckoutResult { CheckoutId = checkoutReq.Id, Status = "failed", ErrorMessage = stockReserved.ErrorMessage };
            }

            // ─── 4. Processar pagamento ───────────────────────────────────────
            CheckoutResult paymentResult;
            if (req.PaymentMethod == "emola")
            {
                paymentResult = await ProcessEMola(checkoutReq, req);
            }
            else
            {
                // Outros métodos (MPesa, cash, card) — extensível
                paymentResult = await ProcessGenericPayment(checkoutReq, req);
            }

            if (paymentResult.Status != "paid" && paymentResult.Status != "pending_confirmation")
            {
                // Pagamento falhou — liberta stock reservado
                await ReleaseStockAsync(req.Cart, checkoutReq.Id);
                await FailCheckout(checkoutReq, paymentResult.ErrorMessage ?? "Pagamento recusado.");
                return paymentResult;
            }

            // ─── 5. Criar Ordem no Laravel (via HTTP interno) ─────────────────
            if (paymentResult.Status == "paid")
            {
                var orderId = await CreateOrderInLaravel(checkoutReq, req);
                checkoutReq.OrderId = orderId;
                checkoutReq.Status  = "paid";
                checkoutReq.UpdatedAt = DateTime.UtcNow;
                await _db.SaveChangesAsync();

                await _redis.KeyExpireAsync(idemKey, TimeSpan.FromHours(24));
            }

            return new CheckoutResult
            {
                CheckoutId  = checkoutReq.Id,
                Status      = checkoutReq.Status,
                ExternalRef = checkoutReq.ExternalRef,
                OrderId     = checkoutReq.OrderId,
            };
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Checkout {Id} failed with exception", checkoutReq.Id);
            await FailCheckout(checkoutReq, ex.Message);
            throw;
        }
    }

    // ─── Reserve stock via Stock Service ──────────────────────────────────────
    private async Task<StockReservationResult> ReserveStockAsync(CartSnapshot cart, long checkoutId)
    {
        var items = cart.Items.Select(i => new { productId = i.ProductId, quantity = i.Quantity }).ToList();
        var response = await _stockClient.PostAsJsonAsync("/api/stock/reserve", new
        {
            checkoutId,
            items,
        });

        if (response.IsSuccessStatusCode) return new StockReservationResult { Success = true };

        var err = await response.Content.ReadAsStringAsync();
        return new StockReservationResult { Success = false, ErrorMessage = err };
    }

    private async Task ReleaseStockAsync(CartSnapshot cart, long checkoutId)
    {
        try
        {
            await _stockClient.PostAsJsonAsync("/api/stock/release", new { checkoutId });
        }
        catch (Exception ex)
        {
            _logger.LogWarning(ex, "Failed to release stock for checkout {Id}", checkoutId);
        }
    }

    // ─── eMola USSD push ───────────────────────────────────────────────────────
    private async Task<CheckoutResult> ProcessEMola(CheckoutRequest req, CheckoutInitiateRequest initiateReq)
    {
        if (string.IsNullOrWhiteSpace(initiateReq.Phone))
            return new CheckoutResult { Status = "failed", ErrorMessage = "Número de telemóvel obrigatório para eMola." };

        var pushResult = await _emola.InitiatePushAsync(req.Amount, initiateReq.Phone, req.IdempotencyKey);

        if (!pushResult.Success)
            return new CheckoutResult { Status = "failed", ErrorMessage = pushResult.ErrorMessage };

        req.ExternalRef = pushResult.ExternalRef;
        req.Status      = "pending_confirmation";
        req.UpdatedAt   = DateTime.UtcNow;
        await _db.SaveChangesAsync();

        // O webhook eMola confirmará o pagamento assincronamente
        return new CheckoutResult
        {
            CheckoutId  = req.Id,
            Status      = "pending_confirmation",
            ExternalRef = pushResult.ExternalRef,
        };
    }

    private async Task<CheckoutResult> ProcessGenericPayment(CheckoutRequest req, CheckoutInitiateRequest initiateReq)
    {
        // Placeholder para métodos adicionais
        req.Status    = "pending_confirmation";
        req.UpdatedAt = DateTime.UtcNow;
        await _db.SaveChangesAsync();
        return new CheckoutResult { CheckoutId = req.Id, Status = "pending_confirmation" };
    }

    // ─── Cria Ordem no Laravel App ─────────────────────────────────────────────
    private async Task<long?> CreateOrderInLaravel(CheckoutRequest req, CheckoutInitiateRequest initiateReq)
    {
        try
        {
            var response = await _laravelClient.PostAsJsonAsync("/api/internal/orders/create", new
            {
                checkoutId    = req.Id,
                userId        = req.UserId,
                cartSnapshot  = JsonSerializer.Deserialize<object>(req.CartSnapshot),
                paymentRef    = req.ExternalRef,
                paymentMethod = req.PaymentMethod,
                amount        = req.Amount,
            });

            if (response.IsSuccessStatusCode)
            {
                var body = await response.Content.ReadFromJsonAsync<JsonElement>();
                if (body.TryGetProperty("orderId", out var id)) return id.GetInt64();
            }
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Failed to create order in Laravel for checkout {Id}", req.Id);
        }
        return null;
    }

    private async Task FailCheckout(CheckoutRequest req, string message)
    {
        req.Status       = "failed";
        req.ErrorMessage = message;
        req.UpdatedAt    = DateTime.UtcNow;
        await _db.SaveChangesAsync();
    }
}

// ─── DTOs ─────────────────────────────────────────────────────────────────────
public record CheckoutInitiateRequest
{
    public required string      IdempotencyKey { get; init; }
    public          long?       UserId         { get; init; }
    public required string      PaymentMethod  { get; init; }
    public          string?     Phone          { get; init; }
    public required CartSnapshot Cart          { get; init; }
}

public record CartSnapshot
{
    public string   CartId     { get; init; } = string.Empty;
    public decimal  Subtotal   { get; init; }
    public int      TotalItems { get; init; }
    public List<CartItemSnapshot> Items { get; init; } = [];
}

public record CartItemSnapshot
{
    public string  ProductId { get; init; } = string.Empty;
    public int     Quantity  { get; init; }
    public decimal UnitPrice { get; init; }
    public string? StoreId   { get; init; }
    public decimal Subtotal  { get; init; }
}

public record CheckoutResult
{
    public long?   CheckoutId       { get; init; }
    public string  Status           { get; init; } = string.Empty;
    public string? ExternalRef      { get; init; }
    public long?   OrderId          { get; init; }
    public string? ErrorMessage     { get; init; }
    public bool    AlreadyProcessed { get; init; }
}

public record StockReservationResult
{
    public bool    Success      { get; init; }
    public string? ErrorMessage { get; init; }
}
