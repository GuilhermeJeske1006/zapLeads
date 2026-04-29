<?php

namespace App\Services;

use App\Models\Empresa;

class GeoService
{
    private const EARTH_RADIUS_KM = 6371;

    public function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round(self::EARTH_RADIUS_KM * $c, 2);
    }

    public function isNearby(Empresa $empresa, float $lat, float $lon): bool
    {
        if (!$empresa->latitude || !$empresa->longitude) {
            return false;
        }

        $distance = $this->haversine($empresa->latitude, $empresa->longitude, $lat, $lon);
        return $distance <= $empresa->raio_atendimento;
    }

    public function calcularDistancia(Empresa $empresa, float $lat, float $lon): float
    {
        if (!$empresa->latitude || !$empresa->longitude) {
            return 0;
        }

        return $this->haversine($empresa->latitude, $empresa->longitude, $lat, $lon);
    }

    public function calcularLeadScore(float $distancia, float $raio): int
    {
        if ($distancia <= 0) {
            return 100;
        }

        if ($distancia > $raio * 2) {
            return 10;
        }

        $ratio = $distancia / $raio;
        return (int) max(10, round(100 - ($ratio * 90)));
    }
}
