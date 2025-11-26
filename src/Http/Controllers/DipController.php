<?php

namespace Optima\DepotStock\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

use Optima\DepotStock\Models\Dip;
use Optima\DepotStock\Models\Tank;
use Optima\DepotStock\Models\Offload;
use Optima\DepotStock\Models\Load;
use Optima\DepotStock\Models\Adjustment;
use Optima\DepotStock\Models\DepotPolicy;

// Services
use Optima\DepotStock\Services\StrappingChartService;
use Optima\DepotStock\Services\VolumeCorrectionService;

class DipController extends Controller
{
    /**
     * List dips with filters + KPIs + book balance.
     */
    public function index(Request $request): View
    {
        $tanks = Tank::with(['depot', 'product'])->orderBy('id')->get();

        // --------------------------------------------------
        // BASE QUERY WITH FILTERS
        // --------------------------------------------------
        $dipsQ = Dip::with(['tank.depot', 'tank.product']);

        // Tank filter
        $tankId = null;
        if ($request->filled('tank')) {
            $tankId = (int) $request->input('tank');
            $dipsQ->where('tank_id', $tankId);
        }

        // Range tabs (all / month / week)
        $range = $request->input('range', 'all');
        if ($range === 'month') {
            $dipsQ->whereYear('date', (int) date('Y'))
                  ->whereMonth('date', (int) date('m'));
        } elseif ($range === 'week') {
            $dipsQ->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()]);
        }

        // Explicit month picker overrides range
        if ($request->filled('month')) {
            $parts = explode('-', (string) $request->month);
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $dipsQ->whereYear('date', (int) $parts[0])
                      ->whereMonth('date', (int) $parts[1]);
            }
        }

        $dipsQ->latest('date');
        $dips = $dipsQ->paginate(15);

        // --------------------------------------------------
        // STATS FOR HEADER + BARREL
        // --------------------------------------------------

        // Last dip according to current filters
        $lastDip = (clone $dipsQ)->first();

        // Book balance @20°
        if ($tankId) {
            // per-tank book
            $bookBalance20 = $this->bookBalanceForTank($tankId);
        } else {
            // global across all tanks
            $bookBalance20 = $this->bookBalanceForAllTanks();
        }

        // "Records this month" – respects tank filter, but always current month
        $countQ = Dip::query();
        if ($tankId) {
            $countQ->where('tank_id', $tankId);
        }
        $countQ->whereYear('date', (int) date('Y'))
               ->whereMonth('date', (int) date('m'));
        $countThisMonth = $countQ->count();

        // Global default litres-per-cm from DepotPolicy (fallback 350)
        $defaultLitresPerCm = DepotPolicy::getNumeric('default_dip_litres_per_cm', 350.0);

        return view('depot-stock::dips.index', [
            'tanks'              => $tanks,
            'dips'               => $dips,
            'bookBalance20'      => $bookBalance20,
            'lastDip'            => $lastDip,
            'countThisMonth'     => $countThisMonth,
            'defaultLitresPerCm' => $defaultLitresPerCm,
        ]);
    }

    /**
     * Store a new dip.
     * - Can auto-calc observed + @20 using strapping + VC
     * - Allows manual override of observed_volume and volume_20
     * - Tracks created_by_id
     */
    public function store(
        Request $request,
        StrappingChartService $strap,
        VolumeCorrectionService $vc
    ) {
        try {
            $data = $request->validate([
                'tank_id'         => ['required', 'exists:tanks,id'],
                'date'            => ['required', 'date'],
                'dip_height'      => ['required', 'numeric', 'min:0'],
                'temperature'     => ['required', 'numeric'],
                'density'         => ['required', 'numeric'],
                'note'            => ['nullable', 'string', 'max:500'],
                // optional manual overrides:
                'observed_volume' => ['nullable', 'numeric', 'min:0'],
                'volume_20'       => ['nullable', 'numeric', 'min:0'],
            ]);

            $tank = Tank::with(['depot', 'product'])->findOrFail($data['tank_id']);
            $chartPath = $tank->strapping_chart_path ?? null;

            // Global default litres-per-cm (for fallback)
            $defaultPerCm = DepotPolicy::getNumeric('default_dip_litres_per_cm', 350.0);

            // 1) Observed volume – manual overrides auto
            if ($data['observed_volume'] !== null && $data['observed_volume'] !== '') {
                $observed = (float) $data['observed_volume'];
            } else {
                $observed = null;
                if ($chartPath) {
                    $observed = $strap->heightToVolume($chartPath, (float) $data['dip_height']);
                }
                if (!is_numeric($observed)) {
                    // fallback: policy-controlled litres per cm
                    $observed = (float) $data['dip_height'] * $defaultPerCm;
                }
            }

            // 2) Volume at 20°C – manual overrides auto
            if ($data['volume_20'] !== null && $data['volume_20'] !== '') {
                $volume20 = (float) $data['volume_20'];
            } else {
                $volume20 = $vc->to20C(
                    (float) $observed,
                    (float) $data['temperature'],
                    (float) $data['density']
                );
            }

            $dip = Dip::create([
                'tank_id'         => $data['tank_id'],
                'date'            => Carbon::parse($data['date']),
                'dip_height'      => $data['dip_height'],
                'temperature'     => $data['temperature'],
                'density'         => $data['density'],
                'observed_volume' => $observed,
                'volume_20'       => $volume20,
                'note'            => $data['note'] ?? null,
                'created_by_id'   => $request->user()->id ?? null,
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'ok'  => true,
                    'msg' => 'DIP recorded successfully.',
                    'dip' => [
                        'id'              => $dip->id,
                        'date'            => $dip->date->toDateString(),
                        'tank_id'         => $dip->tank_id,
                        'tank_label'      => $tank->depot->name.' — '.$tank->product->name.' (T'.$tank->id.')',
                        'dip_height'      => $dip->dip_height,
                        'observed_volume' => $dip->observed_volume,
                        'volume_20'       => $dip->volume_20,
                        'note'            => $dip->note,
                    ],
                ]);
            }

            return back()->with('status', 'DIP recorded successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Validation failed',
                    'errors'  => $e->errors(),
                ], 422);
            }
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            if ($request->wantsJson()) {
                return response()->json([
                    'ok'      => false,
                    'message' => $e->getMessage(),
                ], 500);
            }
            return back()->withErrors('Unexpected error: '.$e->getMessage());
        }
    }

    /**
     * Simple CSV export – uses same filters as index().
     */
    public function export(Request $request)
    {
        $dipsQ = Dip::with(['tank.depot', 'tank.product']);

        // same filters as index()
        if ($request->filled('tank')) {
            $dipsQ->where('tank_id', (int) $request->tank);
        }

        $range = $request->input('range', 'all');
        if ($range === 'month') {
            $dipsQ->whereYear('date', (int) date('Y'))
                  ->whereMonth('date', (int) date('m'));
        } elseif ($range === 'week') {
            $dipsQ->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()]);
        }

        if ($request->filled('month')) {
            $parts = explode('-', (string) $request->month);
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $dipsQ->whereYear('date', (int) $parts[0])
                      ->whereMonth('date', (int) $parts[1]);
            }
        }

        $dips = $dipsQ->orderBy('date', 'desc')->get();

        $filename = 'tank_dips_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($dips) {
            $out = fopen('php://output', 'w');

            // header
            fputcsv($out, [
                'Date',
                'Depot',
                'Tank',
                'Product',
                'Dip height (cm)',
                'Observed (L)',
                'Volume @20C (L)',
                'Book @20C (L)',
                'Variance (L)',
                'Note',
            ]);

            foreach ($dips as $dip) {
                $book20   = (float) ($dip->book_volume_20 ?? 0);
                $v20      = (float) ($dip->volume_20 ?? 0);
                $variance = $v20 - $book20;

                fputcsv($out, [
                    optional($dip->date)->format('Y-m-d'),
                    optional($dip->tank->depot)->name,
                    'T'.$dip->tank_id,
                    optional($dip->tank->product)->name,
                    $dip->dip_height,
                    $dip->observed_volume,
                    $v20,
                    $book20 ?: '',
                    $book20 ? $variance : '',
                    $dip->note,
                ]);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function update(Request $request, Dip $dip)
    {
        $data = $request->validate([
            'tank_id'              => ['required', 'integer', 'exists:tanks,id'],
            'date'                 => ['required', 'date'],
            'dip_height'           => ['required', 'numeric', 'min:0'],
            'temperature'          => ['required', 'numeric', 'between:-20,80'],
            'density'              => ['required', 'numeric', 'between:0.6,1.2'],
            'strapping_chart_path' => ['nullable', 'string'],
            'note'                 => ['nullable', 'string', 'max:500'],
        ]);

        $tank = Tank::find((int) $data['tank_id']);
        $chartPath = isset($data['strapping_chart_path']) && $data['strapping_chart_path'] !== ''
            ? $data['strapping_chart_path']
            : ($tank ? $tank->strapping_chart_path : null);

        $observed = $this->observedFromHeight((int) $data['tank_id'], $chartPath, (float) $data['dip_height']);
        $at20     = $this->to20C($observed, (float) $data['temperature'], (float) $data['density']);
        $book20   = $this->bookBalanceForTank((int) $data['tank_id']);

        $dip->tank_id         = (int) $data['tank_id'];
        $dip->date            = $data['date'];
        $dip->dip_height      = (float) $data['dip_height'];
        $dip->observed_volume = $observed;
        $dip->temperature     = (float) $data['temperature'];
        $dip->density         = (float) $data['density'];
        $dip->volume_20       = $at20;
        $dip->book_volume_20  = $book20;
        $dip->note            = $data['note'] ?? null;
        $dip->save();

        return redirect()
            ->route('depot.dips.index', array_merge($request->except('edit'), ['edit' => $dip->id]))
            ->with('toast', ['type' => 'success', 'message' => 'DIP updated successfully.']);
    }

    public function show(Dip $dip)
    {
        $dip->load(['tank.depot', 'tank.product', 'createdBy']);

        return view('depot-stock::dips.show', [
            'dip' => $dip,
        ]);
    }

    // --------------------------------------------------
    // Helpers
    // --------------------------------------------------

    private function observedFromHeight(int $tankId, ?string $csvPath, float $heightCm): float
    {
        // If we have a strapping chart, use it
        $svc = 'Optima\\DepotStock\\Services\\StrappingChartService';
        if ($csvPath && class_exists($svc)) {
            return (float) app($svc)->heightToVolume((string) $csvPath, $heightCm);
        }

        // Otherwise: depot policy-controlled litres per cm
        $perCm = DepotPolicy::getNumeric('default_dip_litres_per_cm', 350.0);

        return max(0.0, $heightCm * $perCm);
    }

    private function to20C(float $observed, float $tempC, float $density): float
    {
        $svc = 'Optima\\DepotStock\\Services\\VolumeCorrectionService';
        if (class_exists($svc)) {
            return (float) app($svc)->to20C($observed, $tempC, $density);
        }
        $alpha = 0.0008;
        return $observed / (1.0 + $alpha * ($tempC - 20.0));
    }

    /**
     * Book balance for a single tank.
     */
    private function bookBalanceForTank(int $tankId): float
    {
        // 1) Offloads INTO tank
        $off = (float) Offload::where('tank_id', $tankId)
            ->sum('delivered_20_l');

        // 2) Loads OUT of tank
        $loads = (float) Load::where('tank_id', $tankId)
            ->sum('loaded_20_l');

        // 3) Adjustments (positive = add, negative = subtract)
        $adjPos = (float) Adjustment::where('tank_id', $tankId)
            ->where('type', 'positive')
            ->sum('amount_20_l');

        $adjNeg = (float) Adjustment::where('tank_id', $tankId)
            ->where('type', 'negative')
            ->sum('amount_20_l');

        // Final book balance
        return ($off + $adjPos) - ($loads + $adjNeg);
    }

    /**
     * Total book balance across all tanks.
     */
    private function bookBalanceForAllTanks(): float
    {
        $off = (float) Offload::sum('delivered_20_l');

        $loads = (float) Load::sum('loaded_20_l');

        $adjPos = (float) Adjustment::where('type', 'positive')
            ->sum('amount_20_l');

        $adjNeg = (float) Adjustment::where('type', 'negative')
            ->sum('amount_20_l');

        return ($off + $adjPos) - ($loads + $adjNeg);
    }
}