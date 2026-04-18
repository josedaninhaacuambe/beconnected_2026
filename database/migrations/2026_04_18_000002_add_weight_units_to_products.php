<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Lista de unidades disponíveis para venda por peso neste produto
            // Ex: ['kg', 'g'] significa que o vendedor pode escolher kg ou g no POS
            $table->json('weight_units')->nullable()->after('weight_unit')
                  ->comment('Unidades de peso disponíveis para este produto no POS');
        });

        // Expandir o enum weight_unit para incluir tonelada e litro
        // MySQL não suporta ALTER COLUMN em enums directamente — usar raw SQL
        DB::statement("ALTER TABLE products MODIFY COLUMN weight_unit ENUM('g','kg','l','ml','un','tonelada','litro') DEFAULT 'un'");
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('weight_units');
        });

        DB::statement("ALTER TABLE products MODIFY COLUMN weight_unit ENUM('g','kg','l','ml','un') DEFAULT 'un'");
    }
};
