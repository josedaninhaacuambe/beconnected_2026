<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['manager', 'cashier', 'stock_keeper', 'viewer'])->default('viewer');
            $table->json('permissions')->nullable()->comment('Permissões específicas: gerir_stock, ver_pedidos, editar_produtos, etc.');
            $table->boolean('is_active')->default(true);
            $table->foreignId('added_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_employees');
    }
};
