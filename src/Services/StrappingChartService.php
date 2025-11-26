<?php

namespace Optima\DepotStock\Services;

use Maatwebsite\Excel\Facades\Excel;

class StrappingChartService
{
    /**
     * Convert dip height (cm) to volume using a CSV table:
     * columns: height_cm, volume_l
     */
    public function heightToVolume(string $csvPath, float $heightCm): ?float
    {
        if (!file_exists($csvPath)) return null;
        $rows = array_map('str_getcsv', file($csvPath));
        $header = array_map('trim', array_shift($rows));
        $data = [];
        foreach ($rows as $r) {
            $row = array_combine($header, $r);
            $data[(float)$row['height_cm']] = (float)$row['volume_l'];
        }
        // Linear interpolate between nearest heights
        ksort($data);
        $keys = array_keys($data);
        if ($heightCm <= $keys[0]) return $data[$keys[0]];
        if ($heightCm >= end($keys)) return $data[end($keys)];

        $prevH = $keys[0];
        foreach ($keys as $k) {
            if ($k >= $heightCm) {
                $nextH = $k;
                $v1 = $data[$prevH]; $v2 = $data[$nextH];
                $t = ($heightCm - $prevH)/($nextH - $prevH);
                return round($v1 + $t*($v2 - $v1), 3);
            }
            $prevH = $k;
        }
        return null;
    }
}
