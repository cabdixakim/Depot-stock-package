<?php
namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;
class Movement extends Model
{
    protected $guarded = [];
    // app/Models/Movement.php
public function billedInvoice(){ return $this->belongsTo(Invoice::class, 'billed_invoice_id'); }

}