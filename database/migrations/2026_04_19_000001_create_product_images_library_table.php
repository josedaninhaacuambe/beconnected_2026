<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images_library', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->index();          // nome normalizado para pesquisa
            $table->string('original_name', 255);          // nome original para exibição
            $table->string('path', 500);                   // caminho no storage (webp comprimido)
            $table->unsignedInteger('size_bytes')->default(0); // tamanho após compressão
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedInteger('use_count')->default(0);  // quantas vezes foi usada
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images_library');
    }
};
