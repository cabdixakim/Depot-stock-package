<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;
use Optima\DepotStock\Models\Concerns\HasCreatedByUser; 

class Payment extends Model
{
    use HasCreatedByUser;

    protected $guarded = [];
    // app/Models/Payment.php
    public function invoice(){ return $this->belongsTo(Invoice::class); }
    public function client(){ return $this->belongsTo(Client::class); }
}
