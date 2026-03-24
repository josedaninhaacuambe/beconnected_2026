<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryDriver;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(private DeliveryService $deliveryService) {}

    // Estimar custo e disponibilidade de entrega
    public function estimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id'    => 'required|exists:cities,id',
            'latitude'   => 'nullable|numeric',
            'longitude'  => 'nullable|numeric',
            'weight_kg'  => 'nullable|numeric|min:0.1|max:200',
        ]);

        $weightKg = (float) ($validated['weight_kg'] ?? 1);
        $lat      = $validated['latitude']  ?? null;
        $lng      = $validated['longitude'] ?? null;

        // Se temos coordenadas, calcular distância média às lojas activas da cidade
        $distanceKm = null;
        if ($lat && $lng) {
            $store = \App\Models\Store::where('city_id', $validated['city_id'])
                ->where('status', 'active')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->get()
                ->map(fn($s) => $this->deliveryService->calculateDistance($lat, $lng, $s->latitude, $s->longitude))
                ->avg();
            $distanceKm = $store ? round($store, 1) : null;
        }

        $fee = $this->deliveryService->calculateFee($validated['city_id'], $distanceKm, $weightKg);

        $drivers = 0;
        if ($lat && $lng) {
            $drivers = $this->deliveryService->findAvailableDrivers($lat, $lng, 10)->count();
        }

        return response()->json([
            'fee'               => $fee,
            'distance_km'       => $distanceKm,
            'weight_kg'         => $weightKg,
            'available_drivers' => $drivers,
            'estimated_minutes' => $distanceKm ? (int) round($distanceKm * 3 + 10) : 30,
        ]);
    }

    // Rastrear entrega
    public function track(string $trackingCode): JsonResponse
    {
        $delivery = Delivery::with(['order.user', 'driver.user'])
            ->where('tracking_code', $trackingCode)
            ->firstOrFail();

        return response()->json([
            'tracking_code' => $delivery->tracking_code,
            'status' => $delivery->status,
            'driver' => $delivery->driver ? [
                'name' => $delivery->driver->user->name,
                'vehicle_type' => $delivery->driver->vehicle_type,
                'latitude' => $delivery->driver->current_latitude,
                'longitude' => $delivery->driver->current_longitude,
            ] : null,
            'dropoff_address' => $delivery->dropoff_address,
            'assigned_at' => $delivery->assigned_at,
            'picked_up_at' => $delivery->picked_up_at,
            'delivered_at' => $delivery->delivered_at,
        ]);
    }

    // Registar como estafeta
    public function registerAsDriver(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_type' => 'required|in:moto,carro,bicicleta',
            'license_plate' => 'nullable|string|max:20',
        ]);

        $driver = DeliveryDriver::updateOrCreate(
            ['user_id' => $request->user()->id],
            [...$validated, 'status' => 'pending']
        );

        return response()->json([
            'message' => 'Registo submetido. Aguarde aprovação.',
            'driver' => $driver,
        ]);
    }

    // Actualizar disponibilidade do estafeta
    public function updateAvailability(Request $request): JsonResponse
    {
        $driver = DeliveryDriver::where('user_id', $request->user()->id)
            ->where('status', 'approved')
            ->firstOrFail();

        $validated = $request->validate([
            'is_available' => 'required|boolean',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $driver->update([
            'is_available' => $validated['is_available'],
            'current_latitude' => $validated['latitude'] ?? $driver->current_latitude,
            'current_longitude' => $validated['longitude'] ?? $driver->current_longitude,
        ]);

        return response()->json(['message' => 'Disponibilidade actualizada.']);
    }

    // Aceitar entrega (estafeta)
    public function acceptDelivery(Request $request, Delivery $delivery): JsonResponse
    {
        $driver = DeliveryDriver::where('user_id', $request->user()->id)
            ->where('status', 'approved')
            ->where('is_available', true)
            ->firstOrFail();

        if ($delivery->driver_id !== null) {
            return response()->json(['message' => 'Entrega já atribuída.'], 422);
        }

        $delivery->update([
            'driver_id' => $driver->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $driver->update(['is_available' => false]);

        return response()->json(['message' => 'Entrega aceite.', 'delivery' => $delivery]);
    }

    // Actualizar estado da entrega (estafeta)
    public function updateDeliveryStatus(Request $request, Delivery $delivery): JsonResponse
    {
        $driver = DeliveryDriver::where('user_id', $request->user()->id)->firstOrFail();

        if ($delivery->driver_id !== $driver->id) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:picking_up,in_transit,delivered,failed',
        ]);

        $updates = ['status' => $validated['status']];

        if ($validated['status'] === 'picking_up') {
            $updates['picked_up_at'] = now();
        }

        if ($validated['status'] === 'delivered') {
            $updates['delivered_at'] = now();
            $delivery->order->update(['status' => 'delivered']);
            $driver->update(['is_available' => true]);
        }

        $delivery->update($updates);

        return response()->json(['message' => 'Estado actualizado.', 'delivery' => $delivery]);
    }
}
