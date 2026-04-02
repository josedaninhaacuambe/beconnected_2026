using Beconnect.PaymentService.Data;
using Beconnect.PaymentService.Services;
using Microsoft.EntityFrameworkCore;
using Polly;
using Polly.Extensions.Http;
using Serilog;
using StackExchange.Redis;

// ─── Logger de arranque ───────────────────────────────────────────────────────
Log.Logger = new LoggerConfiguration()
    .WriteTo.Console()
    .WriteTo.File("logs/payment-.log", rollingInterval: RollingInterval.Day, retainedFileCountLimit: 14)
    .CreateLogger();

try
{
    Log.Information("[Payment Service] Starting up");

    var builder = WebApplication.CreateBuilder(args);
    builder.Host.UseSerilog();

    // ─── Configurações ────────────────────────────────────────────────────────
    var cfg = builder.Configuration;

    // ─── EF Core → MySQL (mesmo DB do Laravel, schema separado) ──────────────
    builder.Services.AddDbContext<PaymentDbContext>(opt =>
        opt.UseMySql(
            cfg.GetConnectionString("Default") ??
            $"Server={cfg["DB_HOST"] ?? "mysql"};Port={cfg["DB_PORT"] ?? "3306"};" +
            $"Database={cfg["DB_DATABASE"] ?? "beconnect"};" +
            $"User={cfg["DB_USERNAME"] ?? "beconnect"};" +
            $"Password={cfg["DB_PASSWORD"] ?? "beconnect_secret"};",
            ServerVersion.AutoDetect(
                cfg.GetConnectionString("Default") ??
                $"Server={cfg["DB_HOST"] ?? "mysql"};Port=3306;Database={cfg["DB_DATABASE"] ?? "beconnect"};" +
                $"User={cfg["DB_USERNAME"] ?? "beconnect"};Password={cfg["DB_PASSWORD"] ?? "beconnect_secret"};"
            )
        )
    );

    // ─── Redis ────────────────────────────────────────────────────────────────
    builder.Services.AddSingleton<IConnectionMultiplexer>(_ =>
        ConnectionMultiplexer.Connect($"{cfg["REDIS_HOST"] ?? "redis"}:{cfg["REDIS_PORT"] ?? "6379"}"));

    // ─── Retry policy Polly (3 tentativas, backoff exponencial) ───────────────
    static IAsyncPolicy<HttpResponseMessage> RetryPolicy() =>
        HttpPolicyExtensions
            .HandleTransientHttpError()
            .WaitAndRetryAsync(3, attempt => TimeSpan.FromSeconds(Math.Pow(2, attempt)));

    // ─── HTTP Clients (Stock Service + Laravel App) ───────────────────────────
    builder.Services.AddHttpClient("StockService", c =>
    {
        c.BaseAddress = new Uri(cfg["STOCK_SERVICE_URL"] ?? "http://stock-service:8081");
        c.Timeout     = TimeSpan.FromSeconds(10);
        c.DefaultRequestHeaders.Add("X-Internal-Key", cfg["INTERNAL_API_KEY"] ?? "beconnect_internal");
    }).AddPolicyHandler(RetryPolicy());

    builder.Services.AddHttpClient("LaravelApp", c =>
    {
        c.BaseAddress = new Uri(cfg["LARAVEL_APP_URL"] ?? "http://nginx:80");
        c.Timeout     = TimeSpan.FromSeconds(15);
        c.DefaultRequestHeaders.Add("X-Internal-Key", cfg["INTERNAL_API_KEY"] ?? "beconnect_internal");
    }).AddPolicyHandler(RetryPolicy());

    // ─── eMola HTTP Client ────────────────────────────────────────────────────
    builder.Services.AddHttpClient<EMolaService>(c =>
    {
        c.BaseAddress = new Uri(cfg["EMola:ApiUrl"] ?? "https://api.emola.co.mz");
        c.Timeout     = TimeSpan.FromSeconds(30);
        c.DefaultRequestHeaders.Add("Authorization", $"Bearer {cfg["EMola:ApiKey"]}");
    }).AddPolicyHandler(RetryPolicy());

    builder.Services.AddScoped<CheckoutService>();
    builder.Services.AddControllers();
    builder.Services.AddEndpointsApiExplorer();

    var app = builder.Build();

    // ─── Migração automática ao arrancar ──────────────────────────────────────
    using (var scope = app.Services.CreateScope())
    {
        var db = scope.ServiceProvider.GetRequiredService<PaymentDbContext>();
        await db.Database.MigrateAsync();
    }

    app.UseRouting();
    app.MapControllers();

    app.MapGet("/health", () => Results.Ok(new { status = "ok", service = "payment-service" }));

    await app.RunAsync();
}
catch (Exception ex)
{
    Log.Fatal(ex, "[Payment Service] Startup failed");
}
finally
{
    await Log.CloseAndFlushAsync();
}
