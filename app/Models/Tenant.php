<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'provider',
        'provider_api_key',
        'provider_sandbox',
        'webhook_url',
        'webhook_secret',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'provider_api_key' => 'encrypted',
            'provider_sandbox' => 'boolean',
            'active'           => 'boolean',
        ];
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(TenantApiKey::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
