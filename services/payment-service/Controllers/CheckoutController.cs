using Beconnect.PaymentService.Services;
using Microsoft.AspNetCore.Mvc;

namespace Beconnect.PaymentService.Controllers;

[ApiController]
[Route("api/checkout")]
public class CheckoutController : ControllerBase
{
    private readonly CheckoutService _checkout;
    private readonly ILogger<CheckoutController> _logger;

    public CheckoutController(CheckoutService checkout, ILogger<CheckoutController> logger)
    {
        _checkout = checkout;
        _logger   = logger;
    }

    /// <summary>
    /// POST /api/checkout/initiate
    /// Chamado pelo Cart Service. Inicia o processo de pagamento.
    /// </summary>
    [HttpPost("initiate")]
    public async Task<IActionResult> Initiate([FromBody] CheckoutInitiateRequest req)
    {
        // Validação interna de chave de serviço
        if (!Request.Headers.TryGetValue("X-Internal-Key", out var key) ||
            key != (Environment.GetEnvironmentVariable("INTERNAL_API_KEY") ?? "beconnect_internal"))
        {
            return Unauthorized(new { message = "Acesso não autorizado." });
        }

        try
        {
            var result = await _checkout.InitiateAsync(req);

            if (result.AlreadyProcessed)
                return Ok(new { checkoutId = result.CheckoutId, status = result.Status, alreadyProcessed = true });

            return result.Status == "failed"
                ? BadRequest(new { checkoutId = result.CheckoutId, status = result.Status, message = result.ErrorMessage })
                : Ok(new { checkoutId = result.CheckoutId, status = result.Status, externalRef = result.ExternalRef });
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Checkout initiate failed");
            return StatusCode(500, new { message = "Erro interno no serviço de pagamentos." });
        }
    }

    /// <summary>
    /// POST /api/checkout/webhook/emola
    /// Webhook eMola — confirma ou rejeita pagamento assíncrono.
    /// </summary>
    [HttpPost("webhook/emola")]
    public async Task<IActionResult> EMolaWebhook([FromBody] EMolaWebhookPayload payload)
    {
        _logger.LogInformation("eMola webhook: ref={Ref} code={Code}", payload.TransactionReference, payload.ResponseCode);

        // TODO: verificar assinatura HMAC do eMola quando disponível
        // HMAC-SHA256(secret, body) comparado com header X-Emola-Signature

        // Encontrar checkout pela referência externa
        // Delegar ao CheckoutService para actualizar estado e criar ordem
        // (implementação completa pendente de credenciais eMola sandbox)

        return Ok(new { received = true });
    }

    /// <summary>GET /api/checkout/{id}/status</summary>
    [HttpGet("{id:long}/status")]
    public async Task<IActionResult> Status(long id)
    {
        // Retorna estado actual do checkout para polling do frontend
        return Ok(new { id, status = "pending_confirmation" }); // placeholder
    }

    [HttpGet("health")]
    public IActionResult Health() => Ok(new { status = "ok", service = "payment-service" });
}

public record EMolaWebhookPayload
{
    public string? TransactionReference { get; init; }
    public string? ResponseCode         { get; init; }
    public string? CustomerMsisdn       { get; init; }
    public decimal? Amount              { get; init; }
}
