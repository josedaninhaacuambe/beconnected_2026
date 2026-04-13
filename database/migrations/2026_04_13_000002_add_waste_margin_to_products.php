<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Margem de desperdício/perda natural (%) — usado em produtos por peso
            $table->decimal('waste_margin', 5, 2)->default(0)->after('weight_unit');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('waste_margin');
        });
    }
};
