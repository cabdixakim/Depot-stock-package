{{-- resources/views/depot-stock/clients/forms/load.blade.php --}}
@php
    // Optional: current available stock for this client in litres @20°C
    // Pass it from the controller as $clientStockL (float) when including this partial.
    $clientStockL = isset($clientStockL) ? (float) $clientStockL : null;
@endphp

<div id="loadModal" class="fixed inset-0 z-[120] hidden">
  <!-- Backdrop -->
  <button type="button"
          class="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity"
          data-load-close></button>

  <!-- Modal panel -->
  <div class="absolute inset-0 flex items-start justify-center p-4 md:p-8 overflow-y-auto">
    <div class="w-full max-w-3xl bg-white rounded-2xl shadow-2xl border border-gray-100">
      <div class="flex items-center justify-between border-b px-6 py-4 bg-gray-50 rounded-t-2xl">
        <div>
          <h3 class="font-semibold text-gray-900 text-lg">🚛 Load (OUT)</h3>
          <p class="mt-1 text-xs text-gray-500">
            Record product leaving <span class="font-medium">{{ $client->name }}</span>.
          </p>
        </div>
        <button type="button" class="text-gray-500 hover:text-gray-800" data-load-close aria-label="Close">✕</button>
      </div>

      <form id="loadForm"
            class="p-6 space-y-6 text-sm text-gray-800"
            method="POST"
            action="{{ route('depot.clients.loads.store', $client) }}"
            data-url="{{ route('depot.clients.loads.store', $client) }}"
            data-client-stock="{{ !is_null($clientStockL) ? $clientStockL : '' }}">
        @csrf
        <input type="hidden" name="client_id" value="{{ $client->id }}">

        {{-- Top-level error banner --}}
        <div id="loadFormBanner"
             class="hidden rounded-md border border-red-200 bg-red-50 text-red-700 px-3 py-2 text-xs"></div>

        {{-- Row 1: Date & Tank --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="load_date">Date</label>
            <input id="load_date" type="date" name="date"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                   value="{{ now()->toDateString() }}">
            <p class="err err-load-date hidden text-xs text-red-600 mt-1"></p>
          </div>
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="load_tank">Tank</label>
            <select id="load_tank" name="tank_id"
                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
              <option value="">Select tank…</option>
              @foreach($tanks as $t)
                <option value="{{ $t->id }}">
                  {{ $t->depot->name }} — {{ $t->product->name }} (T#{{ $t->id }})
                </option>
              @endforeach
            </select>
            <p class="err err-load-tank_id hidden text-xs text-red-600 mt-1"></p>
          </div>
        </div>

        {{-- Row 2: Loaded + stock info & overdraw warning --}}
        <div>
          <div class="flex items-center justify-between gap-2">
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide"
                   for="load_loaded_20_l">
              Loaded @20 °C (L)
            </label>

            @if(!is_null($clientStockL))
              <div class="text-[11px] text-gray-500">
                Available for {{ $client->name }}:
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 font-medium">
                  {{ number_format($clientStockL, 1, '.', ',') }} L
                </span>
              </div>
            @endif
          </div>

          <input id="load_loaded_20_l"
                 type="number"
                 step="0.001"
                 name="loaded_20_l"
                 class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                 placeholder="Enter loaded litres @20">
          <p class="err err-load-loaded_20_l hidden text-xs text-red-600 mt-1"></p>

          {{-- Soft warning when exceeding stock (does NOT block submit) --}}
          <p id="loadOverdrawHint"
             class="hidden mt-1 text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-md px-2 py-1">
            <!-- filled by JS -->
          </p>
        </div>

        {{-- Plates --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="load_truck_plate">Truck Plate</label>
            <input id="load_truck_plate" type="text" name="truck_plate"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                   placeholder="e.g. KBH431Z">
            <p class="err err-load-truck_plate hidden text-xs text-red-600 mt-1"></p>
          </div>
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="load_trailer_plate">Trailer Plate</label>
            <input id="load_trailer_plate" type="text" name="trailer_plate"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                   placeholder="e.g. KBR315Z">
            <p class="err err-load-trailer_plate hidden text-xs text-red-600 mt-1"></p>
          </div>
        </div>

        {{-- Temp & Density --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="load_temperature_c">Temperature (°C)</label>
            <input id="load_temperature_c" type="number" step="0.1" name="temperature_c"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                   value="20">
          </div>
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="load_density_kg_l">Density (kg/L)</label>
            <input id="load_density_kg_l" type="number" step="0.0001" name="density_kg_l"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                   value="0.8200">
          </div>
        </div>

        {{-- Reference & Note --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="load_reference">Reference</label>
            <input id="load_reference" type="text" name="reference"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                   placeholder="DO / Job / Note">
          </div>
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="load_note">Note</label>
            <input id="load_note" type="text" name="note"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                   placeholder="Optional note">
          </div>
        </div>

        {{-- Buttons --}}
        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100 mt-4">
          <button type="button"
                  class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700"
                  data-load-close>
            Cancel
          </button>
          <button type="submit"
                  class="px-4 py-2 rounded-lg bg-sky-600 text-white hover:bg-sky-700 shadow">
            Save Load
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
(() => {
  const modal    = document.getElementById('loadModal');
  const form     = document.getElementById('loadForm');
  const banner   = document.getElementById('loadFormBanner');
  const openBtn  = document.querySelector('[data-open-load]');
  const closeEls = modal.querySelectorAll('[data-load-close]');
  const volInput = form.querySelector('#load_loaded_20_l');
  const overHint = document.getElementById('loadOverdrawHint');

  // Available stock from data attribute (can be empty)
  const rawStock = form.dataset.clientStock || '';
  const clientStock = rawStock === '' ? null : Number(rawStock);

  // open/close
  openBtn?.addEventListener('click', () => modal.classList.remove('hidden'));
  closeEls.forEach(b => b.addEventListener('click', () => modal.classList.add('hidden')));

  // helpers
  const clearErrors = () => {
    banner.classList.add('hidden'); banner.textContent = '';
    form.querySelectorAll('.err').forEach(e => { e.textContent=''; e.classList.add('hidden'); });
    form.querySelectorAll('input,select').forEach(el => el.classList.remove('border-red-400','ring-red-300'));
  };
  const showFieldError = (name, msg) => {
    const errEl = form.querySelector(`.err.err-load-${name}`);
    const input = form.querySelector(`[name="${name}"]`);
    if (errEl) { errEl.textContent = msg; errEl.classList.remove('hidden'); }
    if (input) input.classList.add('border-red-400','ring-red-300');
  };
  const showBanner = (msg) => {
    banner.textContent = msg;
    banner.classList.remove('hidden');
  };

  // Soft overdraw hint (does NOT block submit)
  const updateOverdrawHint = () => {
    if (clientStock === null || isNaN(clientStock)) {
      // nothing to compare
      overHint.classList.add('hidden');
      overHint.textContent = '';
      return;
    }

    const val = Number(volInput.value || 0);
    if (!val || val <= clientStock) {
      overHint.classList.add('hidden');
      overHint.textContent = '';
      return;
    }

    const extra = val - clientStock;
    overHint.textContent =
      'Note: this load exceeds the client’s available stock by ' +
      extra.toLocaleString(undefined, { maximumFractionDigits: 1 }) +
      ' L. You can still save this load if you want to overdraw them.';
    overHint.classList.remove('hidden');
  };

  volInput.addEventListener('input', updateOverdrawHint);

  form?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    clearErrors();
    // keep the soft hint up-to-date when submitting
    updateOverdrawHint();

    // Soft client-side sanity (does NOT block, just warns)
    const date  = form.querySelector('[name="date"]').value.trim();
    const tank  = form.querySelector('[name="tank_id"]').value.trim();
    const vol   = form.querySelector('[name="loaded_20_l"]').value.trim();
    let warn = [];
    if (!date) warn.push('Date is empty.');
    if (!tank) warn.push('Tank is empty.');
    if (!vol || Number(vol) <= 0) warn.push('Loaded @20°C should be > 0.');
    if (warn.length) showBanner(warn.join(' '));

    const url = form.dataset.url || form.action;
    const btn = form.querySelector('button[type="submit"]');
    const original = btn.textContent;
    btn.disabled = true; btn.textContent = 'Saving…';

    try {
      const fd = new FormData(form);
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': fd.get('_token'),
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        body: fd,
        redirect: 'follow'
      });

      if (res.status === 422) {
        const data = await res.json();
        const errs = data.errors || {};
        Object.keys(errs).forEach(k => showFieldError(k, errs[k][0] || 'Invalid'));
        if (data.message) showBanner(data.message);
        btn.disabled = false; btn.textContent = original;
        return;
      }

      if (!res.ok) {
        const text = await res.text();
        showBanner(text || 'Failed to save (server error).');
        btn.disabled = false; btn.textContent = original;
        return;
      }

      modal.classList.add('hidden');
      location.reload();
    } catch (e) {
      showBanner('Network error. Please try again.');
      btn.disabled = false; btn.textContent = original;
    }
  });
})();
</script>
@endpush