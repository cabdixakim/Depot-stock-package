{{-- depot-stock::clients/forms/adjust.blade.php --}}
<div id="adjustModal" class="fixed inset-0 z-[120] hidden">
  {{-- Backdrop --}}
  <button type="button" class="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity" data-adjust-close></button>

  {{-- Panel --}}
  <div class="absolute inset-0 flex items-start justify-center p-4 md:p-8 overflow-y-auto">
    <div class="w-full max-w-3xl bg-white rounded-2xl shadow-2xl border border-gray-100">
      <div class="flex items-center justify-between border-b px-6 py-4 bg-gray-50 rounded-t-2xl">
        <div>
          <div class="text-[11px] uppercase tracking-wide text-gray-500">New</div>
          <h3 class="font-semibold text-gray-900 text-lg">Adjustment</h3>
        </div>
        <button type="button" class="text-gray-500 hover:text-gray-800" data-adjust-close>✕</button>
      </div>

      <form id="adjustForm"
            class="p-6 space-y-6 text-sm text-gray-800"
            data-url="{{ route('depot.clients.adjustments.store', $client) }}">
        @csrf

        {{-- Alert --}}
        <div id="adjustAlert" class="hidden rounded-lg border px-3 py-2 text-sm"></div>

        {{-- Row 1 --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide">Date</label>
            <input type="date" name="date"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                   value="{{ now()->toDateString() }}">
            <p class="err err-adjust-date hidden text-xs text-red-600 mt-1"></p>
          </div>
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide">Tank</label>
            <select name="tank_id"
                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
              <option value="">Select tank…</option>
              @foreach($tanks as $t)
                <option value="{{ $t->id }}">{{ $t->depot->name }} — {{ $t->product->name }} (T#{{ $t->id }})</option>
              @endforeach
            </select>
            <p class="err err-adjust-tank_id hidden text-xs text-red-600 mt-1"></p>
          </div>
        </div>

        {{-- Row 2 --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide">Volume @20°C (L)</label>
            <input id="adj_volume" type="number" step="0.001" name="amount_20_l"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                   placeholder="e.g. -150 for loss, 150 for gain">
            <p class="err err-adjust-amount_20_l hidden text-xs text-red-600 mt-1"></p>
          </div>
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide">Reason / Reference</label>
            <input type="text" name="reference"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                   placeholder="Loss / Gain / Calibration">
            <p class="err err-adjust-reference hidden text-xs text-red-600 mt-1"></p>
          </div>
        </div>

        {{-- Row 3: Plates + Billable toggle --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide">Truck Plate (optional)</label>
            <input type="text" name="truck_plate"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
            <p class="err err-adjust-truck_plate hidden text-xs text-red-600 mt-1"></p>
          </div>
          <div>
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide">Trailer Plate (optional)</label>
            <input type="text" name="trailer_plate"
                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
            <p class="err err-adjust-trailer_plate hidden text-xs text-red-600 mt-1"></p>
          </div>

          {{-- BILLABLE SWITCH --}}
          <div class="sm:pt-6">
            <label class="font-medium text-gray-700 text-xs uppercase tracking-wide">Billable</label>
            <div class="mt-2 flex items-center gap-3">
              <input id="is_billable" name="is_billable" type="hidden" value="0">
              <button type="button" id="billableSwitch"
                      class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors bg-emerald-500 focus:outline-none ring-1 ring-emerald-400/30">
                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow translate-x-5 transition-transform"></span>
              </button>
              <span id="billableLabel" class="text-sm text-emerald-700 font-medium">Yes, include on invoices</span>
            </div>
            <p class="text-[11px] text-gray-500 mt-1">Turn off if this is an internal correction only.</p>
          </div>
        </div>

        {{-- Footer --}}
        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100 mt-4">
          <button type="button" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700" data-adjust-close>Cancel</button>
          <button id="adjustSubmit" type="submit"
                  class="relative px-4 py-2 rounded-lg bg-amber-600 text-white hover:bg-amber-700 shadow">
            <span class="inline-flex items-center gap-2">
              <svg id="adjustSpinner" class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
              <span id="adjustBtnText">Save Adjustment</span>
            </span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
(() => {
  const modal   = document.getElementById('adjustModal');
  const form    = document.getElementById('adjustForm');
  const alertEl = document.getElementById('adjustAlert');
  const btn     = document.getElementById('adjustSubmit');
  const spinner = document.getElementById('adjustSpinner');
  const btnTxt  = document.getElementById('adjustBtnText');

  // Billable switch
  const switchBtn = document.getElementById('billableSwitch');
  const hiddenFld = document.getElementById('is_billable');
  const lbl       = document.getElementById('billableLabel');

  let billable = false; // default ON
  function renderSwitch() {
    if (billable) {
      switchBtn.className = "relative inline-flex h-6 w-11 items-center rounded-full transition-colors bg-emerald-500 focus:outline-none ring-1 ring-emerald-400/30";
      switchBtn.querySelector('span').className = "inline-block h-5 w-5 transform rounded-full bg-white shadow translate-x-5 transition-transform";
      lbl.textContent = "Yes, include on invoices";
      lbl.className = "text-sm text-emerald-700 font-medium";
      hiddenFld.value = "1";
    } else {
      switchBtn.className = "relative inline-flex h-6 w-11 items-center rounded-full transition-colors bg-gray-300 focus:outline-none ring-1 ring-gray-300/30";
      switchBtn.querySelector('span').className = "inline-block h-5 w-5 transform rounded-full bg-white shadow translate-x-1 transition-transform";
      lbl.textContent = "No, do not invoice";
      lbl.className = "text-sm text-gray-600";
      hiddenFld.value = "0";
    }
  }
  renderSwitch();
  switchBtn.addEventListener('click', () => { billable = !billable; renderSwitch(); });

  // Open/close hookup (already wired by your page)
  document.querySelector('[data-open-adjust]')?.addEventListener('click', () => modal.classList.remove('hidden'));
  modal.querySelectorAll('[data-adjust-close]')?.forEach(b => b.addEventListener('click', () => modal.classList.add('hidden')));

  function setSaving(state) {
    btn.disabled = state;
    spinner.classList.toggle('hidden', !state);
    btnTxt.textContent = state ? 'Saving…' : 'Save Adjustment';
  }
  function showAlert(kind, msg) {
    alertEl.className = 'rounded-lg border px-3 py-2 text-sm ' +
      (kind === 'error'
        ? 'bg-rose-50 border-rose-200 text-rose-700'
        : 'bg-emerald-50 border-emerald-200 text-emerald-700');
    alertEl.textContent = msg;
    alertEl.classList.remove('hidden');
  }
  function clearAlert() {
    alertEl.classList.add('hidden');
    alertEl.textContent = '';
  }
  function clearErrors() {
    form.querySelectorAll('.err').forEach(el => { el.textContent = ''; el.classList.add('hidden'); });
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearAlert();
    clearErrors();

    // Soft warning for 0 amount — submit again to bypass
    try {
      const vol = form.querySelector('[name="amount_20_l"]').value.trim();
      if (vol === '' || Number(vol) === 0) {
        showAlert('error', 'Tip: adjustments of 0 L are usually a mistake. If intentional, submit again.');
        return;
      }
    } catch (_) {}

    setSaving(true);

    const url = form.dataset.url;
    const fd  = new FormData(form);
    const token = form.querySelector('input[name="_token"]')?.value || '';

    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
        body: fd,
      });

      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        if (data?.errors) {
          Object.entries(data.errors).forEach(([field, messages]) => {
            const el = form.querySelector(`.err.err-adjust-${field}`);
            if (el) {
              el.textContent = Array.isArray(messages) ? messages[0] : String(messages);
              el.classList.remove('hidden');
            }
          });
          showAlert('error', data.message || 'Please fix the highlighted fields.');
        } else {
          showAlert('error', 'Something went wrong while saving the adjustment.');
        }
        setSaving(false);
        return;
    }

      const data = await res.json();
      if (data?.ok) {
        showAlert('success', data.message || 'Adjustment saved.');
        setTimeout(() => {
          modal.classList.add('hidden');
          window.location.reload();
        }, 250);
      } else {
        showAlert('error', data?.message || 'Unable to save adjustment.');
        setSaving(false);
      }
    } catch (err) {
      showAlert('error', 'Network error. Please try again.');
      setSaving(false);
    }
  });
})();
</script>
@endpush