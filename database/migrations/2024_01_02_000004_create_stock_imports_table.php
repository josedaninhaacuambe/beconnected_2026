<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('source', ['excel', 'csv', 'json', 'api_webhook', 'api_pull']);
            $table->string('file_path')->nullable(); // Para Excel/CSV
            $table->string('api_endpoint')->nullable(); // Para API externa
            $table->json('api_headers')->nullable(); // Headers de autenticação da API
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('total_rows')->default(0);
            $table->integer('imported_rows')->default(0);
            $table->integer('updated_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->json('errors')->nullable();
            $table->json('column_mapping')->nullable(); // Mapeamento de colunas do ficheiro
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Configuração de API externa de stock (por loja)
        Schema::create('external_stock_apis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // Nome descritivo da integração
            $table->string('endpoint_url');
            $table->enum('method', ['GET', 'POST'])->default('GET');
            $table->json('headers')->nullable(); // Auth headers, API keys, etc.
            $table->json('body_params')->nullable(); // Parâmetros do body para POST
            $table->string('data_path')->nullable(); // JSONPath para os dados, ex: "data.products"
            $table->json('field_mapping')->nullable(); // Mapeamento de campos
            $table->boolean('auto_sync')->default(false);
            $table->integer('sync_interval_minutes')->default(60);
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_stock_apis');
        Schema::dropIfExists('stock_imports');
    }
};
