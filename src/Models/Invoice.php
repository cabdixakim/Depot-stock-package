<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Optima\DepotStock\Models\Concerns\HasCreatedByUser;

class Invoice extends Model
{
    use HasCreatedByUser;
    
    protected $guarded = [];
    protected $table = 'invoices';

    // If your table has these columns already, great; otherwise they’re ignored
    protected $casts = [
        'date'       => 'date',
        'subtotal'   => 'float',
        'tax_total'  => 'float',
        'total'      => 'float',
        'paid_total' => 'float',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // ── Business helpers ─────────────────────────────────────────────
    public function recalculateTotals(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $this->forceFill(['paid_total' => $paid])->saveQuietly();

        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        $total = (float) ($this->total ?? 0);
        $paid  = (float) ($this->paid_total ?? 0);

        $new = 'issued'; // default lifecycle once it exists
        if ($paid <= 0 && in_array($this->status, ['draft','issued','unpaid'], true)) {
            $new = 'issued';
        } elseif ($paid > 0 && $paid < $total) {
            $new = 'partial';
        } elseif ($total > 0 && round($paid, 3) >= round($total, 3)) {
            $new = 'paid';
        }

        if ($this->status !== $new) {
            $this->forceFill(['status' => $new])->saveQuietly();
        }
    }



// In Optima\DepotStock\Models\Invoice
public function getOverpaidAmountAttribute(): float
{
    return max(0, (float)($this->paid_total ?? 0) - (float)($this->total ?? 0));
}

}