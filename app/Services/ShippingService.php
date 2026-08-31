<?php

namespace App\Services;

use App\Models\Settings;
use App\Models\ShippingRule;

class ShippingService
{
    protected ?array $store = null;

    public function calculate(?float $lat, ?float $lng, ?string $method = null): array
    {
        if ($method === 'PICKUP' || ($lat === null || $lng === null)) {
            return [
                'method' => $method === 'PICKUP' ? 'PICKUP' : 'DELIVERY',
                'distanceKm' => null,
                'cost' => 0,
            ];
        }

        $distance = $this->distance($this->storeLat(), $this->storeLng(), $lat, $lng);

        $rule = ShippingRule::where('active', true)
            ->where('minKm', '<=', $distance)
            ->where('maxKm', '>=', $distance)
            ->orderBy('price', 'asc')
            ->first();

        return [
            'method' => 'DELIVERY',
            'distanceKm' => round($distance, 2),
            'cost' => $rule ? (float) $rule->price : 0,
        ];
    }

    protected function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earth * 2 * asin(sqrt($a));
    }

    protected function storeLat(): float
    {
        return $this->storeLocation()['lat'] ?? -6.2;
    }

    protected function storeLng(): float
    {
        return $this->storeLocation()['lng'] ?? 106.8;
    }

    protected function storeLocation(): array
    {
        if ($this->store === null) {
            $settings = Settings::first();
            $this->store = ($settings && ! empty($settings->store))
                ? $settings->store
                : ['lat' => -6.2, 'lng' => 106.8];
        }

        return $this->store;
    }
}
