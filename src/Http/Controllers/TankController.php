<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Optima\DepotStock\Models\Tank;

class TankController extends Controller
{
    public function index()
    {
        // Everything is handled via the depots page modal now.
        return redirect()->route('depot.depots.index');
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'depot_id'   => 'required|integer|exists:depots,id',
            'name'       => 'required|string|max:255',
            'product_id' => 'nullable|integer|exists:products,id',
            'capacity_l' => 'required|numeric|min:0',
            'strapping_chart_path' => 'nullable|string|max:255',
            'strapping_chart'      => 'nullable|file|mimes:csv,txt,xls,xlsx|max:5120',
        ]);

        // Handle uploaded strapping chart file, if any
        if ($r->hasFile('strapping_chart')) {
            $path = $r->file('strapping_chart')->store('strapping-charts');
            $data['strapping_chart_path'] = $path;
        }

        // If you add a 'status' column to tanks, default to 'active'
        if (!array_key_exists('status', $data)) {
            $data['status'] = 'active';
        }

        Tank::create($data);

        return redirect()
            ->route('depot.depots.index')
            ->with('status', 'Tank added.');
    }

    public function update(Request $r, Tank $tank)
    {
        $data = $r->validate([
            'name'       => 'required|string|max:255',
            'product_id' => 'nullable|integer|exists:products,id',
            'capacity_l' => 'required|numeric|min:0',
            'strapping_chart_path' => 'nullable|string|max:255',
            'strapping_chart'      => 'nullable|file|mimes:csv,txt,xls,xlsx|max:5120',
        ]);

        // Handle uploaded strapping chart file, if any (replace existing)
        if ($r->hasFile('strapping_chart')) {
            $path = $r->file('strapping_chart')->store('strapping-charts');
            $data['strapping_chart_path'] = $path;
        }

        $tank->update($data);

        return redirect()
            ->route('depot.depots.index')
            ->with('status', 'Tank updated.');
    }

    /**
     * Toggle tank active / inactive using 'status' column (if present).
     */
    public function toggleStatus(Tank $tank)
    {
        $current = ($tank->status ?? 'active') === 'active';
        $tank->status = $current ? 'inactive' : 'active';
        $tank->save();

        return redirect()
            ->route('depot.depots.index')
            ->with('status', 'Tank ' . ($tank->status === 'active' ? 'activated' : 'deactivated') . '.');
    }
}