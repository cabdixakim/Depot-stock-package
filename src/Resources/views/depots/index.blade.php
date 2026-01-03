@extends('depot-stock::layouts.app')
@section('title','Depots')

@section('content')
@php
    $activeName = $activeDepot?->name;

    // ---- Global depot policy values (safe defaults) ----
    use Optima\DepotStock\Models\DepotPolicy;

    $allowanceRate      = DepotPolicy::getNumeric('allowance_rate', 0.003);          // 0.3%
    $maxStorageDays     = DepotPolicy::getNumeric('max_storage_days', 30);           // idle after 30 days
    $zeroLoadLimit      = DepotPolicy::getNumeric('max_zero_physical_load_litres', 0);
    $unclearedThreshold = DepotPolicy::getNumeric('uncleared_flag_threshold', 200000);

    $policyAction = \Illuminate\Support\Facades\Route::has('depot.policies.save')
        ? route('depot.policies.save')
        : request()->url();
@endphp

<div class="max-w-5xl mx-auto space-y-6">

  {{-- Header row --}}
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-xl font-semibold text-gray-900">Depots</h1>
      <p class="text-sm text-gray-500 mt-1">
        Manage your storage depots and their tanks. Depots stay in the system; you can deactivate them instead of deleting.
      </p>
    </div>
    <div class="flex items-center gap-2">
      <button id="btnDepotPolicies"
              class="rounded-xl border border-gray-200 bg-white/80 text-gray-700 px-3 py-2 text-xs font-medium hover:bg-gray-100 hover:border-gray-300 shadow-sm">
        ⚙ Depot policies
      </button>
      <button id="btnAddDepot"
              class="rounded-xl bg-gray-900 text-white px-4 py-2 text-sm hover:bg-black shadow-sm">
        + Add Depot
      </button>
    </div>
  </div>

  {{-- Active depot filter hint --}}
  <div class="flex flex-wrap items-center gap-3 text-sm">
    @if($activeDepot)
      <span class="inline-flex items-center gap-2 rounded-lg bg-emerald-50 text-emerald-800 px-3 py-1 border border-emerald-100">
        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
        Active filter: <span class="font-medium">{{ $activeDepot->name }}</span>
      </span>
    @else
      <span class="inline-flex items-center gap-2 rounded-lg bg-gray-50 text-gray-700 px-3 py-1 border border-gray-100">
        <span class="h-2 w-2 rounded-full bg-gray-400"></span>
        Active filter: <span class="font-medium">All Depots</span>
      </span>
    @endif
  </div>

  {{-- Depots grid --}}
  <div class="grid gap-4 md:grid-cols-2">
    @forelse($depots as $d)
      @php
        $isActive  = ($d->status ?? 'active') === 'active';
        $tankCount = (int)($d->tanks_count ?? $d->tanks->count());
      @endphp
      <div id="depot-card-{{ $d->id }}"
           class="relative rounded-2xl border border-gray-100 bg-white/90 shadow-sm overflow-hidden">
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-500/60 via-sky-400/60 to-cyan-400/60"></div>

        <div class="p-4 space-y-3">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="inline-flex items-center gap-2">
                <h2 class="text-base font-semibold text-gray-900">
                  {{ $d->name }}
                </h2>
                @if(!$isActive)
                  <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 text-gray-600 px-2 py-0.5 text-[11px]">
                    <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                    Inactive
                  </span>
                @else
                  <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 px-2 py-0.5 text-[11px]">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    Active
                  </span>
                @endif
              </div>
              @if($d->location)
                <div class="mt-1 text-xs text-gray-500 flex items-center gap-1">
                  <svg class="h-3.5 w-3.5 text-gray-400" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C8.7 2 6 4.7 6 8c0 4.2 6 12 6 12s6-7.8 6-12c0-3.3-2.7-6-6-6zm0 8.2c-1.2 0-2.2-1-2.2-2.2S10.8 5.8 12 5.8s2.2 1 2.2 2.2S13.2 10.2 12 10.2z"/>
                  </svg>
                  <span>{{ $d->location }}</span>
                </div>
              @endif
            </div>

            <div class="text-right space-y-1">
              <div class="text-xs text-gray-500">Tanks</div>
              <div class="inline-flex items-center gap-1 rounded-full bg-indigo-50 text-indigo-700 px-2.5 py-1 text-xs font-semibold">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M4 6h16v11H4z"/><path d="M3 5h18v2H3z"/>
                </svg>
                {{ $tankCount }}
              </div>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-dashed border-gray-100 mt-3 pt-3">
            <div class="flex flex-wrap gap-2">
              {{-- Manage tanks opens the tanks modal --}}
              <button type="button"
                      class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 text-indigo-700 px-2.5 py-1.5 text-xs hover:bg-indigo-100"
                      data-manage-tanks
                      data-depot-id="{{ $d->id }}"
                      data-depot-name="{{ $d->name }}">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M4 6h16v11H4z"/><path d="M3 5h18v2H3z"/>
                </svg>
                Manage tanks
              </button>

              {{-- Edit depot --}}
              <button type="button"
                      data-edit-depot
                      data-depot-id="{{ $d->id }}"
                      data-depot-name="{{ $d->name }}"
                      data-depot-location="{{ $d->location }}"
                      class="inline-flex items-center gap-1 rounded-lg bg-gray-100 text-gray-800 px-2.5 py-1.5 text-xs hover:bg-gray-200">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM21.41 6.34c.39-.39.39-1.02 0-1.41l-2.34-2.34a.9959.9959 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                </svg>
                Edit
              </button>
            </div>

            {{-- Toggle status with confirm modal on deactivation --}}
            <form method="POST"
                  action="{{ route('depot.depots.toggleStatus', $d) }}"
                  class="inline"
                  data-toggle-depot>
              @csrf
              <button type="submit"
                      class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs
                             {{ $isActive ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}"
                      @if($isActive)
                        data-confirm-message="Deactivating :name will hide this depot from filters and dashboards. Historical data stays, but you won’t be able to post new movements until you reactivate it. Are you sure?"
                        data-depot-name="{{ $d->name }}"
                      @endif
              >
                @if($isActive)
                  Deactivate
                @else
                  Activate
                @endif
              </button>
            </form>
          </div>
        </div>
      </div>
    @empty
      <div class="col-span-2">
        <div class="rounded-2xl border border-dashed border-gray-200 bg-white/80 p-8 text-center text-gray-500">
          No depots yet. Click <span class="font-semibold">Add Depot</span> to create your first one.
        </div>
      </div>
    @endforelse
  </div>
</div>

{{-- Depot Add/Edit Modal --}}
<div id="depotModal" class="fixed inset-0 z-[120] hidden">
  <button type="button" class="absolute inset-0 bg-black/40" data-close-depot></button>
  <div class="absolute inset-0 flex items-start justify-center p-4 md:p-8 overflow-y-auto">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl border border-gray-100">
      <div class="flex items-center justify-between border-b px-5 py-3 bg-gray-50 rounded-t-2xl">
        <h3 id="depotModalTitle" class="font-semibold text-gray-900">Add Depot</h3>
        <button type="button" class="text-gray-500 hover:text-gray-800" data-close-depot>✕</button>
      </div>

      <form id="depotForm" class="p-5 space-y-4" method="POST" action="{{ route('depot.depots.store') }}">
        @csrf
        <input type="hidden" name="_method" value="POST">

        <div>
          <label class="text-xs text-gray-500">Name</label>
          <input type="text" name="name" required
                 class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <div>
          <label class="text-xs text-gray-500">Location</label>
          <input type="text" name="location"
                 class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        {{-- ✅ Products block (only used on CREATE) --}}
        <div id="depotProductsBlock" class="rounded-2xl border border-indigo-100 bg-indigo-50/40 p-4">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="text-xs uppercase tracking-wide text-indigo-700/80 font-semibold">
                Products in this depot
              </div>
              <p class="text-[12px] text-gray-600 mt-1">
                Type a product and press <span class="font-semibold">Enter</span>.
                If it doesn’t exist, it will be created.
              </p>
            </div>
            <span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-[11px] text-gray-600 border border-indigo-100">
              Optional
            </span>
          </div>

          {{-- Chips --}}
          <div class="mt-3">
            <div id="depotProductChips" class="flex flex-wrap gap-2"></div>

            {{-- Hidden inputs land here --}}
            <div id="depotProductHidden"></div>
          </div>

          {{-- Input + suggestion list --}}
          <div class="mt-3">
            <label class="text-[11px] text-gray-500">Add product</label>
            <div class="relative mt-1">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-indigo-400">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M9 2a7 7 0 1 0 4.9 12l2.6 2.6a1 1 0 0 0 1.4-1.4l-2.6-2.6A7 7 0 0 0 9 2Zm0 2a5 5 0 1 1 0 10A5 5 0 0 1 9 4Z"/>
                </svg>
              </div>

              <input id="depotProductInput"
                     type="text"
                     list="depotProductDatalist"
                     placeholder="e.g. AGO, PMS, Jet A1…"
                     class="w-full rounded-xl border border-indigo-200 bg-white pl-10 pr-3 py-2 text-sm
                            focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              <datalist id="depotProductDatalist">
                @foreach($products as $p)
                  <option value="{{ $p->name }}"></option>
                @endforeach
              </datalist>
            </div>

            <div class="mt-2 flex items-center justify-between">
              <span class="text-[11px] text-gray-500">
                Tip: use short names like <span class="font-semibold">AGO</span>, <span class="font-semibold">PMS</span>.
              </span>
              <button type="button" id="btnClearDepotProducts"
                      class="text-[11px] font-medium text-rose-700 hover:text-rose-800">
                Clear
              </button>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
          <button type="button" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700" data-close-depot>Cancel</button>
          <button id="depotModalSubmit" type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 shadow">
            Save
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Tanks Modal --}}
{{-- (unchanged, keep your existing tanks modal here exactly as-is) --}}
{{-- ... --}}
{{-- Global Depot Policies Modal --}}
{{-- (unchanged, keep as-is) --}}
{{-- ... --}}

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  // ----- Depot modal -----
  const depotModal = document.getElementById('depotModal');
  const depotForm  = document.getElementById('depotForm');
  const depotTitle = document.getElementById('depotModalTitle');
  const depotSubmit= document.getElementById('depotModalSubmit');

  function openDepotModal()  { depotModal.classList.remove('hidden'); }
  function closeDepotModal() { depotModal.classList.add('hidden'); }

  document.querySelectorAll('[data-close-depot]').forEach(b => b.addEventListener('click', closeDepotModal));

  // ✅ Product chips (CREATE mode only)
  const productsBlock = document.getElementById('depotProductsBlock');
  const chipsWrap     = document.getElementById('depotProductChips');
  const hiddenWrap    = document.getElementById('depotProductHidden');
  const productInput  = document.getElementById('depotProductInput');
  const btnClear      = document.getElementById('btnClearDepotProducts');

  const productsIndex = @json($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values());
  const nameToId = new Map(productsIndex.map(p => [String(p.name).trim().toLowerCase(), Number(p.id)]));

  const chosen = new Map(); // key => { type:'existing'|'new', value: id|name, label:name }

  function norm(s) { return String(s || '').trim().replace(/\s+/g,' '); }
  function keyForExisting(id) { return `id:${id}`; }
  function keyForNew(name) { return `new:${name.toLowerCase()}`; }

  function renderChips() {
    if (!chipsWrap || !hiddenWrap) return;

    chipsWrap.innerHTML = '';
    hiddenWrap.innerHTML = '';

    if (chosen.size === 0) {
      chipsWrap.innerHTML = `
        <div class="text-[12px] text-gray-500">
          No products selected yet.
        </div>
      `;
      return;
    }

    for (const [k, item] of chosen.entries()) {
      const chip = document.createElement('span');
      chip.className = "inline-flex items-center gap-1.5 rounded-full bg-white border border-indigo-100 px-2.5 py-1 text-[12px] text-gray-700 shadow-sm";
      chip.innerHTML = `
        <span class="h-1.5 w-1.5 rounded-full ${item.type === 'existing' ? 'bg-emerald-500' : 'bg-indigo-500'}"></span>
        <span class="font-medium">${item.label}</span>
        <button type="button" class="ml-1 text-gray-400 hover:text-gray-700" aria-label="Remove">✕</button>
      `;
      chip.querySelector('button')?.addEventListener('click', () => {
        chosen.delete(k);
        renderChips();
      });
      chipsWrap.appendChild(chip);

      // hidden inputs
      if (item.type === 'existing') {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'product_ids[]';
        input.value = String(item.value);
        hiddenWrap.appendChild(input);
      } else {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'new_products[]';
        input.value = String(item.value);
        hiddenWrap.appendChild(input);
      }
    }
  }

  function addProductFromText(raw) {
    const txt = norm(raw);
    if (!txt) return;

    const lower = txt.toLowerCase();
    const id = nameToId.get(lower);

    if (id) {
      const k = keyForExisting(id);
      if (!chosen.has(k)) {
        chosen.set(k, { type:'existing', value:id, label:txt });
      }
    } else {
      const k = keyForNew(txt);
      if (!chosen.has(k)) {
        chosen.set(k, { type:'new', value:txt, label:txt });
      }
    }

    renderChips();
  }

  function resetDepotProducts() {
    chosen.clear();
    renderChips();
    if (productInput) productInput.value = '';
  }

  productInput?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      addProductFromText(productInput.value);
      productInput.value = '';
    }
  });

  btnClear?.addEventListener('click', resetDepotProducts);

  // When opening ADD
  document.getElementById('btnAddDepot')?.addEventListener('click', () => {
    depotForm.action = "{{ route('depot.depots.store') }}";
    depotForm.querySelector('input[name="_method"]').value = 'POST';
    depotForm.name.value = '';
    depotForm.location.value = '';
    depotTitle.textContent = 'Add Depot';
    depotSubmit.textContent = 'Save';

    // ✅ show product selector in create
    productsBlock?.classList.remove('hidden');
    resetDepotProducts();

    openDepotModal();
  });

  // When opening EDIT (hide product block so we don’t promise update support here)
  document.querySelectorAll('[data-edit-depot]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id   = btn.getAttribute('data-depot-id');
      const name = btn.getAttribute('data-depot-name') || '';
      const loc  = btn.getAttribute('data-depot-location') || '';

      depotForm.action = "{{ route('depot.depots.update', ':id') }}".replace(':id', id);
      depotForm.querySelector('input[name="_method"]').value = 'PATCH';
      depotForm.name.value = name;
      depotForm.location.value = loc;
      depotTitle.textContent = 'Edit Depot';
      depotSubmit.textContent = 'Update';

      // ✅ hide product block in edit mode (keeps behaviour clean)
      productsBlock?.classList.add('hidden');
      resetDepotProducts();

      openDepotModal();
    });
  });

  // ----- Tanks modal -----
  const tankModal         = document.getElementById('tankModal');
  const tankDepotNameLbl  = document.getElementById('tankModalDepotName');
  const tankPanels        = document.querySelectorAll('.tank-panel');

  function openTankModalFor(depotId, depotName) {
    tankPanels.forEach(panel => {
      const pid = panel.getAttribute('data-depot-panel');
      if (pid === depotId) panel.classList.remove('hidden');
      else panel.classList.add('hidden');
    });
    tankDepotNameLbl.textContent = depotName || 'Depot';
    tankModal.classList.remove('hidden');
  }

  function closeTankModal() { tankModal.classList.add('hidden'); }

  document.querySelectorAll('[data-manage-tanks]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id   = btn.getAttribute('data-depot-id');
      const name = btn.getAttribute('data-depot-name') || '';
      openTankModalFor(id, name);
    });
  });

  tankModal?.querySelectorAll('[data-close-tanks]').forEach(b => b.addEventListener('click', closeTankModal));

  // ----- Confirm modal on depot deactivation -----
  document.querySelectorAll('form[data-toggle-depot]').forEach(form => {
    form.addEventListener('submit', async (e) => {
      const btn = form.querySelector('button[data-confirm-message]');
      if (!btn || typeof window.askConfirm !== 'function') return;

      e.preventDefault();

      const messageTemplate = btn.getAttribute('data-confirm-message') || '';
      const depotName       = btn.getAttribute('data-depot-name') || 'this depot';
      const message         = messageTemplate.replace(':name', depotName);

      const ok = await window.askConfirm({
        heading: 'Deactivate depot?',
        message,
        okText: 'Yes, deactivate',
        cancelText: 'Cancel'
      });

      if (ok) form.submit();
    });
  });

  // ----- Policies modal -----
  const policyModal   = document.getElementById('policyModal');
  const btnPolicies   = document.getElementById('btnDepotPolicies');

  function openPolicyModal() { policyModal?.classList.remove('hidden'); }
  function closePolicyModal() { policyModal?.classList.add('hidden'); }

  btnPolicies?.addEventListener('click', openPolicyModal);
  policyModal?.querySelectorAll('[data-close-policy]').forEach(b => b.addEventListener('click', closePolicyModal));

  // initial chips state (empty)
  renderChips();
});
</script>
@endpush