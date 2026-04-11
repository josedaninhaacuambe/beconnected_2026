<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:fix-store-user-associations')]
#[Description('Corrige associações entre lojas e usuários proprietários')]
class FixStoreUserAssociations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verificando associações entre lojas e usuários...');

        // 1. Encontrar lojas sem user_id
        $storesWithoutUser = Store::whereNull('user_id')->get();
        $this->info("Encontradas {$storesWithoutUser->count()} lojas sem user_id associado.");

        // 2. Encontrar usuários store_owner sem loja
        $ownersWithoutStore = User::where('role', 'store_owner')
            ->whereDoesntHave('store')
            ->get();
        $this->info("Encontrados {$ownersWithoutStore->count()} proprietários sem loja associada.");

        // 3. Tentar corrigir associações baseadas em dados similares
        $fixed = 0;

        foreach ($ownersWithoutStore as $owner) {
            // Procurar loja com nome/email similar
            $possibleStore = Store::where(function ($query) use ($owner) {
                $query->where('name', 'like', '%' . $owner->name . '%')
                      ->orWhere('email', $owner->email);
            })->whereNull('user_id')->first();

            if ($possibleStore) {
                $possibleStore->update(['user_id' => $owner->id]);
                $this->info("✅ Associada loja '{$possibleStore->name}' ao usuário '{$owner->name}'");
                $fixed++;
            }
        }

        // 4. Relatório final
        $remainingStores = Store::whereNull('user_id')->count();
        $remainingOwners = User::where('role', 'store_owner')
            ->whereDoesntHave('store')
            ->count();

        $this->info("📊 Relatório final:");
        $this->info("   - Associações corrigidas: {$fixed}");
        $this->info("   - Lojas sem user_id restantes: {$remainingStores}");
        $this->info("   - Proprietários sem loja restantes: {$remainingOwners}");

        if ($remainingStores > 0 || $remainingOwners > 0) {
            $this->warn("⚠️  Ainda há associações pendentes. Pode ser necessário intervenção manual.");
        } else {
            $this->info("🎉 Todas as associações foram corrigidas!");
        }

        return 0;
    }
}
