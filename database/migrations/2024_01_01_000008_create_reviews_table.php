<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('reviewable'); // product ou store
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('rating')->unsigned(); // 1-5
            $table->text('comment')->nullable();
            $table->json('images')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'reviewable_type', 'reviewable_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
