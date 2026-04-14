<?php

namespace App\Traits;

use App\Models\Store;
use Illuminate\Http\Request;

/**
 * Trait para resolver a loja activa do dono, com suporte multi-loja.
 *
 * Fluxo:
 *  1. Lê o header X-Store-Id (ou query param store_id).
 *  2. Valida que a loja pertence ao utilizador autenticado.
 *  3. Se não vier header, usa a primeira loja do utilizador (retrocompatível).
 */
trait ResolvesOwnerStore
{
    protected function resolveOwnerStore(Request $request): Store
    {
        $user    = $request->user();
        $storeId = $request->header('X-Store-Id') ?? $request->input('store_id');

        if ($storeId) {
            return Store::where('id', $storeId)
                ->where('user_id', $user->id)
                ->firstOrFail();
        }

        return Store::where('user_id', $user->id)->firstOrFail();
    }
}
