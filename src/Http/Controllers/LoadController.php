<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Optima\DepotStock\Http\Requests\StoreLoadRequest;
use Optima\DepotStock\Models\Client;
use Optima\DepotStock\Models\Load;
use Optima\DepotStock\Models\Tank;

class LoadController extends Controller
{
    /**
     * Store a new load for a client.
     *
     * NOTE: This does NOT block loads that exceed client stock.
     * Any “overdraw” warnings are handled purely in the UI.
     */
    public function store(StoreLoadRequest $request, Client $client)
    {
        // Only validated data from your FormRequest
        $data = $request->validated();

        // Derive depot & product from the selected tank
        $tank = Tank::with(['depot', 'product'])->findOrFail($data['tank_id']);
        $data['depot_id']   = $tank->depot_id;
        $data['product_id'] = $tank->product_id;

        // Attach the client
        $data['client_id'] = $client->id;

        // Create the record (model must allow these fillable attributes)
        $load = Load::create($data);

        // Return JSON for your AJAX handler (modal)
        if ($request->expectsJson()) {
            return response()->json([
                'ok'            => true,
                'message'       => 'Load saved',
                'id'            => $load->id,
                'date'          => Carbon::parse($load->date)->format('M d'),
                'tank_label'    => $load->tank
                    ? ($load->tank->depot->name . ' / ' . $load->tank->product->name)
                    : ('Tank #' . $load->tank_id),
                'loaded_20_l'   => (float) $load->loaded_20_l,
                'truck_plate'   => (string) $load->truck_plate,
                'trailer_plate' => (string) $load->trailer_plate,
            ]);
        }

        return back()->with('status', 'Load saved');
    }

    /**
     * Update a single load (inline / modal edit).
     */
    public function update(Request $request, Client $client, Load $load)
    {
        abort_if($load->client_id !== $client->id, 404);

        $data = $request->validate([
            'date'            => ['sometimes', 'date'],
            'loaded_20_l'     => ['sometimes', 'numeric', 'min:0'],
            'temperature_c'   => ['nullable', 'numeric'],
            'density_kg_l'    => ['nullable', 'numeric'],
            'truck_plate'     => ['nullable', 'string', 'max:50'],
            'trailer_plate'   => ['nullable', 'string', 'max:50'],
            'reference'       => ['nullable', 'string', 'max:100'],
            'note'            => ['nullable', 'string', 'max:255'],
        ]);

        $load->fill($data)->save();

        return response()->json([
            'ok'      => true,
            'message' => 'Load updated',
        ]);
    }

    /**
     * Hard-delete a load (if allowed by your business rules).
     */
    public function destroy(Client $client, Load $load)
    {
        abort_if($load->client_id !== $client->id, 404);

        $load->delete();

        return response()->json([
            'ok'      => true,
            'message' => 'Load deleted',
        ]);
    }

    /**
     * Bulk update loads from Tabulator grid.
     */
    public function bulkUpdate(Request $request, Client $client)
    {
        $rows  = $request->input('rows', []);
        $count = 0;

        foreach ($rows as $row) {
            if (!isset($row['id'])) {
                continue;
            }

            $load = Load::where('client_id', $client->id)->find($row['id']);
            if (!$load) {
                continue;
            }

            $load->fill(Arr::only($row, [
                'date',
                'loaded_20_l',
                'temperature_c',
                'density_kg_l',
                'truck_plate',
                'trailer_plate',
                'reference',
                'note',
            ]));

            $load->save();
            $count++;
        }

        return response()->json([
            'ok'      => true,
            'updated' => $count,
        ]);
    }
}