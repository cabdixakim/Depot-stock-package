<?php

namespace Optima\DepotStock\Services;

class VolumeCorrectionService
{
    /**
     * Rough conversion to volume at 20°C using ASTM petroleum correction.
     *
     * @param float $observed Observed volume (L)
     * @param float $temperature Temperature in °C
     * @param float $density Density at observed temp (kg/L)
     * @return float
     */
    public function to20C(float $observed, float $temperature, float $density): float
    {
        // Simple approximation factor: volume decreases ~0.0008 per degree above 20°C
        $alpha = 0.0008;

        return $observed / (1 + $alpha * ($temperature - 20));
    }
}
