{{-- resources/views/depot-stock/clients/forms/offload.blade.php --}}
<div id="offloadModal" class="fixed inset-0 z-[120] hidden">
  <!-- Backdrop -->
  <button type="button" class="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity" data-offload-close aria-label="Close"></button>

  <!-- Panel -->
  <div class="absolute inset-0 flex items-start justify-center p-4 md:p-8 overflow-y-auto">
    <div class="w-full max-w-3xl bg-white rounded-2xl shadow-2xl border border-gray-100">
      <div class="flex items-center justify-between border-b px-6 py-4 bg-emerald-50/60 rounded-t-2xl">
        <h3 class="font-semibold text-gray-900 text-lg flex items-center gap-2">
          <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-emerald-600 text-white text-[11px] font-bold">IN</span>
          Offload (Delivered to Depot)
        </h3>
        <button type="button" class="text-gray-500 hover:text-gray-800" data-offload-close aria-label="Close">✕</button>
      </div>

      <form id="offloadForm"
            class="p-6 space-y-6 text-sm text-gray-800"
            method="POST"
            action="{{ route('depot.clients.offloads.store', $client) }}"
            data-url="{{ route('depot.clients.offloads.store', $client) }}">
        @csrf
        <input type="hidden" name="client_id" value="{{ $client->id }}">

        {{-- Top-level error banner (matches load’s UX) --}}
        <div id="offloadFormBanner" class="hidden rounded-md border border-red-200 bg-red-50 text-red-700 px-3 py-2 text-xs"></div>

        {{-- Row 1: Date & Tank --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="off_date">Date</label>
            <input id="off_date" type="date" name="date"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                   value="{{ now()->toDateString() }}">
            <p class="err err-offload-date hidden text-xs text-red-600 mt-1"></p>
          </div>

          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="off_tank">Tank</label>
            <select id="off_tank" name="tank_id"
                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
              <option value="">Select tank…</option>
              @foreach($tanks as $t)
                <option value="{{ $t->id }}">{{ $t->depot->name }} — {{ $t->product->name }} (T#{{ $t->id }})</option>
              @endforeach
            </select>
            <p class="err err-offload-tank_id hidden text-xs text-red-600 mt-1"></p>
          </div>
        </div>

        {{-- Row 2: Observed / CVF / Delivered @20 --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="in_observed">Observed @ meter (L)</label>
            <input id="in_observed" name="delivered_observed_l" type="number" step="0.001"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="38000">
            <p class="err err-offload-delivered_observed_l hidden text-xs text-red-600 mt-1"></p>
          </div>
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="in_cvf">CVF (optional)</label>
            <input id="in_cvf" name="cvf" type="number" step="0.000001"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="0.963125">
          </div>
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="in_delivered">Delivered @20°C (L)</label>
            <input id="in_delivered" name="delivered_20_l" type="number" step="0.001"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="auto or manual">
            <p class="err err-offload-delivered_20_l hidden text-xs text-red-600 mt-1"></p>
          </div>
        </div>

        {{-- Row 3: Temp / Density / Loaded (paperwork) --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="in_temp">Temperature (°C)</label>
            <input id="in_temp" name="temperature_c" type="number" step="0.1"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="25">
            <p class="err err-offload-temperature_c hidden text-xs text-red-600 mt-1"></p>
          </div>
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="in_density">Density (kg/L)</label>
            <input id="in_density" name="density_kg_l" type="number" step="0.0001"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="0.8249">
            <p class="err err-offload-density_kg_l hidden text-xs text-red-600 mt-1"></p>
          </div>
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="in_loaded">Loaded @20 (paperwork)</label>
            <input id="in_loaded" name="loaded_observed_l" type="number" step="0.001"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="38500">
          </div>
        </div>

        {{-- Auto: Shortfall / Allowance / RSV --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="in_short">Shortfall @20 (auto)</label>
            <input id="in_short" name="shortfall_20_l" type="number" step="0.001"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-50" readonly>
          </div>
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="in_allow">Depot allowance @20 (auto)</label>
            <input id="in_allow" name="depot_allowance_20_l" type="number" step="0.001"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-50" readonly>
          </div>
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="in_rsv">RSV / Policy (optional)</label>
            <input id="in_rsv" name="rsv" type="text" maxlength="50"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="0.3% policy">
          </div>
        </div>

        {{-- Plates & Reference --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="in_truck">Truck Plate</label>
            <input id="in_truck" name="truck_plate" type="text" maxlength="50"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="KBH431Z">
          </div>
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="in_trailer">Trailer Plate</label>
            <input id="in_trailer" name="trailer_plate" type="text" maxlength="50"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="KBR315Z">
          </div>
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="in_ref">Reference</label>
            <input id="in_ref" name="reference" type="text" maxlength="100"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Waybill / Batch">
          </div>
        </div>

        <div>
          <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="in_note">Note</label>
          <input id="in_note" name="note" type="text" maxlength="255"
                 class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Optional note">
        </div>

        {{-- Buttons --}}
        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100 mt-4">
          <button type="button" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700" data-offload-close>Cancel</button>
          <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 shadow">Save Offload</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
(() => {
  const modal    = document.getElementById('offloadModal');
  const form     = document.getElementById('offloadForm');
  const openBtn  = document.querySelector('[data-open-offload]');
  const closeEls = modal.querySelectorAll('[data-offload-close]');
  const banner   = document.getElementById('offloadFormBanner');

  // open/close (same as Load)
  openBtn?.addEventListener('click', () => modal.classList.remove('hidden'));
  closeEls.forEach(b => b.addEventListener('click', () => modal.classList.add('hidden')));

  // helpers (same style as Load)
  const clearErrors = () => {
    banner.classList.add('hidden'); banner.textContent = '';
    form.querySelectorAll('.err').forEach(e => { e.textContent=''; e.classList.add('hidden'); });
    form.querySelectorAll('input,select').forEach(el => el.classList.remove('border-red-400','ring-red-300'));
  };
  const showFieldError = (name, msg) => {
    const errEl = form.querySelector(`.err.err-offload-${name}`);
    const input = form.querySelector(`[name="${name}"]`);
    if (errEl) { errEl.textContent = msg; errEl.classList.remove('hidden'); }
    if (input) input.classList.add('border-red-400','ring-red-300');
  };
  const showBanner = (msg) => {
    banner.textContent = msg;
    banner.classList.remove('hidden');
  };

  // --- Auto-calcs (kept from your version) ---
  const fv = (n, d=0)=>{ const v=parseFloat(n?.value); return Number.isFinite(v)?v:d; };
  const $  = (sel, root=form) => root.querySelector(sel);

  const elObs   = $('#in_observed');
  const elTemp  = $('#in_temp');
  const elRho   = $('#in_density');
  const elCvf   = $('#in_cvf');
  const elLoad  = $('#in_loaded');
  const elDel   = $('#in_delivered');
  const elAllow = $('#in_allow');
  const elShort = $('#in_short');

  let manualDelivered = false;
  elDel?.addEventListener('input', () => { manualDelivered = true; recalc(); });

  function estCVF(t,r){
    const k=0.00065, base=0.825, rel = r? (r/base):1, fac = 1 - k*(t-20);
    return Math.max(0.90, Math.min(1.02, rel*fac));
  }
  function recalc(){
    const obs=fv(elObs), t=fv(elTemp), rho=fv(elRho), load=fv(elLoad);
    if(!manualDelivered){
      const cvf = elCvf?.value ? fv(elCvf) : estCVF(t,rho);
      const delivered = obs * cvf;
      if(elDel) elDel.value = delivered ? delivered.toFixed(3) : '';
    }
    const delivered = fv(elDel);
    if(elAllow) elAllow.value = (delivered * 0.003).toFixed(3);
    if(elShort) elShort.value = Math.max(load - delivered, 0).toFixed(3);
  }
  ['input','change'].forEach(evt => [elObs,elTemp,elRho,elCvf,elLoad].forEach(el=>el?.addEventListener(evt,recalc)));
  recalc();

  // --- Submit (identical flow to Load) ---
  form?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    clearErrors();

    // Soft client-side warnings (doesn't block submit)
    const date  = form.querySelector('[name="date"]').value.trim();
    const tank  = form.querySelector('[name="tank_id"]').value.trim();
    const del20 = form.querySelector('[name="delivered_20_l"]').value.trim();
    let warn = [];
    if (!date) warn.push('Date is empty.');
    if (!tank) warn.push('Tank is empty.');
    if (!del20 || Number(del20) <= 0) warn.push('Delivered @20°C should be > 0.');
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

      // Success: close + reload (like Load)
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