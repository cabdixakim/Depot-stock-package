<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Optima\DepotStock\Models\Depot;
use Optima\DepotStock\Models\Product;
use Optima\DepotStock\Models\DepotPolicy;

class DepotController extends Controller
{
    public function index()
    {
        $depots = Depot::with(['tanks' => function ($q) {
                $q->orderBy('name');
            }])
            ->withCount('tanks')
            ->orderBy('name')
            ->get();

        $activeId    = session('depot.active_id');
        $activeDepot = $activeId ? $depots->firstWhere('id', $activeId) : null;

        $products = Product::orderBy('name')->get();

        return view('depot-stock::depots.index', [
            'depots'      => $depots,
            'activeId'    => $activeId,
            'activeDepot' => $activeDepot,
            'products'    => $products,
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name'     => ['required','string','max:255'],
            'location' => ['nullable','string','max:255'],
        ]);

        Depot::create($data);

        return redirect()
            ->route('depot.depots.index')
            ->with('status', 'Depot created.');
    }

    public function update(Request $r, Depot $depot)
    {
        $data = $r->validate([
            'name'     => ['required','string','max:255'],
            'location' => ['nullable','string','max:255'],
        ]);

        $depot->update($data);

        return redirect()
            ->route('depot.depots.index')
            ->with('status', 'Depot updated.');
    }

    public function destroy(Depot $depot)
    {
        $depot->delete();

        if (session('depot.active_id') == $depot->id) {
            session()->forget('depot.active_id');
        }

        return redirect()
            ->route('depot.depots.index')
            ->with('status', 'Depot removed.');
    }

    public function toggleStatus(Depot $depot)
    {
        $current       = ($depot->status ?? 'active') === 'active';
        $depot->status = $current ? 'inactive' : 'active';
        $depot->save();

        if ($depot->status === 'inactive' && session('depot.active_id') == $depot->id) {
            session()->forget('depot.active_id');
        }

        return redirect()
            ->route('depot.depots.index')
            ->with('status', 'Depot ' . ($depot->status === 'active' ? 'activated' : 'deactivated') . '.');
    }

    public function setActive(Request $r)
    {
        $id = $r->input('depot_id');

        if ($id === null || $id === '' || $id === 'all') {
            session()->forget('depot.active_id');
        } else {
            $r->validate([
                'depot_id' => ['required', Rule::exists('depots', 'id')],
            ]);
            session(['depot.active_id' => (int)$id]);
        }

        return response()->json([
            'ok'     => true,
            'active' => session('depot.active_id'),
        ]);
    }

    /**
     * Save global depot policies (allowance rate, idle days, risk thresholds, dip L/cm).
     * Uses depot_policies table with `code`, `name`, `value_numeric`.
     */
    public function savePolicies(Request $r)
    {
        $data = $r->validate([
            'allowance_rate'                => ['nullable', 'numeric'],
            'max_storage_days'              => ['nullable', 'integer', 'min:0'],
            'max_zero_physical_load_litres' => ['nullable', 'numeric', 'min:0'],
            'uncleared_flag_threshold'      => ['nullable', 'numeric', 'min:0'],
            'default_dip_litres_per_cm'     => ['nullable', 'numeric', 'min:0'],
        ]);

        // Human-readable names for the policies
        $labels = [
            'allowance_rate'                => 'Offload allowance rate',
            'max_storage_days'              => 'Max storage days',
            'max_zero_physical_load_litres' => 'Max load at zero stock',
            'uncleared_flag_threshold'      => 'Uncleared stock flag threshold',
            'default_dip_litres_per_cm'     => 'Default dip litres per cm',
        ];

        $map = [
            'allowance_rate'                => $data['allowance_rate'] ?? null,
            'max_storage_days'              => $data['max_storage_days'] ?? null,
            'max_zero_physical_load_litres' => $data['max_zero_physical_load_litres'] ?? null,
            'uncleared_flag_threshold'      => $data['uncleared_flag_threshold'] ?? null,
            'default_dip_litres_per_cm'     => $data['default_dip_litres_per_cm'] ?? null,
        ];

        foreach ($map as $code => $value) {
            if ($value === null) {
                // leave existing row untouched if the field was left empty
                continue;
            }

            DepotPolicy::updateOrCreate(
                ['code' => $code],
                [
                    'name'          => $labels[$code] ?? $code,
                    'value_numeric' => $value,
                ]
            );
        }

        return redirect()
            ->route('depot.depots.index')
            ->with('status', 'Depot policies saved.');
    }
}