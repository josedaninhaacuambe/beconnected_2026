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
        Schema::table('products', function (Blueprint $table) {
            // Controla se o produto é visível para o público geral
            $table->boolean('is_visible_to_public')
                ->default(false)
                ->after('is_active')
                ->comment('Se true, o produto é visível para todos os usuários; caso contrário, apenas o dono da loja pode vê-lo');

            // Campo para data de aprovação do produto pelo admin
            $table->timestamp('approved_at')
                ->nullable()
                ->after('is_visible_to_public')
                ->comment('Data em que o admin aprovou o produto para visibilidade pública');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_visible_to_public', 'approved_at']);
        });
    }
};
