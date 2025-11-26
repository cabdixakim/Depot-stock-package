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
                  <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M7 6h10v2H7zM7 11h10v2H7zM7 16h10v2H7z"/>
                  </svg>
                  Deactivate
                @else
                  <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Zm1 15h-2v-2h2Zm0-4h-2V7h2Z"/>
                  </svg>
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

{{-- Tanks Modal (per depot, inside this page) --}}
<div id="tankModal" class="fixed inset-0 z-[125] hidden">
  <button type="button" class="absolute inset-0 bg-black/40" data-close-tanks></button>
  <div class="absolute inset-0 flex items-start justify-center p-4 md:p-8 overflow-y-auto">
    <div class="w-full max-w-4xl bg-white rounded-2xl shadow-2xl border border-gray-100">
      <div class="flex items-center justify-between border-b px-6 py-4 bg-gray-50 rounded-t-2xl">
        <div>
          <h3 class="font-semibold text-gray-900">Depot Tanks</h3>
          <p class="text-xs text-gray-500 mt-0.5">
            Depot: <span id="tankModalDepotName" class="font-medium text-gray-800">—</span>
          </p>
        </div>
        <button type="button" class="text-gray-500 hover:text-gray-800" data-close-tanks>✕</button>
      </div>

      <div class="p-6 space-y-6">
        {{-- Panels for each depot, one visible at a time --}}
        <div id="tankModalPanels" class="space-y-6">
          @foreach($depots as $d)
            <div class="tank-panel hidden" data-depot-panel="{{ $d->id }}">
              {{-- Create tank --}}
              <div class="rounded-xl border border-gray-100 bg-gray-50/80 p-4">
                <h4 class="text-sm font-semibold text-gray-800 mb-3">Add tank</h4>
                <form method="POST"
                      action="{{ route('depot.tanks.store') }}"
                      class="grid gap-3 md:grid-cols-[2fr,1fr,1fr,2fr,auto] items-end"
                      enctype="multipart/form-data">
                  @csrf
                  <input type="hidden" name="depot_id" value="{{ $d->id }}">
                  <div>
                    <label class="text-[11px] text-gray-500">Name</label>
                    <input type="text" name="name" required
                           class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                  </div>
                  <div>
                    <label class="text-[11px] text-gray-500">Product</label>
                    <select name="product_id"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-2 text-sm">
                      <option value="">— None —</option>
                      @foreach($products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div>
                    <label class="text-[11px] text-gray-500">Capacity (L)</label>
                    <input type="number" name="capacity_l" min="0" step="0.001" required
                           class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                  </div>
                  <div>
                    <label class="text-[11px] text-gray-500 flex items-center gap-1">
                      Strapping chart
                      <span class="text-[10px] text-gray-400">(CSV only)</span>
                    </label>
                    <input type="file"
                           name="strapping_chart"
                           accept=".csv"
                           class="mt-1 block w-full text-[11px] text-gray-600 file:mr-3 file:rounded-lg file:border file:border-gray-200 file:bg-white file:px-2.5 file:py-1.5 file:text-xs file:font-medium file:text-gray-700 hover:file:bg-gray-50">
                    <p class="mt-1 text-[10px] text-gray-400">
                      Optional. Upload a CSV with columns <code>height_cm,volume_l</code> to convert dip height to litres.
                    </p>
                  </div>
                  <div class="flex justify-end">
                    <button type="submit"
                            class="inline-flex items-center gap-1 rounded-xl bg-gray-900 text-white px-3 py-2 text-sm hover:bg-black shadow">
                      Save
                    </button>
                  </div>
                </form>
              </div>

              {{-- Existing tanks --}}
              <div class="space-y-2">
                <h4 class="text-sm font-semibold text-gray-800">Existing tanks</h4>

                @forelse($d->tanks as $t)
                  @php
                    $tActive = ($t->status ?? 'active') === 'active';
                  @endphp
                  <div class="rounded-xl border border-gray-100 bg-white/90 px-3 py-2.5 flex flex-wrap items-start gap-3">
                    <form method="POST"
                          action="{{ route('depot.tanks.update', $t) }}"
                          class="flex-1 grid gap-2 md:grid-cols-[2fr,1fr,1fr,2fr] items-center"
                          enctype="multipart/form-data">
                      @csrf
                      @method('PATCH')
                      <div>
                        <input type="text" name="name" value="{{ $t->name }}"
                               class="w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500">
                      </div>
                      <div>
                        <select name="product_id"
                                class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs">
                          <option value="">— None —</option>
                          @foreach($products as $p)
                            <option value="{{ $p->id }}" @selected($p->id == $t->product_id)>
                              {{ $p->name }}
                            </option>
                          @endforeach
                        </select>
                      </div>
                      <div>
                        <input type="number" name="capacity_l" min="0" step="0.001"
                               value="{{ $t->capacity_l }}"
                               class="w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500">
                      </div>
                      <div class="space-y-1">
                        @if($t->strapping_chart_path)
                          <div class="text-[11px] text-gray-500">
                            Chart: <span class="font-medium">
                              {{ basename($t->strapping_chart_path) }}
                            </span>
                          </div>
                        @else
                          <div class="text-[11px] text-gray-400">
                            No strapping chart attached
                          </div>
                        @endif
                        <input type="file"
                               name="strapping_chart"
                               accept=".csv"
                               class="block w-full text-[11px] text-gray-600 file:mr-3 file:rounded-lg file:border file:border-gray-200 file:bg-white file:px-2 file:py-1 file:text-[11px] file:font-medium file:text-gray-700 hover:file:bg-gray-50">
                        <p class="text-[10px] text-gray-400">
                          Upload a new CSV to replace the current chart (optional).
                        </p>
                      </div>

                      <div class="hidden md:block"></div>

                      <div class="mt-2 flex items-center gap-2 md:col-span-4 md:justify-end">
                        <button type="submit"
                                class="inline-flex items-center gap-1 rounded-lg bg-gray-100 text-gray-700 px-2.5 py-1.5 text-xs hover:bg-gray-200">
                          Save
                        </button>

                        <form method="POST" action="{{ route('depot.tanks.toggleStatus', $t) }}">
                          @csrf
                          <button type="submit"
                                  class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs
                                         {{ $tActive ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                            @if($tActive)
                              Deactivate
                            @else
                              Activate
                            @endif
                          </button>
                        </form>
                      </div>
                    </form>
                  </div>
                @empty
                  <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50/70 px-4 py-3 text-xs text-gray-500">
                    No tanks yet for this depot.
                  </div>
                @endforelse
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Global Depot Policies Modal --}}
<div id="policyModal" class="fixed inset-0 z-[130] hidden">
  <button type="button" class="absolute inset-0 bg-black/40 backdrop-blur-sm" data-close-policy></button>
  <div class="absolute inset-0 flex items-start justify-center p-4 md:p-8 overflow-y-auto">
    <div class="w-full max-w-xl bg-white/95 rounded-2xl shadow-2xl border border-gray-100 mt-10">
      <div class="flex items-center justify-between px-5 py-3 border-b bg-gray-50/90 rounded-t-2xl">
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
            Global depot policies
          </div>
          <div class="text-sm font-semibold text-gray-900">
            Stock, allowance &amp; risk rules
          </div>
        </div>
        <button type="button" class="text-gray-500 hover:text-gray-800" data-close-policy>✕</button>
      </div>

      <form method="POST" action="{{ $policyAction }}" class="p-5 space-y-4 text-sm">
        @csrf
        <div class="grid gap-4 md:grid-cols-2">
          {{-- Allowance rate --}}
          <label class="space-y-1">
            <span class="block text-[11px] uppercase tracking-wide text-gray-500">
              Allowance rate on offloads
            </span>
            <input
              type="number"
              step="0.0001"
              name="allowance_rate"
              value="{{ old('allowance_rate', $allowanceRate) }}"
              class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              placeholder="0.003 = 0.3%">
            <span class="block text-[11px] text-gray-400">
              Used when depot allowance litres aren’t typed. Example: <code>0.003</code> = 0.3% shrinkage.
            </span>
          </label>

          {{-- Max storage days --}}
          <label class="space-y-1">
            <span class="block text-[11px] uppercase tracking-wide text-gray-500">
              Max storage days before stock is idle
            </span>
            <input
              type="number"
              step="1"
              min="0"
              name="max_storage_days"
              value="{{ old('max_storage_days', $maxStorageDays) }}"
              class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              placeholder="30">
            <span class="block text-[11px] text-gray-400">
              Offloads older than this are treated as <strong>idle stock</strong> (after allowance &amp; loads).
            </span>
          </label>

          {{-- Zero-stock load allowance --}}
          <label class="space-y-1">
            <span class="block text-[11px] uppercase tracking-wide text-gray-500">
              Max litres that can load when physical stock is zero / negative
            </span>
            <input
              type="number"
              step="0.001"
              min="0"
              name="max_zero_physical_load_litres"
              value="{{ old('max_zero_physical_load_litres', $zeroLoadLimit) }}"
              class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              placeholder="0">
            <span class="block text-[11px] text-gray-400">
              Safety band for loading “ahead of payments”. Above this, loads should be blocked when stock is zero.
            </span>
          </label>

          {{-- Uncleared threshold --}}
          <label class="space-y-1">
            <span class="block text-[11px] uppercase tracking-wide text-gray-500">
              Uncleared stock alert threshold
            </span>
            <input
              type="number"
              step="0.001"
              min="0"
              name="uncleared_flag_threshold"
              value="{{ old('uncleared_flag_threshold', $unclearedThreshold) }}"
              class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              placeholder="200000">
            <span class="block text-[11px] text-gray-400">
              Above this, Client Risk marks the client as <strong>Attention needed</strong> for uncleared stock.
            </span>
          </label>
          {{-- Default dip litres per cm --}}
      <label class="space-y-1">
        <span class="block text-[11px] uppercase tracking-wide text-gray-500">
          Default dip volume per cm (L/cm)
        </span>
        <input
          type="number"
          step="0.001"
          min="0"
          name="default_dip_litres_per_cm"
          value="{{ old('default_dip_litres_per_cm', \Optima\DepotStock\Models\DepotPolicy::getNumeric('default_dip_litres_per_cm', 350)) }}"
          class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          placeholder="350">
        <span class="block text-[11px] text-gray-400">
          Fallback used when no strapping chart is attached to the tank. Example: <code>350</code> means 350&nbsp;L per cm of dip height.
        </span>
      </label>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
          <button type="button"
                  class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm hover:bg-gray-200"
                  data-close-policy>
            Cancel
          </button>
          <button type="submit"
                  class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-medium hover:bg-indigo-700 shadow">
            Save policies
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

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

  document.getElementById('btnAddDepot')?.addEventListener('click', () => {
    depotForm.action = "{{ route('depot.depots.store') }}";
    depotForm.querySelector('input[name="_method"]').value = 'POST';
    depotForm.name.value = '';
    depotForm.location.value = '';
    depotTitle.textContent = 'Add Depot';
    depotSubmit.textContent = 'Save';
    openDepotModal();
  });

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
      if (pid === depotId) {
        panel.classList.remove('hidden');
      } else {
        panel.classList.add('hidden');
      }
    });
    tankDepotNameLbl.textContent = depotName || 'Depot';
    tankModal.classList.remove('hidden');
  }

  function closeTankModal() {
    tankModal.classList.add('hidden');
  }

  document.querySelectorAll('[data-manage-tanks]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id   = btn.getAttribute('data-depot-id');
      const name = btn.getAttribute('data-depot-name') || '';
      openTankModalFor(id, name);
    });
  });

  tankModal.querySelectorAll('[data-close-tanks]').forEach(b => {
    b.addEventListener('click', closeTankModal);
  });

  // ----- Confirm modal on depot deactivation -----
  document.querySelectorAll('form[data-toggle-depot]').forEach(form => {
    form.addEventListener('submit', async (e) => {
      const btn = form.querySelector('button[data-confirm-message]');
      if (!btn || typeof window.askConfirm !== 'function') {
        return;
      }

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

  function openPolicyModal() {
    if (!policyModal) return;
    policyModal.classList.remove('hidden');
  }
  function closePolicyModal() {
    if (!policyModal) return;
    policyModal.classList.add('hidden');
  }

  btnPolicies?.addEventListener('click', openPolicyModal);
  policyModal?.querySelectorAll('[data-close-policy]').forEach(b => {
    b.addEventListener('click', closePolicyModal);
  });
});
</script>
@endpush