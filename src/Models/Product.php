<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [];
    public function tanks(){ return $this->hasMany(Tank::class); }
}
