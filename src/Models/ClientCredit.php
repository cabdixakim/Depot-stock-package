<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientCredit extends Model
{
    protected $table = 'client_credits';

    protected $fillable = [
        'client_id',
        'payment_id',
        'invoice_id',
        'amount',
        'remaining',
        'currency',
        'reason',
        'source',
        'created_by',
        'meta',
    ];

    protected $casts = [
        'amount'    => 'decimal:2',
        'remaining' => 'decimal:2',
        'meta'      => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}