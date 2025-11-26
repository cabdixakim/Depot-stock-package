<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;
use Optima\DepotStock\Models\Concerns\HasCreatedByUser;


class Adjustment extends Model
{
    use HasCreatedByUser;
    
    protected $table = 'adjustments';

    // pick ONE: I’ll use guarded = [] (everything mass-assignable)
    protected $guarded = [];

    protected $casts = [
        'date'              => 'date',
        'client_id'         => 'int',
        'depot_id'          => 'int',
        'tank_id'           => 'int',
        'product_id'        => 'int',
        'amount_20_l'       => 'float',

        // billing fields (make sure migration adds these)
        'is_billable'       => 'bool',
        'billed_invoice_id' => 'int',
        'billed_at'         => 'datetime',
    ];

    public const TYPE_POSITIVE = 'positive';
    public const TYPE_NEGATIVE = 'negative';

    /* ---------------- Relationships ---------------- */
    public function client()  { return $this->belongsTo(Client::class); }
    public function depot()   { return $this->belongsTo(Depot::class); }
    public function tank()    { return $this->belongsTo(Tank::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function billedInvoice() { return $this->belongsTo(Invoice::class, 'billed_invoice_id'); }

    /* ---------------- Accessors / Mutators ---------------- */

    // Normalize 'type' from sign if not explicitly set.
    public function getTypeAttribute($value)
    {
        if ($value === self::TYPE_POSITIVE || $value === self::TYPE_NEGATIVE) return $value;
        $amt = (float) ($this->attributes['amount_20_l'] ?? 0);
        return $amt >= 0 ? self::TYPE_POSITIVE : self::TYPE_NEGATIVE;
    }

    // Useful computed field the invoice builder can read
    public function getBillableVolume20LAttribute(): float
    {
        // Only positive adjustments are billable by policy
        return $this->type === self::TYPE_POSITIVE && $this->is_billable
            ? (float) ($this->amount_20_l ?? 0)
            : 0.0;
    }

    /* ---------------- Scopes ---------------- */

    public function scopeForClient($q, int $clientId)
    {
        return $q->where('client_id', $clientId);
    }

    public function scopeBetween($q, ?string $from, ?string $to)
    {
        if ($from) $q->whereDate('date', '>=', $from);
        if ($to)   $q->whereDate('date', '<=', $to);
        return $q;
    }

    /** Positive + billable + not yet invoiced */
    public function scopeBillableUnbilledForClient($q, int $clientId)
    {
        return $q->where('client_id', $clientId)
                 ->where(function ($qq) {
                     // Prefer stored 'type' if column exists; else infer from amount
                     $qq->where('type', self::TYPE_POSITIVE)
                        ->orWhere(function ($q2) {
                           $q2->whereNull('type')->where('amount_20_l', '>', 0);
                        });
                 })
                 ->where(function ($qq) {
                     $qq->where('is_billable', true)->orWhereNull('is_billable');
                 })
                 ->whereNull('billed_invoice_id');
    }

    /* ---------------- Helpers ---------------- */

    public function markBilled(int $invoiceId): void
    {
        $this->forceFill([
            'billed_invoice_id' => $invoiceId,
            'billed_at'         => now(),
        ])->save();
    }

    // Alias: lets you call $adjustment->invoice
 public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'billed_invoice_id');
    }
}