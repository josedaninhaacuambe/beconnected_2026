<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_visibility_purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('store_visibility_purchases', 'status')) {
                $table->enum('status', ['pending_payment', 'active', 'expired', 'cancelled'])
                    ->default('pending_payment')
                    ->after('payment_reference');
            }
            if (!Schema::hasColumn('store_visibility_purchases', 'next_payment_at')) {
                $table->timestamp('next_payment_at')->nullable();
            }
            if (!Schema::hasColumn('store_visibility_purchases', 'payment_notified_at')) {
                $table->timestamp('payment_notified_at')->nullable();
            }
            if (!Schema::hasColumn('store_visibility_purchases', 'invoice_number')) {
                $table->string('invoice_number', 50)->nullable();
            }
            if (!Schema::hasColumn('store_visibility_purchases', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('store_visibility_purchases', function (Blueprint $table) {
            $cols = array_filter(
                ['status', 'next_payment_at', 'payment_notified_at', 'invoice_number', 'notes'],
                fn($c) => Schema::hasColumn('store_visibility_purchases', $c)
            );
            if ($cols) $table->dropColumn(array_values($cols));
        });
    }
};
