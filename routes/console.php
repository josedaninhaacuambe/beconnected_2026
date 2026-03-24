<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Limpar tokens Sanctum expirados — tabela personal_access_tokens cresce indefinidamente
// sem limpeza, o SELECT por token fica mais lento com o tempo
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Limpar jobs falhados com mais de 7 dias da tabela failed_jobs
Schedule::command('queue:flush')->weekly();
