<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalStockApi extends Model
{
    protected $table = 'external_stock_apis';

    protected $fillable = [
        'store_id', 'name', 'endpoint_url', 'method',
        'headers', 'body_params', 'data_path', 'field_mapping',
        'auto_sync', 'sync_interval_minutes', 'last_synced_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'body_params' => 'array',
            'field_mapping' => 'array',
            'auto_sync' => 'boolean',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
