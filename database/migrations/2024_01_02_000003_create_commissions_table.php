<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Comissão por produto vendido (0.5 MZN por produto)
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity'); // Quantidade de produtos vendidos
            $table->decimal('rate', 8, 2)->default(0.50); // Taxa por unidade (0.50 MZN)
            $table->decimal('amount', 10, 2); // Total = quantity * rate
            $table->enum('status', ['pending', 'processing', 'paid', 'failed'])->default('pending');
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // Pagamentos de comissão (agrupados por lote)
        Schema::create('commission_payouts', function (Blueprint $table) {
            $table->id();
            $table->decimal('total_amount', 12, 2);
            $table->integer('total_commissions'); // Número de comissões incluídas
            $table->enum('payment_method', ['emola', 'mpesa']);
            $table->string('recipient_phone'); // 840442932 ou 973157227
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('transaction_id')->nullable();
            $table->string('payment_reference')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        // Config do sistema de comissões
        Schema::create('commission_config', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_config');
        Schema::dropIfExists('commission_payouts');
        Schema::dropIfExists('commissions');
    }
};
