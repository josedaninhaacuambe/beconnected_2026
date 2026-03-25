<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'admin_note')) {
                $table->text('admin_note')->nullable();
            }
            if (!Schema::hasColumn('orders', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'refund_flag')) {
                $table->tinyInteger('refund_flag')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $cols = array_filter(['admin_note', 'resolved_at', 'refund_flag'], fn($c) => Schema::hasColumn('orders', $c));
            if ($cols) $table->dropColumn(array_values($cols));
        });
    }
};
