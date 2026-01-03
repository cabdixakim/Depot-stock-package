<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [];
    protected $fillable = ['name', 'default_density'];
    public function tanks(){ return $this->hasMany(Tank::class); }
}
