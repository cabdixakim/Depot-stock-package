<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;
use Optima\DepotStock\Models\Concerns\HasCreatedByUser;

class Load extends Model
{
    use HasCreatedByUser;

    protected $casts = [
        'date'        => 'date',];
    protected $guarded = [];

    public function client(){ return $this->belongsTo(Client::class); }
    public function depot(){ return $this->belongsTo(Depot::class); }
    public function tank(){ return $this->belongsTo(Tank::class); }
    public function product(){ return $this->belongsTo(Product::class); }
}
