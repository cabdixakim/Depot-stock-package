<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;
use Optima\DepotStock\Models\Concerns\HasCreatedByUser;

class Offload extends Model
{

    use HasCreatedByUser;

    protected $table = 'offloads';
    protected $guarded = [];

    protected $casts = [
        'date'                 => 'date',
        'client_id'            => 'int',
        'depot_id'             => 'int',
        'tank_id'              => 'int',
        'product_id'           => 'int',
        'loaded_observed_l'    => 'float',
        'delivered_observed_l' => 'float',
        'delivered_20_l'       => 'float',
        'shortfall_20_l'       => 'float',
        'depot_allowance_20_l' => 'float',
        'temperature_c'        => 'float',
        'density_kg_l'         => 'float',

        // new billing fields (added by migration)
        'billed_invoice_id'    => 'int',
        'billed_at'            => 'datetime',
    ];

    public function client()  { return $this->belongsTo(Client::class); }
    public function depot()   { return $this->belongsTo(Depot::class); }
    public function tank()    { return $this->belongsTo(Tank::class); }
    public function product() { return $this->belongsTo(Product::class); }

    // Optional: if you have an Invoice model in app/Models
    public function billedInvoice() { return $this->belongsTo(Invoice::class, 'billed_invoice_id'); }

    /** Unbilled offloads for a client (what we will invoice) */
    public function scopeUnbilledForClient($q, $clientId)
    {
        return $q->where('client_id', $clientId)
                 ->whereNull('billed_invoice_id');
    }

    /** Handy date window filter (optional) */
    public function scopeBetween($q, ?string $from, ?string $to)
    {
        if ($from) $q->whereDate('date', '>=', $from);
        if ($to)   $q->whereDate('date', '<=', $to);
        return $q;
    }

    // Alias: lets you call $offload->invoice instead of billedInvoice
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'billed_invoice_id');
    }
}