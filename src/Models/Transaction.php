<?php

// src/Models/Transaction.php
namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'client_id','tank_id','product_id','date','type',
        'delivered_20','allowance_20','reason','note'
    ];
    protected $casts = ['date' => 'date'];

    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function tank(): BelongsTo { return $this->belongsTo(Tank::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
