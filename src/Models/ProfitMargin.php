<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;

class ProfitMargin extends Model
{
    protected $table = 'profit_margins';
    protected $guarded = [];
    protected $casts = [
        'client_id'        => 'int',
        'effective_from'   => 'date',
        'margin_per_litre' => 'float',
    ];

    public function client() { return $this->belongsTo(Client::class); }
}