{{-- depot-stock::clients/forms/lossModal.blade.php --}}
<div id="lossModal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl overflow-hidden ring-1 ring-gray-200">
    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-gradient-to-r from-amber-50 to-white">
      <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
        <span class="text-amber-600">Δ</span>
        Loss Breakdown
      </h2>
      <button type="button" data-loss-close
              class="rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 p-1.5">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <div class="p-5 overflow-y-auto max-h-[75vh] text-sm text-gray-700">
      @php
        use Illuminate\Pagination\AbstractPaginator;
        use Optima\DepotStock\Models\Offload;

        // --------------------------------------------------
        // 1) Base source: ALL offloads for this client,
        //    filtered by the same window as the page (from/to).
        // --------------------------------------------------
        $req = request();

        $offQ = Offload::with(['tank.depot','tank.product'])
          ->where('client_id', $client->id)
          ->when($req->filled('from'), fn($q) => $q->whereDate('date', '>=', $req->date('from')))
          ->when($req->filled('to'),   fn($q) => $q->whereDate('date', '<=', $req->date('to')));

        // NOTE: no "this month" hard-coding; this follows the page window.
        $source = $offQ->orderBy('date')->orderBy('id')->get();

        // --------------------------------------------------
        // 2) Helper to read first existing key from a row
        // --------------------------------------------------
        $get = function($row, array $keys, $default = null) {
          foreach ($keys as $k) {
            if (is_object($row) && isset($row->$k)) return $row->$k;
            if (is_array($row)  && array_key_exists($k, $row)) return $row[$k];
          }
          return $default;
        };

        // column alias pools
        $aliasDelivered20 = ['delivered_20_l','delivered20','delivered_at_20','qty_20_l','qty_20','delivered'];
        // prefer true loaded @20; fallbacks keep it robust
        $aliasLoaded20    = ['loaded_20_l','loaded20','loaded_at_20','qty_loaded_20','loaded'];
        $aliasObserved20  = ['observed_20_l','observed20','observed_at_20','obs_20_l','obs_20','loaded_observed_l'];
        $aliasAllowance20 = ['depot_allowance_20_l','depot_allowance_l','allowance_20_l','allowance_l'];
        $aliasPlates      = ['plates','plate','truck_plate','truck_plates','registration','reg_no','vehicle','vehicle_no'];

        // --------------------------------------------------
        // 3) Normalise rows (limit keeps modal snappy)
        // --------------------------------------------------
        $rows = $source->take(100)->map(function ($r) use ($get, $aliasDelivered20, $aliasLoaded20, $aliasObserved20, $aliasAllowance20, $aliasPlates) {
          $rawDate = $r->date ? \Carbon\Carbon::parse($r->date)->format('Y-m-d') : null;

          $delivered = (float) $get($r, $aliasDelivered20, 0);

          // loaded: prefer loaded @20, else observed @20, else loaded_observed_l
          $loaded = (float) $get($r, $aliasLoaded20, 0);
          if ($loaded == 0.0) {
            $loaded = (float) $get($r, $aliasObserved20, 0);
          }

          $allowance = (float) $get($r, $aliasAllowance20, 0);
          if ($allowance == 0.0 && $delivered > 0) {
            // fallback to simple 0.3% when allowance column is missing
            $allowance = round($delivered * 0.003, 3);
          }

          $shortfall = max($loaded - $delivered, 0);

          return [
            'raw_date'  => $rawDate,
            'date'      => $rawDate ? \Carbon\Carbon::parse($rawDate)->format('d M Y') : '—',
            'tank'      => optional($r->tank)->depot->name ?? '—',
            'prod'      => optional($r->tank)->product->name ?? '—',
            'plates'    => trim((string) ($get($r, $aliasPlates, '') ?? '')),
            'loaded'    => $loaded,
            'delivered' => $delivered,
            'shrink'    => $allowance,       // allowance only
            'short'     => $shortfall,       // loaded - delivered (>=0)
            'total'     => $allowance + $shortfall,
          ];
        });

        $totalShrink     = (float) $rows->sum('shrink');
        $totalTruckShort = (float) $rows->sum('short');
        $totalLoss       = $totalShrink + $totalTruckShort;

        // show columns if any non-zero or any value present
        $showLoaded    = $rows->contains(fn($x) => ($x['loaded'] ?? 0) != 0);
        $showDelivered = $rows->contains(fn($x) => ($x['delivered'] ?? 0) != 0);
        $showShort     = $rows->contains(fn($x) => ($x['short'] ?? 0) != 0) || $showLoaded || $showDelivered;
        $showShrink    = $rows->contains(fn($x) => ($x['shrink'] ?? 0) != 0);
        $showPlates    = $rows->contains(fn($x) => ($x['plates'] ?? '') !== '');
      @endphp

      {{-- Local filter bar (on top of page filters) --}}
      <div class="mb-4 flex flex-wrap items-end gap-3 text-[12px]">
        <div class="flex items-center gap-2">
          <span class="uppercase tracking-wide text-gray-500 text-[11px]">View</span>
          <div class="flex rounded-full bg-gray-100 p-1 gap-1">
            <button type="button" class="loss-range-btn px-2 py-1 rounded-full text-gray-700 text-[11px] bg-white shadow-sm"
                    data-range="all">
              All
            </button>
            <button type="button" class="loss-range-btn px-2 py-1 rounded-full text-gray-500 text-[11px]"
                    data-range="month">
              This Month
            </button>
            <button type="button" class="loss-range-btn px-2 py-1 rounded-full text-gray-500 text-[11px]"
                    data-range="year">
              This Year
            </button>
          </div>
        </div>

        <div class="flex items-end gap-2">
          <div>
            <label class="block text-[11px] uppercase tracking-wide text-gray-500">From</label>
            <input type="date" id="lossLocalFrom"
                   class="mt-0.5 rounded-lg border border-gray-200 px-2 py-1 text-xs focus:border-gray-400">
          </div>
          <div>
            <label class="block text-[11px] uppercase tracking-wide text-gray-500">To</label>
            <input type="date" id="lossLocalTo"
                   class="mt-0.5 rounded-lg border border-gray-200 px-2 py-1 text-xs focus:border-gray-400">
          </div>
          <button type="button" id="lossLocalReset"
                  class="mb-0.5 px-2 py-1 rounded-lg border border-gray-200 bg-white text-[11px] text-gray-600 hover:bg-gray-50">
            Reset
          </button>
        </div>

        <div class="ml-auto text-[11px] text-gray-500">
          <span class="hidden md:inline">Base window:</span>
          <span class="font-medium">
            {{ $req->get('from') ?: 'Start' }} → {{ $req->get('to') ?: 'Now' }}
          </span>
        </div>
      </div>

      {{-- Summary --}}
      <div class="flex flex-wrap gap-4 mb-5 text-sm">
        @if($showShrink)
          <div class="flex items-center gap-1 text-amber-700">
            <span id="lossTotalShrink" class="font-semibold">{{ number_format($totalShrink, 1) }}</span>
            <span>L Shrinkage</span>
          </div>
        @endif
        @if($showShort)
          <div class="flex items-center gap-1 text-rose-700">
            <span id="lossTotalShort" class="font-semibold">{{ number_format($totalTruckShort, 1) }}</span>
            <span>L Shortfall</span>
          </div>
        @endif
        <div class="flex items-center gap-1 text-gray-900 font-semibold">
          <span id="lossTotalAll">{{ number_format($totalLoss, 1) }}</span>
          <span>L Total</span>
        </div>
      </div>

      {{-- Table --}}
      <div class="overflow-x-auto rounded-xl border border-gray-100 shadow-sm">
        <table class="min-w-full text-[13px]">
          <thead class="bg-gray-50 border-b border-gray-100 text-gray-600">
            <tr>
              <th class="px-3 py-2 text-left font-medium">Date</th>
              <th class="px-3 py-2 text-left font-medium">Tank / Product</th>
              @if($showPlates)
                <th class="px-3 py-2 text-left font-medium">Plates</th>
              @endif
              @if($showLoaded)
                <th class="px-3 py-2 text-right font-medium">Loaded @20 (L)</th>
              @endif
              @if($showDelivered)
                <th class="px-3 py-2 text-right font-medium">Delivered @20 (L)</th>
              @endif
              @if($showShort)
                <th class="px-3 py-2 text-right font-medium">Shortfall (L)</th>
              @endif
              @if($showShrink)
                <th class="px-3 py-2 text-right font-medium">Allowance (L)</th>
              @endif
              <th class="px-3 py-2 text-right font-medium">Total Loss (L)</th>
            </tr>
          </thead>
          <tbody id="lossTableBody" class="divide-y divide-gray-100">
            @forelse($rows as $row)
              <tr
                data-date="{{ $row['raw_date'] ?? '' }}"
                data-shrink="{{ $row['shrink'] ?? 0 }}"
                data-short="{{ $row['short'] ?? 0 }}"
                data-total="{{ $row['total'] ?? 0 }}"
              >
                <td class="px-3 py-1.5 text-gray-700">{{ $row['date'] }}</td>
                <td class="px-3 py-1.5 text-gray-700">{{ $row['tank'] }} / {{ $row['prod'] }}</td>
                @if($showPlates)
                  <td class="px-3 py-1.5 text-gray-700">{{ $row['plates'] ?: '—' }}</td>
                @endif
                @if($showLoaded)
                  <td class="px-3 py-1.5 text-right text-gray-700">{{ number_format($row['loaded'] ?? 0, 1) }}</td>
                @endif
                @if($showDelivered)
                  <td class="px-3 py-1.5 text-right text-gray-700">{{ number_format($row['delivered'] ?? 0, 1) }}</td>
                @endif
                @if($showShort)
                  <td class="px-3 py-1.5 text-right text-rose-700">{{ number_format($row['short'] ?? 0, 1) }}</td>
                @endif
                @if($showShrink)
                  <td class="px-3 py-1.5 text-right text-amber-700">{{ number_format($row['shrink'] ?? 0, 1) }}</td>
                @endif
                <td class="px-3 py-1.5 text-right font-semibold text-gray-900">
                  {{ number_format($row['total'] ?? 0, 1) }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="{{ 3 + (int)$showPlates + (int)$showLoaded + (int)$showDelivered + (int)$showShort + (int)$showShrink }}"
                    class="px-3 py-4 text-center text-gray-500 text-sm">
                  No data available in this range.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 flex justify-end">
      <button type="button" data-loss-close
              class="rounded-lg bg-gray-800 text-white px-3 py-1.5 text-sm hover:bg-black">
        Close
      </button>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const tbody      = document.getElementById('lossTableBody');
  if (!tbody) return;

  const rows       = Array.from(tbody.querySelectorAll('tr[data-date]'));
  const fromInput  = document.getElementById('lossLocalFrom');
  const toInput    = document.getElementById('lossLocalTo');
  const resetBtn   = document.getElementById('lossLocalReset');
  const rangeBtns  = Array.from(document.querySelectorAll('.loss-range-btn'));

  const elShrink   = document.getElementById('lossTotalShrink');
  const elShort    = document.getElementById('lossTotalShort');
  const elTotal    = document.getElementById('lossTotalAll');

  function applyLossFilter() {
    const from = fromInput?.value || '';
    const to   = toInput?.value   || '';

    let sumShrink = 0, sumShort = 0, sumTotal = 0;

    rows.forEach(row => {
      const d = row.dataset.date || '';
      let visible = true;

      if (from && d < from) visible = false;
      if (to && d > to)     visible = false;

      row.style.display = visible ? '' : 'none';

      if (visible) {
        const shrink = parseFloat(row.dataset.shrink || '0') || 0;
        const short  = parseFloat(row.dataset.short  || '0') || 0;
        const total  = parseFloat(row.dataset.total  || '0') || 0;

        sumShrink += shrink;
        sumShort  += short;
        sumTotal  += total;
      }
    });

    if (elShrink) elShrink.textContent = sumShrink.toFixed(1);
    if (elShort)  elShort.textContent  = sumShort.toFixed(1);
    if (elTotal)  elTotal.textContent  = sumTotal.toFixed(1);
  }

  function setRange(range) {
    // clear active state
    rangeBtns.forEach(b => b.classList.remove('bg-white','shadow-sm','text-gray-700'));
    rangeBtns.forEach(b => b.classList.add('text-gray-500'));

    const btn = rangeBtns.find(b => b.dataset.range === range);
    if (btn) {
      btn.classList.add('bg-white','shadow-sm','text-gray-700');
      btn.classList.remove('text-gray-500');
    }

    const today = new Date();
    const pad = n => String(n).padStart(2,'0');

    if (range === 'all') {
      if (fromInput) fromInput.value = '';
      if (toInput)   toInput.value   = '';
    } else if (range === 'month') {
      const y = today.getFullYear();
      const m = today.getMonth() + 1;
      const first = `${y}-${pad(m)}-01`;
      // cheap last day of month
      const lastDate = new Date(y, m, 0).getDate();
      const last = `${y}-${pad(m)}-${pad(lastDate)}`;
      if (fromInput) fromInput.value = first;
      if (toInput)   toInput.value   = last;
    } else if (range === 'year') {
      const y = today.getFullYear();
      if (fromInput) fromInput.value = `${y}-01-01`;
      if (toInput)   toInput.value   = `${y}-12-31`;
    }

    applyLossFilter();
  }

  rangeBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      setRange(btn.dataset.range || 'all');
    });
  });

  fromInput?.addEventListener('change', () => {
    // manual date change → clear quick-range highlight
    rangeBtns.forEach(b => b.classList.remove('bg-white','shadow-sm','text-gray-700'));
    rangeBtns.forEach(b => b.classList.add('text-gray-500'));
    applyLossFilter();
  });

  toInput?.addEventListener('change', () => {
    rangeBtns.forEach(b => b.classList.remove('bg-white','shadow-sm','text-gray-700'));
    rangeBtns.forEach(b => b.classList.add('text-gray-500'));
    applyLossFilter();
  });

  resetBtn?.addEventListener('click', () => {
    if (fromInput) fromInput.value = '';
    if (toInput)   toInput.value   = '';
    setRange('all');
  });

  // initial state: "All"
  setRange('all');
});
</script>
@endpush