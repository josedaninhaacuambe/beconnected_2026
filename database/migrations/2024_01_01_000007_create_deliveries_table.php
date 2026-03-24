<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('vehicle_type')->nullable()->comment('moto, carro, bicicleta');
            $table->string('license_plate')->nullable();
            $table->string('id_document')->nullable();
            $table->boolean('is_available')->default(false);
            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();
            $table->enum('status', ['pending', 'approved', 'suspended'])->default('pending');
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('delivery_drivers')->nullOnDelete();
            $table->string('tracking_code')->unique();
            $table->enum('status', ['pending', 'assigned', 'picking_up', 'in_transit', 'delivered', 'failed'])->default('pending');
            $table->decimal('fee', 10, 2);
            $table->decimal('distance_km', 8, 2)->nullable();
            // Origem (loja ou ponto de coleta)
            $table->string('pickup_address')->nullable();
            $table->decimal('pickup_latitude', 10, 8)->nullable();
            $table->decimal('pickup_longitude', 11, 8)->nullable();
            // Destino (cliente)
            $table->string('dropoff_address');
            $table->decimal('dropoff_latitude', 10, 8)->nullable();
            $table->decimal('dropoff_longitude', 11, 8)->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('delivery_drivers');
    }
};
