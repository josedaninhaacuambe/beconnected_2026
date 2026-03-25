<?php

namespace App\Notifications;

use App\Models\StoreVisibilityPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpiringVisibilityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public StoreVisibilityPurchase $purchase) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Visibilidade de loja expirando em breve')
            ->line("A loja {$this->purchase->store->name} tem visibilidade expirando em {$this->purchase->expires_at->diffForHumans()}.")
            ->line("Plano: {$this->purchase->plan->name}")
            ->action('Ver detalhes', url("/admin/stores/{$this->purchase->store->id}"))
            ->line('Entre em contato com o dono da loja para renovação.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'expiring_visibility',
            'store_id' => $this->purchase->store_id,
            'store_name' => $this->purchase->store->name,
            'expires_at' => $this->purchase->expires_at,
            'plan' => $this->purchase->plan->name,
        ];
    }
}