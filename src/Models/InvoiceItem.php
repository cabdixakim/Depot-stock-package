<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $table = 'invoice_items';

    // Keep it simple while building
    protected $guarded = [];

    protected $casts = [
        'date'           => 'date',
        'litres'         => 'float',
        'rate_per_litre' => 'float',
        'amount'         => 'float',
        'meta'           => 'array',
    ];

    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function client()  { return $this->belongsTo(Client::class); }
}