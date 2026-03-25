<?php

namespace App\Console\Commands;

use App\Models\StoreVisibilityPurchase;
use App\Models\User;
use App\Notifications\ExpiringVisibilityNotification;
use Illuminate\Console\Command;

class NotifyExpiringVisibilities extends Command
{
    protected $signature = 'visibilities:notify-expiring';
    protected $description = 'Notify admins about stores with visibilities expiring soon';

    public function handle()
    {
        $expiringSoon = StoreVisibilityPurchase::with(['store.owner', 'plan'])
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(7))
            ->whereNull('payment_notified_at')
            ->get();

        $admins = User::where('role', 'admin')->get();

        foreach ($expiringSoon as $purchase) {
            foreach ($admins as $admin) {
                $admin->notify(new ExpiringVisibilityNotification($purchase));
            }
            $purchase->update(['payment_notified_at' => now()]);
        }

        $this->info("Notified about {$expiringSoon->count()} expiring visibilities.");
    }
}