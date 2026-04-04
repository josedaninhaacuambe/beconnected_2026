using System.Net.Http.Json;
using System.Text.Json;

namespace Beconnect.PaymentService.Services;

/// <summary>
/// Cliente para a API eMola (Mozambique).
///
/// Documentação eMola: https://developer.emola.co.mz
/// Implementação baseada no fluxo USSD push:
///   1. POST /v1/pushTransaction   → inicia cobrança no telemóvel do cliente
///   2. GET  /v1/transaction/{ref} → polling do estado da transação
///
/// Variáveis de ambiente necessárias:
///   EMOLA_API_URL, EMOLA_API_KEY, EMOLA_MERCHANT_NUMBER
/// </summary>
public class EMolaService
{
    private readonly HttpClient _http;
    private readonly ILogger<EMolaService> _logger;
    private readonly string _merchantNumber;

    public EMolaService(HttpClient http, ILogger<EMolaService> logger, IConfiguration config)
    {
        _http           = http;
        _logger         = logger;
        _merchantNumber = config["EMola:MerchantNumber"] ?? throw new InvalidOperationException("EMola:MerchantNumber not configured");
    }

    /// <summary>
    /// Inicia uma cobrança USSD push. O cliente recebe notificação no telemóvel.
    /// </summary>
    public async Task<EMolaPushResult> InitiatePushAsync(decimal amount, string customerPhone, string reference)
    {
        _logger.LogInformation("eMola push: {Amount} MT → {Phone} [ref={Ref}]", amount, customerPhone, reference);

        var payload = new
        {
            amount          = (int)Math.Round(amount),     // eMola aceita inteiros (centavos implícitos)
            customerMsisdn  = customerPhone,
            merchantMsisdn  = _merchantNumber,
            reference,
            serviceProviderCode = _merchantNumber,
        };

        var response = await _http.PostAsJsonAsync("/v1/pushTransaction", payload);
        var body     = await response.Content.ReadAsStringAsync();

        if (!response.IsSuccessStatusCode)
        {
            _logger.LogError("eMola push failed [{Status}]: {Body}", response.StatusCode, body);
            return new EMolaPushResult { Success = false, ErrorMessage = $"eMola error {response.StatusCode}: {body}" };
        }

        using var doc = JsonDocument.Parse(body);
        var root = doc.RootElement;

        return new EMolaPushResult
        {
            Success      = true,
            ExternalRef  = root.TryGetProperty("transactionReference", out var r) ? r.GetString() : reference,
            Status       = root.TryGetProperty("responseCode", out var s) ? s.GetString() : "pending",
        };
    }

    /// <summary>
    /// Verifica o estado de uma transação previamente iniciada.
    /// </summary>
    public async Task<EMolaStatusResult> CheckStatusAsync(string externalRef)
    {
        var response = await _http.GetAsync($"/v1/transaction/{Uri.EscapeDataString(externalRef)}");
        var body     = await response.Content.ReadAsStringAsync();

        if (!response.IsSuccessStatusCode)
        {
            return new EMolaStatusResult { Status = "unknown", Paid = false };
        }

        using var doc  = JsonDocument.Parse(body);
        var root       = doc.RootElement;
        var statusCode = root.TryGetProperty("responseCode", out var c) ? c.GetString() : null;

        // eMola: "INS-0" = sucesso, qualquer outro = pendente/falha
        return new EMolaStatusResult
        {
            Status = statusCode == "INS-0" ? "paid" : "pending",
            Paid   = statusCode == "INS-0",
            Raw    = body,
        };
    }
}

public record EMolaPushResult
{
    public bool    Success      { get; init; }
    public string? ExternalRef  { get; init; }
    public string? Status       { get; init; }
    public string? ErrorMessage { get; init; }
}

public record EMolaStatusResult
{
    public string  Status { get; init; } = "pending";
    public bool    Paid   { get; init; }
    public string? Raw    { get; init; }
}
