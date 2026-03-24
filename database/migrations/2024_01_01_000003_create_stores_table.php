<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('banner')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('email')->nullable();
            $table->foreignId('province_id')->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->foreignId('neighborhood_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->enum('status', ['pending', 'active', 'suspended', 'rejected'])->default('pending');
            $table->boolean('is_featured')->default(false);
            $table->integer('visibility_position')->default(0)->comment('0=normal, higher=more visible (paid)');
            $table->timestamp('visibility_expires_at')->nullable();
            $table->decimal('rating', 3, 2)->default(0.00);
            $table->integer('total_reviews')->default(0);
            $table->boolean('accepts_delivery')->default(true);
            $table->integer('estimated_delivery_minutes')->default(60);
            $table->timestamps();
            $table->softDeletes();
        });

        // Planos de visibilidade/destaque
        Schema::create('visibility_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('duration_days');
            $table->integer('position_boost');
            $table->boolean('is_featured_badge')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('store_visibility_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visibility_plan_id')->constrained()->restrictOnDelete();
            $table->decimal('amount_paid', 10, 2);
            $table->enum('payment_method', ['emola', 'mpesa', 'cash']);
            $table->string('payment_reference')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_visibility_purchases');
        Schema::dropIfExists('visibility_plans');
        Schema::dropIfExists('stores');
    }
};
