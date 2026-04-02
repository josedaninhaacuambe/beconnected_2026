using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace Beconnect.PaymentService.Models;

/// <summary>
/// Registo imutável de cada tentativa de checkout.
/// Idempotency key garante que cliques duplos não geram ordens duplicadas.
/// </summary>
[Table("payment_checkout_requests")]
public class CheckoutRequest
{
    [Key]
    public long Id { get; set; }

    [Required, MaxLength(64)]
    public string IdempotencyKey { get; set; } = string.Empty;

    public long? UserId { get; set; }

    [Required, MaxLength(32)]
    public string Status { get; set; } = "pending"; // pending | processing | paid | failed | refunded

    [Required, MaxLength(32)]
    public string PaymentMethod { get; set; } = "emola"; // emola | mpesa | cash | card

    [Column(TypeName = "decimal(12,2)")]
    public decimal Amount { get; set; }

    [MaxLength(20)]
    public string? Phone { get; set; }

    /// <summary>JSON snapshot do carrinho no momento do checkout.</summary>
    [Column(TypeName = "json")]
    public string CartSnapshot { get; set; } = "{}";

    /// <summary>Referência externa devolvida pelo operador (eMola, MPesa).</summary>
    [MaxLength(128)]
    public string? ExternalRef { get; set; }

    [MaxLength(1024)]
    public string? ErrorMessage { get; set; }

    public long? OrderId { get; set; }

    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;
}
