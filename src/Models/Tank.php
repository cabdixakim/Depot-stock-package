<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tank extends Model
{
    protected $table = 'tanks';

    protected $fillable = [
        'depot_id','product_id','name','capacity_l','strapping_chart_path','status',
    ];

    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function dips(): HasMany
    {
        return $this->hasMany(Dip::class);
    }

    /**
     * Get the depot pool balance for a tank (by depot, product, and optional date).
     */
    public static function poolBalanceForTank(int $tankId, $date = null): float
    {
        $tank = self::find($tankId);
        if (!$tank) return 0.0;
        $query = DepotPoolEntry::where('depot_id', $tank->depot_id)
            ->where('product_id', $tank->product_id);
        if ($date) {
            $query->where('date', '<=', $date);
        }
        return (float) $query->sum('volume_20_l');
    }
}
