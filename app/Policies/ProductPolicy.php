<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function update(User $user, Product $product): bool
    {
        return $user->id === $product->store->user_id || $user->role === 'admin';
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->id === $product->store->user_id || $user->role === 'admin';
    }
}
