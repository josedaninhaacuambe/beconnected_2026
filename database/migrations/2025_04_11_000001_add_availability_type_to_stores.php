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
        Schema::table('stores', function (Blueprint $table) {
            // Tipo de disponibilidade: 'pos_only', 'virtual_only', 'both'
            // Default: 'both' para compatibilidade com lojas existentes
            $table->enum('availability_type', ['pos_only', 'virtual_only', 'both'])
                ->default('both')
                ->after('status')
                ->comment('Define se a loja está disponível apenas no POS, apenas na loja virtual, ou em ambos');

            // Controla se a loja está visível para todos os usuários
            $table->boolean('is_visible_to_public')
                ->default(false)
                ->after('availability_type')
                ->comment('Se true, a loja e seus produtos são visíveis para todos os usuários');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['availability_type', 'is_visible_to_public']);
        });
    }
};
