{{-- resources/views/depot-stock/clients/forms/offload.blade.php --}}
<div id="offloadModal"
     class="fixed inset-0 z-[120] hidden"
     data-clearance-list-url="{{ url('/depot/compliance/clearances/linkable') }}"
     data-clearance-preview-url="{{ url('/depot/compliance/clearances') }}/__ID__/link-preview">
  <!-- Backdrop -->
  <button type="button" class="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity" data-offload-close aria-label="Close"></button>

  <!-- Panel -->
  <div class="absolute inset-0 flex items-start justify-center p-4 md:p-8 overflow-y-auto">
    <div class="w-full max-w-3xl bg-white rounded-2xl shadow-2xl border border-gray-100">
      <div class="flex items-center justify-between border-b px-6 py-4 bg-emerald-50/60 rounded-t-2xl">
        <div class="flex items-center gap-3">
          <h3 class="font-semibold text-gray-900 text-lg flex items-center gap-2">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-emerald-600 text-white text-[11px] font-bold">IN</span>
            Offload (Delivered to Depot)
          </h3>

          {{-- Mode badge --}}
          <span id="offloadModeBadge"
                class="hidden items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-800">
            Compliance: TR8 Linked
          </span>
        </div>

        <button type="button" class="text-gray-500 hover:text-gray-800" data-offload-close aria-label="Close">✕</button>
      </div>

      <form id="offloadForm"
            class="p-6 space-y-6 text-sm text-gray-800"
            method="POST"
            action="{{ route('depot.clients.offloads.store', $client) }}"
            data-url="{{ route('depot.clients.offloads.store', $client) }}">
        @csrf
        <input type="hidden" name="client_id" value="{{ $client->id }}">

        {{-- Compliance mode flags (conditional on UI toggle) --}}
        <input type="hidden" id="off_link_mode" name="link_clearance" value="0">
        <input type="hidden" id="off_clearance_id" name="clearance_id" value="">

        {{-- Top-level error banner (matches load’s UX) --}}
        <div id="offloadFormBanner" class="hidden rounded-md border border-red-200 bg-red-50 text-red-700 px-3 py-2 text-xs"></div>

        {{-- Mode Switch (Walk-in vs Link Clearance) --}}
        <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-4">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <div class="text-xs font-semibold uppercase tracking-wide text-gray-600">Offload Mode</div>
              <div class="text-[12px] text-gray-500 mt-0.5">
                Walk-in is default. Link Clearance pulls plates + declared loaded qty and shows TR8 docs.
              </div>
            </div>

            <div class="inline-flex rounded-lg border border-gray-200 bg-white p-1">
              <button type="button"
                      id="btnModeWalkin"
                      class="px-3 py-1.5 rounded-md text-xs font-semibold bg-gray-900 text-white"
                      data-mode="walkin">
                Walk-in
              </button>
              <button type="button"
                      id="btnModeClearance"
                      class="px-3 py-1.5 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-100"
                      data-mode="clearance">
                Link Clearance (TR8)
              </button>
            </div>
          </div>

          {{-- Clearance Mode Panel (hidden until enabled) --}}
          <div id="clearanceModePanel" class="hidden mt-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="font-medium text-gray-700 text-xs uppercase tracking-wide" for="off_clearance_select">
                  Clearance (plates)
                </label>

                <div class="mt-1 flex gap-2">
                  <select id="off_clearance_select"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    <option value="">Loading clearances…</option>
                  </select>

                  <button type="button"
                          id="btnClearanceChange"
                          class="hidden shrink-0 px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold">
                    Change
                  </button>
                </div>

                <p id="clearanceSelectHelp" class="mt-1 text-xs text-gray-500">
                  Select a clearance to auto-fill plates + loaded qty and review TR8 documents.
                </p>
                <p id="clearanceSelectErr" class="hidden text-xs text-red-600 mt-1"></p>
              </div>

              <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3">
                <div class="flex items-center justify-between">
                  <div class="text-xs font-semibold text-emerald-900">Clearance Preview</div>
                  <span id="clearanceStatusPill"
                        class="hidden text-[11px] font-semibold px-2 py-1 rounded-full border border-emerald-200 bg-white text-emerald-800">
                    —
                  </span>
                </div>

                <div class="mt-2 space-y-1 text-[12px] text-gray-700">
                  <div class="flex items-center justify-between">
                    <span class="text-gray-500">Truck</span>
                    <span id="clearancePrevTruck" class="font-semibold text-gray-900">—</span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-gray-500">Trailer</span>
                    <span id="clearancePrevTrailer" class="font-semibold text-gray-900">—</span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-gray-500">Loaded @20</span>
                    <span id="clearancePrevLoaded" class="font-semibold text-gray-900">—</span>
                  </div>
                </div>

                <div id="clearanceEligibility"
                     class="hidden mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                  —
                </div>
              </div>
            </div>

            {{-- Documents --}}
            <div class="mt-4 rounded-xl border border-gray-200 bg-white p-4">
              <div class="flex items-center justify-between">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-700">TR8 Documents</div>
                <span id="docsCount" class="text-[11px] text-gray-500">0 files</span>
              </div>

              <div id="docsEmpty" class="mt-3 text-xs text-gray-500">
                Select a clearance to see its documents here.
              </div>

              <ul id="docsList" class="hidden mt-3 divide-y divide-gray-100"></ul>

              <div id="docsWarn"
                   class="hidden mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                This clearance has no TR8 documents. Upload TR8 in the clearance dossier before linking.
              </div>
            </div>
          </div>

          {{-- Compliance bypass (Walk-in only) --}}
