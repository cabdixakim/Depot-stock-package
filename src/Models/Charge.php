<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;

class Charge extends Model
{
    protected $guarded = [];
    public function client(){ return $this->belongsTo(Client::class); }
}
