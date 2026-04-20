<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Novos campos em stock_movements: entrada por caixa + preço de aquisição + validade
        Schema::table('stock_movements', function (Blueprint $table) {
            // Modo de entrada: 'unit' (padrão) ou 'box' (por caixa)
            $table->enum('entry_mode', ['unit', 'box'])->default('unit')->after('reason');
            // Unidades por caixa (apenas para entry_mode='box')
            $table->decimal('units_per_box', 10, 3)->nullable()->after('entry_mode');
            // Número de caixas (apenas para entry_mode='box')
            $table->decimal('boxes_count', 10, 3)->nullable()->after('units_per_box');
            // Preço de aquisição do lote (custo de compra ao fornecedor)
            $table->decimal('acquisition_price', 14, 2)->nullable()->after('boxes_count');
            // Data de validade do lote (apenas para produtos com has_expiry=true)
            $table->date('expiry_date')->nullable()->after('acquisition_price');
        });

        // Flag no produto: indica se deve controlar validade no stock
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('has_expiry')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['entry_mode', 'units_per_box', 'boxes_count', 'acquisition_price', 'expiry_date']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('has_expiry');
        });
    }
};
