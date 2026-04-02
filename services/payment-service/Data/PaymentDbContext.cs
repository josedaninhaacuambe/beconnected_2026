using Beconnect.PaymentService.Models;
using Microsoft.EntityFrameworkCore;

namespace Beconnect.PaymentService.Data;

public class PaymentDbContext : DbContext
{
    public PaymentDbContext(DbContextOptions<PaymentDbContext> options) : base(options) { }

    public DbSet<CheckoutRequest> CheckoutRequests => Set<CheckoutRequest>();

    protected override void OnModelCreating(ModelBuilder builder)
    {
        builder.Entity<CheckoutRequest>(e =>
        {
            e.HasIndex(c => c.IdempotencyKey).IsUnique();
            e.HasIndex(c => c.UserId);
            e.HasIndex(c => c.Status);
            e.HasIndex(c => c.ExternalRef);
        });
    }
}
