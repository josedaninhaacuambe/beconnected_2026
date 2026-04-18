<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_stock', function (Blueprint $table) {
            // Mudar quantidade de INTEGER para DECIMAL(10,3)
            // Permite registar stock em kg (ex: 10.500 kg)
            // Valores inteiros existentes mantêm-se iguais (5 → 5.000)
            $table->decimal('quantity', 10, 3)->default(0)->change();
            $table->decimal('minimum_stock', 10, 3)->default(0)->change();

            // Stock alocado para venda por peso (em kg/l/g conforme produto)
            // Separado do stock geral — o dono aloca parte do stock para venda por peso
            $table->decimal('weight_quantity', 10, 3)->default(0)->after('quantity')
                  ->comment('Stock alocado para venda por peso (kg, g, l, etc.)');
        });
    }

    public function down(): void
    {
        Schema::table('product_stock', function (Blueprint $table) {
            $table->dropColumn('weight_quantity');
            $table->integer('quantity')->default(0)->change();
            $table->integer('minimum_stock')->default(0)->change();
        });
    }
};
