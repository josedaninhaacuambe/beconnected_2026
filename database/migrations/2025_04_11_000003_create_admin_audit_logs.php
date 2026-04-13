<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->comment('ID do admin que realizou a ação');
            $table->string('action')->comment('Ação realizada (ex: store_visibility_toggled, product_approved)');
            $table->string('entity_type')->comment('Tipo de entidade afetada (Store, Product, User)');
            $table->unsignedBigInteger('entity_id')->comment('ID da entidade');
            $table->json('old_values')->nullable()->comment('Valores antigos');
            $table->json('new_values')->nullable()->comment('Novos valores');
            $table->string('ip_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['admin_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