<div id="bypassPanel" class="rounded-xl border border-amber-200 bg-amber-50/60 p-4">
  <div class="flex items-start justify-between gap-3">
    <div>
      <div class="text-xs font-semibold uppercase tracking-wide text-amber-900">Compliance Bypass</div>
      <div class="text-[12px] text-amber-800/80 mt-0.5">
        Optional. If this offload is not linked to a TR8 clearance, record a reason for audit.
      </div>
    </div>
    <span class="text-[11px] font-semibold px-2 py-1 rounded-full border border-amber-200 bg-white text-amber-900">
      Walk-in
    </span>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
    <div>
      <label class="font-medium text-amber-900 text-xs uppercase tracking-wide" for="bypass_reason">
        Bypass reason (optional)
      </label>
      <select id="bypass_reason" name="compliance_bypass_reason"
              class="mt-1 w-full border border-amber-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
        <option value="">Select reason…</option>
        <option value="walk_in_customer">Walk-in customer</option>
        <option value="tr8_not_available">TR8 not available</option>
        <option value="emergency_unloading">Emergency unloading</option>
        <option value="system_issue">System issue</option>
        <option value="other">Other</option>
      </select>
      <p class="err err-offload-compliance_bypass_reason hidden text-xs text-red-600 mt-1"></p>
    </div>

    <div>
      <label class="font-medium text-amber-900 text-xs uppercase tracking-wide" for="bypass_notes">
        Notes (optional)
      </label>
      <input id="bypass_notes" name="compliance_bypass_notes" type="text" maxlength="255"
             class="mt-1 w-full border border-amber-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-amber-400 focus:border-amber-400"
             placeholder="Short explanation (optional)">
      <p class="err err-offload-compliance_bypass_notes hidden text-xs text-red-600 mt-1"></p>
    </div>
  </div>
