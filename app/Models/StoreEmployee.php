<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreEmployee extends Model
{
    protected $fillable = ['store_id', 'user_id', 'role', 'permissions', 'is_active', 'added_by'];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];

    // Permissões disponíveis no sistema POS
    // fazer_vendas   → acesso ao Terminal de Vendas
    // gerir_stock    → acesso à gestão de Stock
    // ver_relatorios → acesso aos Relatórios (reservado ao dono + quem ele autorizar)
    // gerir_equipa   → acesso à Equipa/Funcionários (reservado ao dono)
    // adicionar_produtos → criar produtos no POS (offline ou online)
    // deletar_venda   → apagar vendas confirmadas/finalizadas por engano (reservado ao dono)
    public const ALL_PERMISSIONS = [
        'fazer_vendas', 'gerir_stock', 'ver_relatorios', 'gerir_equipa', 'adicionar_produtos', 'deletar_venda',
    ];

    public static function defaultPermissions(string $role): array
    {
        return match ($role) {
            'manager'     => ['fazer_vendas', 'gerir_stock', 'adicionar_produtos', 'deletar_venda'],
            'cashier'     => ['fazer_vendas'],
            'stock_keeper'=> ['gerir_stock', 'adicionar_produtos'],
            'viewer'      => ['ver_relatorios'],
            default       => [],
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
