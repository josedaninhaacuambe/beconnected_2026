<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    public function create(User $user): bool
    {
        return $user->role === 'store_owner';
    }

    public function update(User $user, Store $store): bool
    {
        return $user->id === $store->user_id || $user->role === 'admin';
    }

    public function delete(User $user, Store $store): bool
    {
        return $user->id === $store->user_id || $user->role === 'admin';
    }
}
