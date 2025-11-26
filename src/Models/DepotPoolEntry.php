<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepotPoolEntry extends Model
{
    protected $table = 'depot_pool_entries';

    protected $fillable = [
        'depot_id',
        'product_id',
        'date',
        'type',          // 'in' | 'out'
        'volume_20_l',
        'unit_price',   // positive for 'in', negative allowed if you prefer single-sign; we’ll keep sign aligned to type here
        'ref_type',      // 'allowance' | 'allowance_correction' | 'allowance_reversal' | 'transfer' | 'sell' | ...
        'ref_id',        // offload_id (for allowance*) or custom ids for manual ops
        'note',
        'created_by',
    ];

    protected $casts = [
        'date'        => 'date',
        'volume_20_l' => 'float',
        'unit_price'  => 'float',
        'depot_id'    => 'int',
        'product_id'  => 'int',
        'ref_id'      => 'int',
        'created_by'  => 'int',
    ];

    // Types
    public const TYPE_IN  = 'in';
    public const TYPE_OUT = 'out';

    // Ref types
    public const REF_ALLOWANCE           = 'allowance';
    public const REF_ALLOWANCE_CORR      = 'allowance_correction';
    public const REF_ALLOWANCE_REVERSAL  = 'allowance_reversal';
    public const REF_TRANSFER            = 'transfer'; // future
    public const REF_SELL                = 'sell';     // future

    public function depot(): BelongsTo   { return $this->belongsTo(Depot::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function user(): BelongsTo    { return $this->belongsTo(\App\Models\User::class, 'created_by'); }

    /* Convenience factories */
    public static function allowance(array $attrs): self
    {
        $attrs['ref_type'] = self::REF_ALLOWANCE;
        $attrs['type']     = self::TYPE_IN;
        return static::create($attrs);
    }

    public static function allowanceCorrection(float $delta, array $attrs): ?self
    {
        $delta = round($delta, 3);
        if (abs($delta) < 0.0005) return null;

        $attrs['ref_type'] = self::REF_ALLOWANCE_CORR;
        $attrs['type']     = $delta >= 0 ? self::TYPE_IN : self::TYPE_OUT;
        $attrs['volume_20_l'] = abs($delta);
        return static::create($attrs);
    }

    public static function allowanceReversal(float $amount, array $attrs): ?self
    {
        $amount = round($amount, 3);
        if ($amount <= 0) return null;

        $attrs['ref_type'] = self::REF_ALLOWANCE_REVERSAL;
        $attrs['type']     = self::TYPE_OUT;
        $attrs['volume_20_l'] = $amount;
        return static::create($attrs);
    }

    /* Scopes */
    public function scopeBetween($q, ?string $from, ?string $to)
    {
        if ($from) $q->whereDate('date', '>=', $from);
        if ($to)   $q->whereDate('date', '<=', $to);
        return $q;
    }
}