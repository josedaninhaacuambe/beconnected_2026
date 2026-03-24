<?php

namespace App\Providers;

use App\Models\Product;
use App\Policies\ProductPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);

        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        // Endpoints públicos: 120 req/min por IP
        RateLimiter::for('api-public', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        // Endpoints autenticados: 300 req/min por utilizador
        RateLimiter::for('api-auth', function (Request $request) {
            return Limit::perMinute(300)->by($request->user()?->id ?: $request->ip());
        });

        // Login / registo: 10 tentativas por 5 min (anti brute-force)
        RateLimiter::for('auth-strict', function (Request $request) {
            return Limit::perMinutes(5, 10)->by($request->ip());
        });

        // Checkout / pagamento: 20 req/min por utilizador
        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // POS sync: 60 req/min por utilizador
        RateLimiter::for('pos-sync', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Feedback / reviews: 5 submissões por 10 min
        RateLimiter::for('submit', function (Request $request) {
            return Limit::perMinutes(10, 5)->by($request->user()?->id ?: $request->ip());
        });
    }
}
