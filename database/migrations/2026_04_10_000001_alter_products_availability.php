<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Change pos_only to availability enum
            $table->enum('availability', ['virtual_store', 'pos', 'both'])->default('both')->after('is_featured');
        });

        // Migrate existing data
        DB::statement("UPDATE products SET availability = CASE WHEN pos_only = 1 THEN 'pos' ELSE 'both' END");

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('pos_only');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('pos_only')->default(false)->after('is_featured');
        });

        // Revert data
        DB::statement("UPDATE products SET pos_only = CASE WHEN availability = 'pos' THEN 1 ELSE 0 END");

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('availability');
        });
    }
};