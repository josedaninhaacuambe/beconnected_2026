<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Antes de adicionar o índice único, limpar duplicados existentes
        // Mantém o registo mais antigo (menor id) para cada (store_id, local_id)
        DB::statement("
            DELETE ps1 FROM pos_sales ps1
            INNER JOIN pos_sales ps2
                ON ps1.store_id = ps2.store_id
                AND ps1.local_id = ps2.local_id
                AND ps1.id > ps2.id
            WHERE ps1.local_id IS NOT NULL
        ");

        Schema::table('pos_sales', function (Blueprint $table) {
            // Índice único parcial: (store_id, local_id) apenas para linhas com local_id preenchido
            // Impede race conditions em que dois pedidos simultâneos passam o exists() check
            $table->unique(['store_id', 'local_id'], 'pos_sales_store_local_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropUnique('pos_sales_store_local_unique');
        });
    }
};
