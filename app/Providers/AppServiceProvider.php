<?php

namespace App\Providers;

use App\Services\Contracts\SignatureProviderInterface;
use App\Services\Providers\Autentique\AutentiqueProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SignatureProviderInterface::class, function () {
            $tenant = request()->tenant;

            return match ($tenant->provider) {
                'autentique' => new AutentiqueProvider(
                    apiKey:  $tenant->provider_api_key,
                    sandbox: $tenant->provider_sandbox,
                ),
                default => throw new RuntimeException("Provedor '{$tenant->provider}' não suportado."),
            };
        });
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $key = $request->header('Authorization') ?? $request->ip();

            return Limit::perMinute(60)->by($key);
        });
    }
}
