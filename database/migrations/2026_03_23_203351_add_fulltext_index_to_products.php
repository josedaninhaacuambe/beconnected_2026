<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FULLTEXT index enables MATCH AGAINST — ~10x faster than LIKE '%term%'
        DB::statement('ALTER TABLE products ADD FULLTEXT INDEX ft_products_search (name, model)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE products DROP INDEX ft_products_search');
    }
};
