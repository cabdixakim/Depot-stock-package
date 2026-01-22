<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;

class Clearance extends Model
{
    protected $fillable = [
        'client_id',
        'depot_id',
        'product_id',
        'status',
        'is_bonded',
        'truck_number',
        'trailer_number',
        'loaded_20_l',
        'invoice_number',
        'delivery_note_number',
        'border_point',
        'submitted_at',
        'submitted_by',
        'tr8_number',
        'tr8_issued_at',
        'arrived_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'is_bonded' => 'boolean',
        'submitted_at' => 'datetime',
        'tr8_issued_at' => 'datetime',
        'arrived_at' => 'datetime',
    ];

    /* ================= Relations ================= */

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function documents()
    {
        return $this->hasMany(ClearanceDocument::class);
    }

    public function events()
    {
        return $this->hasMany(ClearanceEvent::class);
    }
   
    // Relation to Offload
    public function offload()
{
    return $this->hasOne(\Optima\DepotStock\Models\Offload::class, 'clearance_id');
}

}