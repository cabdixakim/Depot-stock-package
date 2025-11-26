<?php
// app/Models/ClientStorageCharge.php
// or packages/DepotStock/src/Models/ClientStorageCharge.php
// adjust path to match your package structure

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;

class ClientStorageCharge extends Model
{
    protected $table = 'client_storage_charges';

    protected $fillable = [
        'client_id',
        'from_date',
        'to_date',
        'cleared_litres',
        'uncleared_litres',
        'total_litres',
        'fee_amount',
        'currency',
        'notes',
        'invoice_id',
        'paid_at',
    ];

    protected $casts = [
        'from_date'       => 'date',
        'to_date'         => 'date',
        'cleared_litres'  => 'float',
        'uncleared_litres'=> 'float',
        'total_litres'    => 'float',
        'fee_amount'      => 'float',
        'paid_at'         => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}