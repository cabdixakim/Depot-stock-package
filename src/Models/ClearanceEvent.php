<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;

class ClearanceDocument extends Model
{
    protected $table = 'clearance_documents';

    protected $fillable = [
        'clearance_id',
        'type',
        'file_path',
        'original_name',
        'uploaded_by',
    ];

    public function clearance()
    {
        return $this->belongsTo(Clearance::class);
    }
}