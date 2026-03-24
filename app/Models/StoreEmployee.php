<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreEmployee extends Model
{
    protected $fillable = ['store_id', 'user_id', 'role', 'permissions', 'is_active', 'added_by'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // Permissões disponíveis por role
    public static function defaultPermissions(string $role): array
    {
        return match ($role) {
            'manager' => ['gerir_stock', 'ver_pedidos', 'editar_produtos', 'ver_relatorios', 'gerir_funcionarios'],
            'cashier' => ['ver_pedidos', 'processar_vendas'],
            'stock_keeper' => ['gerir_stock', 'editar_produtos'],
            'viewer' => ['ver_pedidos', 'ver_relatorios'],
            default => [],
        };
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? self::defaultPermissions($this->role);
        return in_array($permission, $permissions);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
