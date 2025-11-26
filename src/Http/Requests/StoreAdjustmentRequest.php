<?php

namespace Optima\DepotStock\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdjustmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'date'        => ['required','date'],
            'tank_id'     => ['required','exists:tanks,id'],
            'depot_id'    => ['nullable','exists:depots,id'],
            'product_id'  => ['nullable','exists:products,id'],
            'amount_20_l' => ['required','numeric'], // ± allowed
            'reason'      => ['nullable','string','max:255'],
            'reference'   => ['nullable','string','max:100'],
            'note'        => ['nullable','string','max:255'],
            'truck_plate'   => ['nullable','string','max:50'],
            'trailer_plate' => ['nullable','string','max:50'],
            'is_billable'   => ['nullable','boolean'],
        
        ];
    }
}
