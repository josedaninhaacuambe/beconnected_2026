<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->index(['status'], 'idx_stores_status');
            $table->index(['status', 'is_featured'], 'idx_stores_status_featured');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['store_id', 'is_active'], 'idx_products_store_active');
            $table->index(['total_sold'], 'idx_products_total_sold');
            $table->index(['flash_until'], 'idx_products_flash_until');
            $table->index(['is_active', 'is_featured'], 'idx_products_active_featured');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex('idx_stores_status');
            $table->dropIndex('idx_stores_status_featured');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_store_active');
            $table->dropIndex('idx_products_total_sold');
            $table->dropIndex('idx_products_flash_until');
            $table->dropIndex('idx_products_active_featured');
        });
    }
};
