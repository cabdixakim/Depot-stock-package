<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;

class DepotPolicy extends Model
{
    protected $table = 'depot_policies';

    protected $fillable = [
        'code',          // e.g. max_storage_days
        'name',          // e.g. Max storage days
        'value_numeric', // decimal(20,4)
        'value_text',    // text value
        'notes',
    ];

    /**
     * Get numeric policy (uses value_numeric).
     */
    public static function getNumeric(string $code, float $default = 0.0): float
    {
        $row = static::where('code', $code)->first();

        if (!$row || $row->value_numeric === null || $row->value_numeric === '') {
            return $default;
        }

        return (float) $row->value_numeric;
    }

    /**
     * Get text policy (uses value_text).
     */
    public static function getText(string $code, string $default = ''): string
    {
        $row = static::where('code', $code)->first();

        if (!$row || $row->value_text === null || $row->value_text === '') {
            return $default;
        }

        return (string) $row->value_text;
    }

    /**
     * Universal getter – returns text or numeric automatically.
     */
    public static function getValue(string $code, $default = null)
    {
        $row = static::where('code', $code)->first();

        if (!$row) return $default;

        if (!empty($row->value_numeric)) return (float) $row->value_numeric;
        if (!empty($row->value_text)) return (string) $row->value_text;

        return $default;
    }
}