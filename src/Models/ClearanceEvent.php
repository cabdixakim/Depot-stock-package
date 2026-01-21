<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;

class ClearanceEvent extends Model
{
    protected $fillable = [
        'clearance_id',
        'event',
        'from_status',
        'to_status',
        'user_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function clearance()
    {
        return $this->belongsTo(Clearance::class);
    }

    public function user()
    {
        $userModel = config('auth.providers.users.model');
        return $this->belongsTo($userModel, 'user_id');
    }
}