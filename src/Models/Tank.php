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
}
