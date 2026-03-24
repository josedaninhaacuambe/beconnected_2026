<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockImport extends Model
{
    protected $fillable = [
        'store_id', 'user_id', 'source', 'file_path',
        'api_endpoint', 'api_headers', 'status',
        'total_rows', 'imported_rows', 'updated_rows', 'failed_rows',
        'errors', 'column_mapping', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'api_headers' => 'array',
            'errors' => 'array',
            'column_mapping' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
