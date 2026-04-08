<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Produtos vendidos por peso (cereais, legumes, etc.)
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_weighable')->default(false)->after('is_active');
            $table->enum('weight_unit', ['g', 'kg', 'l', 'ml', 'un'])->default('un')->after('is_weighable');
        });

        // Item de venda com peso/unidade medida
        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->decimal('weight_amount', 10, 3)->nullable()->after('quantity'); // ex: 0.750 kg
            $table->string('weight_unit', 5)->nullable()->after('weight_amount');   // g, kg, l, ml, un
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_weighable', 'weight_unit']);
        });
        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->dropColumn(['weight_amount', 'weight_unit']);
        });
    }
};
