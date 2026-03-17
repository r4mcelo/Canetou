<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'external_id',
        'external_provider',
        'name',
        'status',
        'signers',
        'provider_response',
        'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'signers'           => 'array',
            'provider_response' => 'array',
            'signed_at'         => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
