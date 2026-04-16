<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->enum('status', ['completed', 'voided'])->default('completed')->after('synced');
            $table->text('void_reason')->nullable()->after('status');
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete()->after('void_reason');
            $table->timestamp('voided_at')->nullable()->after('voided_by');
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropIndex(['store_id', 'status']);
            $table->dropColumn(['status', 'void_reason', 'voided_by', 'voided_at']);
        });
    }
};
