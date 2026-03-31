<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncPosStockToSearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'search_index';
    public int $tries = 2;

    public function __construct(private array $productIds) {}

    public function handle(): void
    {
        foreach ($this->productIds as $id) {
            IndexProductInSearch::dispatch($id)->onQueue('search_index');
        }
    }
}
