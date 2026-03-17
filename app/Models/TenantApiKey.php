<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantApiKey extends Model
{
    protected $fillable = [
        'tenant_id',
        'key',
        'name',
        'last_used_at',
        'expires_at',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at'   => 'datetime',
            'active'       => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
