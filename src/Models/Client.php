<?php

// src/Models/Client.php
namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $table = 'clients';

    // Optima/DepotStock/Models/Client.php
protected $fillable = ['code','name','email','phone','billing_terms'];

    
    public function transactions(): HasMany { return $this->hasMany(Transaction::class); }

    public function getStockBalance20Attribute(): float
    {
        $in  = (float)$this->transactions()->where('type','IN')->sum('delivered_20');
        $out = (float)$this->transactions()->where('type','OUT')->sum('delivered_20');
        $adj = (float)$this->transactions()->where('type','ADJ')->sum('delivered_20');
        return $in - $out + $adj;
    }
    
    // recent additions
    public function offloads(){ return $this->hasMany(Offload::class); }
    public function loads(){ return $this->hasMany(Load::class); }
    public function adjustments(){ return $this->hasMany(Adjustment::class); }

    public function user()
{
    // The portal user that “belongs” to this client
    $userModel = config('auth.providers.users.model', \App\Models\User::class);

    return $this->hasOne($userModel, 'client_id');
}

  public function invoices()
{
    return $this->hasMany(\Optima\DepotStock\Models\Invoice::class);
}

public function invoiceItems()
{
    return $this->hasMany(\Optima\DepotStock\Models\InvoiceItem::class);
}

}