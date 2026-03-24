<?php

namespace App\Services;

class DeliveryService
{
    private float $baseFee;
    private float $feePerKm;

    public function __construct()
    {
        $this->baseFee = (float) config('services.delivery.base_fee', 50);
        $this->feePerKm = (float) config('services.delivery.fee_per_km', 5);
    }

    /**
     * Calcular taxa de entrega com base na cidade
     */
    public function calculateFee(int $cityId, ?float $distanceKm = null, float $weightKg = 1): float
    {
        // Taxa extra por kg (acima de 1kg)
        $weightExtra = max(0, $weightKg - 1) * 3; // 3 MZN por kg extra

        if ($distanceKm !== null) {
            return round($this->baseFee + ($this->feePerKm * $distanceKm) + $weightExtra, 2);
        }

        $cityFees = [
            1 => 50, 2 => 60, 3 => 70, // Maputo
            4 => 60,                     // Matola
            5 => 50,                     // Beira
            6 => 50,                     // Nampula
            7 => 45,                     // Chimoio
        ];

        return round(($cityFees[$cityId] ?? $this->baseFee) + $weightExtra, 2);
    }

    /**
     * Calcular distância entre dois pontos (Haversine)
     */
    public function calculateDistance(
        float $lat1, float $lon1,
        float $lat2, float $lon2
    ): float {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Encontrar estafetas disponíveis próximos
     */
    public function findAvailableDrivers(float $latitude, float $longitude, float $radiusKm = 5): \Illuminate\Support\Collection
    {
        return \App\Models\DeliveryDriver::with('user')
            ->where('status', 'approved')
            ->where('is_available', true)
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->get()
            ->filter(function ($driver) use ($latitude, $longitude, $radiusKm) {
                $distance = $this->calculateDistance(
                    $latitude, $longitude,
                    $driver->current_latitude, $driver->current_longitude
                );
                return $distance <= $radiusKm;
            })
            ->sortBy(function ($driver) use ($latitude, $longitude) {
                return $this->calculateDistance(
                    $latitude, $longitude,
                    $driver->current_latitude, $driver->current_longitude
                );
            });
    }
}
