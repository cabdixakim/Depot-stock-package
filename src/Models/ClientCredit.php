<?php

// src/Models/ClientCredit.php
namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;

class ClientCredit extends Model
{
    protected $table = 'client_credits';
    protected $guarded = [];
    protected $casts = [
        'amount'    => 'float',
        'remaining' => 'float',
    ];

    public function client()  { return $this->belongsTo(Client::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
}