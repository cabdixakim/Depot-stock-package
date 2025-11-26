<?php

// Optima/DepotStock/Http/Controllers/AdjustmentController.php
namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Routing\Controller;
use Optima\DepotStock\Http\Requests\StoreAdjustmentRequest;
use Optima\DepotStock\Models\Adjustment;
use Optima\DepotStock\Models\Client;
use Optima\DepotStock\Models\Tank;

class AdjustmentController extends Controller
{
    public function store(StoreAdjustmentRequest $request, Client $client)
    {
        $data = $request->validated();

        // fill depot_id & product_id from tank if missing
        if (empty($data['depot_id']) || empty($data['product_id'])) {
            $tank = Tank::with(['depot','product'])->findOrFail($data['tank_id']);
            $data['depot_id']   = $tank->depot_id;
            $data['product_id'] = $tank->product_id;
        }

        $data['client_id'] = $client->id;

        try {
            $adj = Adjustment::create($data);
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Failed to save adjustment.',
                    'error' => app()->hasDebugModeEnabled() ? $e->getMessage() : null,
                ], 500);
            }
            return back()->withErrors('Failed to save adjustment.');
        }

        if ($request->expectsJson()) {
            // Return simple JSON (no view rendering needed)
            return response()->json([
                'ok'      => true,
                'message' => 'Adjustment saved',
                'adjustment' => $adj,
            ]);
        }

        return back()->with('status','Adjustment saved');
    }
}