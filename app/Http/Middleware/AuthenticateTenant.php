<?php

namespace App\Http\Middleware;

use App\Models\TenantApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            return $this->unauthorized();
        }

        $hash = hash('sha256', $bearerToken);

        $apiKey = TenantApiKey::where('key', $hash)
            ->where('active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->with('tenant')
            ->first();

        if (! $apiKey || ! $apiKey->tenant?->active) {
            return $this->unauthorized();
        }

        $apiKey->update(['last_used_at' => now()]);

        $request->tenant = $apiKey->tenant;

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json([
            'message' => 'API key inválida ou ausente.',
            'code' => 'UNAUTHORIZED',
        ], 401);
    }
}
