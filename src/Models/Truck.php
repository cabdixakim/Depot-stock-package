<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;

class Truck extends Model
{
    protected $guarded = [];
    public function client(){ return $this->belongsTo(Client::class); }
}
