<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->after('estimated_delivery_minutes', function (Blueprint $table) {
                // Invoice customization fields for 2K+ tier stores
                $table->boolean('invoice_show_logo')->default(true);
                $table->string('invoice_header_text')->nullable()->comment('Custom header text on invoice');
                $table->text('invoice_footer_text')->nullable()->comment('Custom footer text on invoice');
                $table->string('invoice_format')->default('80mm')->comment('Receipt format: 80mm, 100mm, A4');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_show_logo',
                'invoice_header_text',
                'invoice_footer_text',
                'invoice_format',
            ]);
        });
    }
};
