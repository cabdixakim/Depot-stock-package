<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dip extends Model
{
    protected $table = 'dips';

    protected $fillable = [
        'tank_id','date','dip_height','observed_volume',
        'temperature','density','volume_20','book_volume_20','note','created_by_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function tank(): BelongsTo
    {
        return $this->belongsTo(Tank::class);
    }

    public function createdBy()
    {
        $userModel = config('auth.providers.users.model', \App\Models\User::class);

        return $this->belongsTo($userModel, 'created_by_id');
    }
}
