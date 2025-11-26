<?php

namespace Optima\DepotStock\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoadRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'date'               => ['required','date'],
            'tank_id'            => ['required','exists:tanks,id'],
            'depot_id'           => ['nullable','exists:depots,id'],
            'product_id'         => ['nullable','exists:products,id'],

            'loaded_observed_l'  => ['nullable','numeric','min:0'],
            'loaded_20_l'        => ['required','numeric','min:0'],

            'temperature_c'      => ['nullable','numeric'],
            'density_kg_l'       => ['nullable','numeric'],

            'reference'          => ['nullable','string','max:100'],
            'note'               => ['nullable','string','max:255'],
            'truck_plate'   => ['required','string','max:50'],
            'trailer_plate' => ['required','string','max:50'],
            
        ];
    }
}
