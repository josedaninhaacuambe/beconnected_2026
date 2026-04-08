<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // IVA na venda POS
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->boolean('apply_vat')->default(false)->after('discount');
            $table->decimal('vat_rate', 5, 2)->default(17.00)->after('apply_vat'); // % IVA
            $table->decimal('vat_amount', 12, 2)->default(0)->after('vat_rate');
        });

        // Preço de custo no item (para calcular lucro)
        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 2)->default(0)->after('unit_price');
        });

        // Preço de custo no produto (para o terminal carregar)
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 2)->default(0)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropColumn(['apply_vat', 'vat_rate', 'vat_amount']);
        });
        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
    }
};
