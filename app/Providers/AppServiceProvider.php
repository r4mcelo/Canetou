<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Providers\Autentique\AutentiqueProvider;
use App\Services\Contracts\SignatureProviderInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
                    apiKey: $tenant->provider_api_key,
                    sandbox: $tenant->provider_sandbox,
                ),
                default => throw new RuntimeException("Provedor '{$tenant->provider}' não suportado."),
            };
        });
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Gate::define('viewPulse', function (User $user) {
            return $user->is_root;
        });

        RateLimiter::for('api', function (Request $request) {
            $key = $request->header('Authorization') ?? $request->ip();

            return Limit::perMinute(60)->by($key);
        });
    }
}
