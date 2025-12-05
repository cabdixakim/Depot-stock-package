<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepotReconDay extends Model
{
    protected $table = 'depot_recon_days';

    protected $fillable = [
        'tank_id',
        'date',
        'status',
        'opening_l_20',
        'closing_expected_l_20',
        'closing_actual_l_20',
        'variance_l_20',
        'variance_pct',
        'note',
        'created_by_user_id',
        'checked_by_user_id',
    ];

    protected $casts = [
        'date'                 => 'date',
        'opening_l_20'         => 'float',
        'closing_expected_l_20'=> 'float',
        'closing_actual_l_20'  => 'float',
        'variance_l_20'        => 'float',
        'variance_pct'         => 'float',
    ];

    public function tank(): BelongsTo
    {
        return $this->belongsTo(Tank::class);
    }

    public function dips(): HasMany
    {
        return $this->hasMany(DepotReconDip::class, 'recon_day_id');
    }

    public function openingDip(): HasMany
    {
        return $this->dips()->where('type', 'opening');
    }

    public function closingDip(): HasMany
    {
        return $this->dips()->where('type', 'closing');
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }


    public function createdBy()
{
    return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
}

public function checkedBy()
{
    return $this->belongsTo(\App\Models\User::class, 'checked_by_user_id');
}
}