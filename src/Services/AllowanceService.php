<?php

namespace Optima\DepotStock\Services;

class AllowanceService
{
    public function allowanceAt20(float $deliveredAt20, float $percent = 0.3): float
    {
        return round($deliveredAt20 * ($percent/100.0), 3);
    }
}
