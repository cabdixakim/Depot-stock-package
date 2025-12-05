<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepotReconDip extends Model
{
    protected $table = 'depot_recon_dips';

    protected $fillable = [
        'recon_day_id',
        'type',
        'dip_height_cm',
        'temperature_c',
        'density_kg_l',
        'volume_20_l',
        'captured_at',
        'note',
        'created_by_user_id',
    ];

    protected $casts = [
        'dip_height_cm' => 'float',
        'temperature_c' => 'float',
        'density_kg_l'  => 'float',
        'volume_20_l'   => 'float',
        'captured_at'   => 'datetime',
    ];

    public function day(): BelongsTo
    {
        return $this->belongsTo(DepotReconDay::class, 'recon_day_id');
    }
}