<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\StoreEmployee;
use App\Models\User;

class ProductPolicy
{
    public function update(User $user, Product $product): bool
    {
        if ($user->role === 'admin') return true;
        if ($user->id === $product->store->user_id) return true;

        // Funcionário com permissão de adicionar/editar produtos
        $employee = StoreEmployee::where('user_id', $user->id)
            ->where('store_id', $product->store_id)
            ->where('is_active', true)
            ->first();

        return $employee && $employee->hasPermission('adicionar_produtos');
    }

    public function delete(User $user, Product $product): bool
    {
        if ($user->role === 'admin') return true;
        if ($user->id === $product->store->user_id) return true;

        // Apenas o dono ou admin podem apagar produtos
        return false;
    }
}