</div>
        </div>

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

  // Mode UI
  const btnWalkin     = document.getElementById('btnModeWalkin');
  const btnClearance  = document.getElementById('btnModeClearance');
  const modeBadge     = document.getElementById('offloadModeBadge');
  const modePanel     = document.getElementById('clearanceModePanel');
  const linkModeInput = document.getElementById('off_link_mode');
  const clearanceIdEl = document.getElementById('off_clearance_id');

  // Clearance widgets
  const clearanceSelect   = document.getElementById('off_clearance_select');
  const clearanceErr      = document.getElementById('clearanceSelectErr');
  const btnClearanceChange= document.getElementById('btnClearanceChange');
  const statusPill        = document.getElementById('clearanceStatusPill');
  const prevTruck         = document.getElementById('clearancePrevTruck');
  const prevTrailer       = document.getElementById('clearancePrevTrailer');
  const prevLoaded        = document.getElementById('clearancePrevLoaded');
  const eligibilityBox    = document.getElementById('clearanceEligibility');

  const docsCount = document.getElementById('docsCount');
  const docsEmpty = document.getElementById('docsEmpty');
  const docsList  = document.getElementById('docsList');
  const docsWarn  = document.getElementById('docsWarn');

  // Offload inputs to autofill
  const inTruck  = document.getElementById('in_truck');
  const inTrailer= document.getElementById('in_trailer');
  const inLoaded = document.getElementById('in_loaded');

  // open/close (same as Load)
  openBtn?.addEventListener('click', () => modal.classList.remove('hidden'));
  closeEls.forEach(b => b.addEventListener('click', () => modal.classList.add('hidden')));

  // helpers (same style as Load)
  const clearErrors = () => {
    banner.classList.add('hidden'); banner.textContent = '';
    clearanceErr.classList.add('hidden'); clearanceErr.textContent = '';
    eligibilityBox.classList.add('hidden'); eligibilityBox.textContent = '';
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

  // -------------------------------
  // Compliance mode (Link Clearance)
  // -------------------------------
  let clearanceListLoaded = false;

  const setMode = (mode) => {
    const isClearance = mode === 'clearance';

    // toggle button styling
    btnWalkin.classList.toggle('bg-gray-900', !isClearance);
    btnWalkin.classList.toggle('text-white', !isClearance);
    btnWalkin.classList.toggle('text-gray-700', isClearance);
    btnWalkin.classList.toggle('hover:bg-gray-100', isClearance);

    btnClearance.classList.toggle('bg-gray-900', isClearance);
    btnClearance.classList.toggle('text-white', isClearance);
    btnClearance.classList.toggle('text-gray-700', !isClearance);
    btnClearance.classList.toggle('hover:bg-gray-100', !isClearance);

    modePanel.classList.toggle('hidden', !isClearance);
    modeBadge.classList.toggle('hidden', !isClearance);
    linkModeInput.value = isClearance ? '1' : '0';

    if (!isClearance) {
      // reset clearance state (walk-in must be clean)
      unlockClearance();
      clearanceIdEl.value = '';
      statusPill.classList.add('hidden'); statusPill.textContent = '—';
      prevTruck.textContent = '—';
      prevTrailer.textContent = '—';
      prevLoaded.textContent = '—';
      docsCount.textContent = '0 files';
      docsEmpty.classList.remove('hidden');
      docsList.classList.add('hidden'); docsList.innerHTML = '';
      docsWarn.classList.add('hidden');
      eligibilityBox.classList.add('hidden'); eligibilityBox.textContent = '';
      return;
    }

    // load list only once per page session
    if (!clearanceListLoaded) loadLinkableClearances();

    const bypassPanel = document.getElementById('bypassPanel');
  const bypassReason = document.getElementById('bypass_reason');
  const bypassNotes  = document.getElementById('bypass_notes');

  if (isClearance) {
    bypassPanel?.classList.add('hidden');
    if (bypassReason) bypassReason.value = '';
    if (bypassNotes)  bypassNotes.value  = '';
  } else {
    bypassPanel?.classList.remove('hidden');
  }
  };

  const lockClearance = () => {
    clearanceSelect.disabled = true;
    btnClearanceChange.classList.remove('hidden');
  };
  const unlockClearance = () => {
    clearanceSelect.disabled = false;
    btnClearanceChange.classList.add('hidden');
    clearanceSelect.value = '';
    clearanceIdEl.value = '';
  };

  btnWalkin?.addEventListener('click', () => setMode('walkin'));
  btnClearance?.addEventListener('click', () => setMode('clearance'));
  btnClearanceChange?.addEventListener('click', () => {
    unlockClearance();
    // Keep panel visible; just clear preview + docs
    statusPill.classList.add('hidden'); statusPill.textContent = '—';
    prevTruck.textContent = '—';
    prevTrailer.textContent = '—';
    prevLoaded.textContent = '—';
    eligibilityBox.classList.add('hidden'); eligibilityBox.textContent = '';
    docsCount.textContent = '0 files';
    docsEmpty.classList.remove('hidden');
    docsList.classList.add('hidden'); docsList.innerHTML = '';
    docsWarn.classList.add('hidden');
  });

  async function loadLinkableClearances() {
    const listUrl = modal.dataset.clearanceListUrl;
    clearanceSelect.innerHTML = `<option value="">Loading clearances…</option>`;
    clearanceSelect.disabled = true;

    try {
      const url = new URL(listUrl, window.location.origin);
      url.searchParams.set('client_id', '{{ $client->id }}');

      const res = await fetch(url.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      });

      if (!res.ok) throw new Error('Failed to load clearances');

      const data = await res.json();
      const rows = Array.isArray(data) ? data : (data.data || data.rows || []);

      clearanceSelect.innerHTML = `<option value="">Select clearance…</option>`;
      rows.forEach(r => {
        const id = r.id ?? r.clearance_id;
        const truck = r.truck_number ?? r.truck_plate ?? '';
        const trailer = r.trailer_number ?? r.trailer_plate ?? '';
        const label = [truck, trailer].filter(Boolean).join(' • ') || (`Clearance #${id}`);
        const opt = document.createElement('option');
        opt.value = id;
        opt.textContent = label;
        clearanceSelect.appendChild(opt);
      });

      clearanceSelect.disabled = false;
      clearanceListLoaded = true;
    } catch (e) {
      clearanceSelect.innerHTML = `<option value="">Could not load clearances</option>`;
      clearanceSelect.disabled = true;
      clearanceErr.textContent = 'Failed to load clearances for this client.';
      clearanceErr.classList.remove('hidden');
    }
  }

  clearanceSelect?.addEventListener('change', async () => {
    clearErrors();

    const id = clearanceSelect.value;
    if (!id) return;

    // lock selection instantly
    lockClearance();
    clearanceIdEl.value = id;

    // fetch preview
    const tpl = modal.dataset.clearancePreviewUrl;
    const url = tpl.replace('__ID__', encodeURIComponent(id));

    // reset preview UI while loading
    statusPill.classList.add('hidden');
    prevTruck.textContent = '…';
    prevTrailer.textContent = '…';
    prevLoaded.textContent = '…';
    docsCount.textContent = '…';
    docsEmpty.classList.remove('hidden');
    docsList.classList.add('hidden'); docsList.innerHTML = '';
    docsWarn.classList.add('hidden');
    eligibilityBox.classList.add('hidden'); eligibilityBox.textContent = '';

    try {
      const res = await fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      });
      if (!res.ok) throw new Error('Preview failed');

      const p = await res.json();

      // Eligibility message (server can compute can_link + reason)
      if (p.can_link === false) {
        eligibilityBox.textContent = p.reason || 'This clearance cannot be linked.';
        eligibilityBox.classList.remove('hidden');
      }

      // Status pill
      if (p.status) {
        statusPill.textContent = String(p.status).replaceAll('_',' ').toUpperCase();
        statusPill.classList.remove('hidden');
      }

      // Populate preview + form
      const truck = p.truck_number ?? p.truck_plate ?? '';
      const trailer = p.trailer_number ?? p.trailer_plate ?? '';
      const loaded20 = p.loaded_20_l ?? p.loaded_at_20 ?? p.loaded_qty ?? null;

      prevTruck.textContent = truck || '—';
      prevTrailer.textContent = trailer || '—';
      prevLoaded.textContent = (loaded20 !== null && loaded20 !== undefined && loaded20 !== '') ? Number(loaded20).toLocaleString() : '—';

      // Autofill form fields (editable)
      if (truck && inTruck) inTruck.value = truck;
      if (trailer && inTrailer) inTrailer.value = trailer;
      if (loaded20 !== null && loaded20 !== undefined && loaded20 !== '' && inLoaded) {
        inLoaded.value = Number(loaded20).toFixed(3);
        recalc();
      }

      // Docs
      const docs = Array.isArray(p.documents) ? p.documents : [];
      docsCount.textContent = `${docs.length} file${docs.length === 1 ? '' : 's'}`;

      if (!docs.length) {
        docsEmpty.classList.add('hidden');
        docsList.classList.add('hidden');
        docsWarn.classList.remove('hidden');
      } else {
        docsWarn.classList.add('hidden');
        docsEmpty.classList.add('hidden');
        docsList.classList.remove('hidden');
        docsList.innerHTML = '';

        docs.forEach(d => {
          const li = document.createElement('li');
          li.className = 'py-2 flex items-center justify-between gap-3';

          const left = document.createElement('div');
          left.className = 'min-w-0';

          const name = document.createElement('div');
          name.className = 'text-sm font-semibold text-gray-900 truncate';
          name.textContent = d.original_name || d.name || d.filename || 'Document';

          const meta = document.createElement('div');
          meta.className = 'text-xs text-gray-500';
          meta.textContent = (d.type || 'document').toString().replaceAll('_',' ');

          left.appendChild(name);
          left.appendChild(meta);

          const right = document.createElement('div');
          right.className = 'shrink-0 flex items-center gap-2';

          // Prefer server-provided URLs (best)
          const viewUrl = d.open_url || d.url_view || d.url || null;

          const a = document.createElement('a');
          a.className = 'px-3 py-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-xs font-semibold text-gray-700';
          a.textContent = 'View';
          a.target = '_blank';
          a.rel = 'noopener';
          if (viewUrl) a.href = viewUrl;
          else a.href = '#';

          right.appendChild(a);

          li.appendChild(left);
          li.appendChild(right);

          docsList.appendChild(li);
        });
      }
    } catch (e) {
      clearanceErr.textContent = 'Failed to load clearance preview.';
      clearanceErr.classList.remove('hidden');
      unlockClearance();
    }
  });

  // Default mode
  setMode('walkin');

  // --- Submit (identical flow to Load) ---
  form?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    clearErrors();

    // If in clearance mode, require a selected clearance (client-side, soft; server enforces too)
    const isLinking = linkModeInput.value === '1';
    if (isLinking && !clearanceIdEl.value) {
      clearanceErr.textContent = 'Select a clearance to link.';
      clearanceErr.classList.remove('hidden');
      showBanner('Clearance link is enabled. Please select a clearance.');
      return;
    }

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