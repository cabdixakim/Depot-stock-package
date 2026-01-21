@extends('depot-stock::layouts.app')

@section('content')
@php
    use Optima\DepotStock\Models\Clearance;

    $clients    = $clients ?? collect();
    $clearances = $clearances ?? null;

    // Filters
    $fClient = request('client_id');
    $fStatus = request('status');
    $fSearch = request('q');
    $fFrom   = request('from');
    $fTo     = request('to');

    // Roles
    $u = auth()->user();
    $roleNames = $u?->roles?->pluck('name')->map(fn($r) => strtolower($r))->all() ?? [];
    $canCreate = in_array('admin', $roleNames) || in_array('owner', $roleNames) || in_array('compliance', $roleNames);
    $canAct    = $canCreate;

    // Base query for stats (client/search/date filters apply; status varies per-pill)
    $statsBase = Clearance::query();

    if ($fClient) $statsBase->where('client_id', $fClient);

    if ($fSearch) {
        $term = trim($fSearch);
        $statsBase->where(function ($w) use ($term) {
            $w->where('truck_number', 'like', "%{$term}%")
              ->orWhere('trailer_number', 'like', "%{$term}%")
              ->orWhere('tr8_number', 'like', "%{$term}%")
              ->orWhere('invoice_number', 'like', "%{$term}%")
              ->orWhere('delivery_note_number', 'like', "%{$term}%");
        });
    }

    if ($fFrom) $statsBase->whereDate('created_at', '>=', $fFrom);
    if ($fTo)   $statsBase->whereDate('created_at', '<=', $fTo);

    $stats = [
        'total'      => (clone $statsBase)->count(),
        'draft'      => (clone $statsBase)->where('status','draft')->count(),
        'submitted'  => (clone $statsBase)->where('status','submitted')->count(),
        'tr8_issued' => (clone $statsBase)->where('status','tr8_issued')->count(),
        'arrived'    => (clone $statsBase)->where('status','arrived')->count(),
        'cancelled'  => (clone $statsBase)->where('status','cancelled')->count(),
    ];

    // Needs-attention counts (simple v1 logic; you can refine later)
    $attStuckSubmitted = (clone $statsBase)
        ->where('status','submitted')
        ->whereNull('tr8_issued_at')
        ->count();

    $attStuckTr8 = (clone $statsBase)
        ->where('status','tr8_issued')
        ->whereNull('arrived_at')
        ->count();

    $attMissingTr8 = (clone $statsBase)
        ->where('status','tr8_issued')
        ->where(function($w){
            $w->whereNull('tr8_number')->orWhere('tr8_number','');
        })
        ->count();

    // If you track docs properly, swap this query to real missing-doc logic
    $attMissingDocs = 0;

    $attTotal = $attStuckSubmitted + $attStuckTr8 + $attMissingTr8 + $attMissingDocs;

    // Helper URL builder (preserve filters)
    $qsBase = array_filter([
        'client_id' => $fClient,
        'q'         => $fSearch,
        'from'      => $fFrom,
        'to'        => $fTo,
    ], fn($v) => $v !== null && $v !== '');

    $makeFilterUrl = function(array $override = []) use ($qsBase) {
        $qs = array_merge($qsBase, $override);
        $qs = array_filter($qs, fn($v) => $v !== null && $v !== '');
        return url()->current() . (count($qs) ? ('?' . http_build_query($qs)) : '');
    };

    $now = now();
@endphp
@if (session('success'))
<div id="successToast"
     class="fixed top-4 right-4 z-[60] max-w-sm rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-lg">
    <div class="flex items-start gap-3">
        <div class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-600 text-white">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-semibold text-emerald-900">Success</div>
            <div class="text-sm text-emerald-800">{{ session('success') }}</div>
        </div>
        <button type="button"
                class="ml-auto rounded-xl px-2 py-1 text-emerald-900/70 hover:bg-emerald-100"
                onclick="document.getElementById('successToast')?.remove()">
            ✕
        </button>
    </div>
</div>
@endif
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mt-6">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="p-5 sm:p-6">

                {{-- HEADER --}}
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 sm:gap-6">
                    <div class="min-w-0">
                        <div class="text-xs text-gray-500">Compliance</div>
                        <h1 class="mt-1 text-xl font-semibold tracking-tight text-gray-900">Clearances &amp; TR8</h1>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                            <span class="inline-flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                Live
                            </span>
                            <span class="hidden sm:inline">•</span>
                            <span>Refreshed: <span class="font-medium text-gray-700">{{ $now->format('m/d/Y, g:i A') }}</span></span>
                        </div>
                    </div>

                    {{-- Actions area --}}
                    <div class="flex items-center justify-start sm:justify-end gap-2 sm:gap-3 flex-wrap">
                        @if($canCreate)
                            <button
                                type="button"
                                class="h-9 inline-flex items-center gap-2 rounded-xl bg-gray-900 px-3 sm:px-4 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                                id="btnOpenCreateClearance"
                            >
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-white/10 text-base leading-none">+</span>
                                <span class="hidden sm:inline">New clearance</span>
                                <span class="sm:hidden">New</span>
                            </button>
                        @endif

                        {{-- Needs attention (bell icon + badge + responsive panel) --}}
                        <div class="relative" id="attWrap">
                            <button
                                type="button"
                                id="btnAttention"
                                class="group relative h-9 inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-3 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                                aria-haspopup="true"
                                aria-expanded="false"
                                title="Needs attention"
                            >
                                {{-- Bell icon (modern) --}}
                                <svg class="h-5 w-5 text-gray-700 group-hover:text-gray-900" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 22a2.4 2.4 0 0 0 2.35-2H9.65A2.4 2.4 0 0 0 12 22Z" fill="currentColor" opacity=".9"/>
                                    <path d="M20 17H4c1.7-1.4 2.4-3.1 2.4-5.7V9.4C6.4 6.5 8.5 4.2 12 4.2s5.6 2.3 5.6 5.2v1.9c0 2.6.7 4.3 2.4 5.7Z"
                                          stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                                </svg>

                                {{-- Badge pinned like social apps --}}
                                <span class="absolute -top-2 -right-2 inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1.5 text-[11px] font-bold shadow
                                    {{ $attTotal > 0 ? 'bg-rose-600 text-white' : 'bg-gray-200 text-gray-700' }}">
                                    {{ $attTotal }}
                                </span>
                            </button>

                            {{-- ✅ PANEL FIX: mobile uses fixed + left/right clamp; desktop stays dropdown --}}
                            <div
                                id="attentionPanel"
                                class="hidden z-50 rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden
                                       fixed sm:absolute
                                       left-2 right-2 sm:left-auto sm:right-0
                                       top-20 sm:top-full
                                       sm:mt-2
                                       w-auto sm:w-[22rem]
                                       max-h-[calc(100vh-6rem)] overflow-auto"
                            >
                                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                    <div class="text-sm font-semibold text-gray-900">Needs attention</div>
                                    <button type="button" class="rounded-lg px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50" data-att-close="1">
                                        Close
                                    </button>
                                </div>

                                <div class="p-3 text-[12px] text-gray-600">
                                    <div class="grid grid-cols-1 gap-2">
                                        <a href="{{ $makeFilterUrl(['status' => 'submitted', '__att' => 'stuck_submitted']) }}#clearances"
                                           class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-2 hover:bg-gray-50">
                                            <div class="min-w-0">
                                                <div class="text-[13px] font-semibold text-gray-900">Stuck in Submitted</div>
                                                <div class="text-[11px] text-gray-500">Waiting TR8 issuance</div>
                                            </div>
                                            <div class="inline-flex items-center gap-3">
                                                <span class="rounded-full bg-amber-50 px-2 py-1 text-[11px] font-bold text-amber-900 border border-amber-200">{{ $attStuckSubmitted }}</span>
                                                <span class="text-gray-300">›</span>
                                            </div>
                                        </a>

                                        <a href="{{ $makeFilterUrl(['status' => 'tr8_issued', '__att' => 'stuck_tr8_issued']) }}#clearances"
                                           class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-2 hover:bg-gray-50">
                                            <div class="min-w-0">
                                                <div class="text-[13px] font-semibold text-gray-900">TR8 issued, not arrived</div>
                                                <div class="text-[11px] text-gray-500">Chase truck / dispatch</div>
                                            </div>
                                            <div class="inline-flex items-center gap-3">
                                                <span class="rounded-full bg-blue-50 px-2 py-1 text-[11px] font-bold text-blue-900 border border-blue-200">{{ $attStuckTr8 }}</span>
                                                <span class="text-gray-300">›</span>
                                            </div>
                                        </a>

                                        <a href="{{ $makeFilterUrl(['status' => 'tr8_issued', '__att' => 'missing_tr8_number']) }}#clearances"
                                           class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-2 hover:bg-gray-50">
                                            <div class="min-w-0">
                                                <div class="text-[13px] font-semibold text-gray-900">Missing TR8 number</div>
                                                <div class="text-[11px] text-gray-500">Data risk</div>
                                            </div>
                                            <div class="inline-flex items-center gap-3">
                                                <span class="rounded-full bg-rose-50 px-2 py-1 text-[11px] font-bold text-rose-900 border border-rose-200">{{ $attMissingTr8 }}</span>
                                                <span class="text-gray-300">›</span>
                                            </div>
                                        </a>

                                        <a href="{{ $makeFilterUrl(['__att' => 'missing_documents']) }}#clearances"
                                           class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-2 hover:bg-gray-50">
                                            <div class="min-w-0">
                                                <div class="text-[13px] font-semibold text-gray-900">Missing documents</div>
                                                <div class="text-[11px] text-gray-500">Audit risk</div>
                                            </div>
                                            <div class="inline-flex items-center gap-3">
                                                <span class="rounded-full bg-gray-50 px-2 py-1 text-[11px] font-bold text-gray-800 border border-gray-200">{{ $attMissingDocs }}</span>
                                                <span class="text-gray-300">›</span>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- STATUS PILLS --}}
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @php
                        $pill = function($label, $count, $key, $tone) use ($makeFilterUrl) {
                            $href = $makeFilterUrl(['status' => $key]);
                            $base = "inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold";
                            $toneClass = match($tone) {
                                'amber' => "border-amber-200 bg-amber-50 text-amber-900",
                                'blue' => "border-blue-200 bg-blue-50 text-blue-900",
                                'emerald' => "border-emerald-200 bg-emerald-50 text-emerald-900",
                                'rose' => "border-rose-200 bg-rose-50 text-rose-900",
                                default => "border-gray-200 bg-gray-50 text-gray-800",
                            };
                            $countPill = "<span class='inline-flex items-center justify-center rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-bold border border-black/5'>{$count}</span>";
                            return "<a href='{$href}' class='{$base} {$toneClass} hover:shadow-sm transition'><span>{$label}</span>{$countPill}</a>";
                        };
                    @endphp

                    {!! $pill('Total', (int)$stats['total'], '', 'gray') !!}
                    {!! $pill('Draft', (int)$stats['draft'], 'draft', 'gray') !!}
                    {!! $pill('Submitted', (int)$stats['submitted'], 'submitted', 'amber') !!}
                    {!! $pill('TR8 Issued', (int)$stats['tr8_issued'], 'tr8_issued', 'blue') !!}
                    {!! $pill('Arrived', (int)$stats['arrived'], 'arrived', 'emerald') !!}
                    {!! $pill('Cancelled', (int)$stats['cancelled'], 'cancelled', 'rose') !!}
                </div>

<form method="GET" class="mt-4 rounded-2xl border border-gray-200 bg-white p-3">
    <div class="clr-filters">

        {{-- Client --}}
        <div>
            <select name="client_id"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10">
                <option value="">All clients</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" @selected((string)$fClient === (string)$c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Status --}}
        <div>
            <select name="status"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10">
                <option value="">All</option>
                <option value="draft" @selected($fStatus==='draft')>Draft</option>
                <option value="submitted" @selected($fStatus==='submitted')>Submitted</option>
                <option value="tr8_issued" @selected($fStatus==='tr8_issued')>TR8 issued</option>
                <option value="arrived" @selected($fStatus==='arrived')>Arrived</option>
                <option value="cancelled" @selected($fStatus==='cancelled')>Cancelled</option>
            </select>
        </div>

        {{-- Search --}}
        <div>
            <input name="q" value="{{ $fSearch }}" placeholder="Truck, trailer, TR8, invoice…"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10" />
        </div>

        {{-- From --}}
        <div>
            <input type="date" name="from" value="{{ $fFrom }}"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10" />
        </div>

        {{-- To --}}
        <div>
            <input type="date" name="to" value="{{ $fTo }}"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10" />
        </div>

        {{-- Actions --}}
        <div class="clr-filters-actions">
            <a href="{{ url()->current() }}"
               class="h-9 inline-flex items-center rounded-xl border border-gray-200 bg-white px-3 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                Reset
            </a>
            <button type="submit"
                class="h-9 inline-flex items-center rounded-xl bg-gray-900 px-4 text-sm font-semibold text-white hover:bg-gray-800">
                Apply
            </button>
        </div>

    </div>
</form>

                {{-- LIST HEADER + EXPORTS --}}
                <div id="clearances" class="mt-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">Clearances</div>
                        <div class="text-xs text-gray-500">Row click opens record. Actions update workflow.</div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button" class="h-9 inline-flex items-center rounded-xl border border-gray-200 bg-white px-3 text-sm font-semibold text-gray-800 hover:bg-gray-50" id="btnExportXlsx">
                            Export Excel
                        </button>
                        <button type="button" class="h-9 inline-flex items-center rounded-xl border border-gray-200 bg-white px-3 text-sm font-semibold text-gray-800 hover:bg-gray-50" id="btnExportPdf">
                            Export PDF
                        </button>
                    </div>
                </div>

                {{-- TABULATOR --}}
                <div class="mt-3 rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-2 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div class="text-xs text-gray-500">Tip: use filters above + click a row</div>
                        <div class="text-xs text-gray-400">Remote pagination enabled</div>
                    </div>

                    <div class="p-3">
                        <div id="clearancesTable"></div>
                    </div>
                </div>

                @if($clearances && method_exists($clearances, 'links'))
                    <div class="mt-4">
                        {{ $clearances->withQueryString()->links() }}
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>

{{-- Create modal --}}
@if($canCreate)
    @include('depot-stock::compliance.clearances._create_modal', ['clients' => $clients])
@endif

{{-- tr8 modal --}}
@include('depot-stock::compliance.clearances._issue_tr8_modal')

{{-- Confirm modal (generic) --}}
<div id="confirmModal" class="hidden fixed inset-0 z-50 items-center justify-center">
    <div class="absolute inset-0 bg-black/30" data-modal-backdrop="confirm"></div>
    <div class="relative w-[min(32rem,calc(100vw-1rem))] rounded-2xl bg-white shadow-2xl border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <div id="confirmTitle" class="text-sm font-semibold text-gray-900">Confirm</div>
            <button class="rounded-lg px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50" data-modal-close="confirm">Close</button>
        </div>
        <div class="p-4">
            <div id="confirmText" class="text-sm text-gray-700">Are you sure?</div>
            <div class="mt-4 flex items-center justify-end gap-2">
                <button class="h-9 rounded-xl border border-gray-200 bg-white px-3 text-sm font-semibold text-gray-800 hover:bg-gray-50" data-modal-close="confirm">
                    Cancel
                </button>
                <button id="confirmOk" class="h-9 rounded-xl bg-gray-900 px-4 text-sm font-semibold text-white hover:bg-gray-800">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Tabulator polish + compact row height */
    #clearancesTable .tabulator{
        border: 0;
        border-radius: 14px;
        overflow: hidden;
        background: white;
    }
    #clearancesTable .tabulator-header{
        background: #0f172a; /* slate-900 */
        color: white;
        border-bottom: 0;
    }
    #clearancesTable .tabulator-header .tabulator-col{
        background: transparent;
        border-right: 0;
    }
    #clearancesTable .tabulator-col-title{
        font-weight: 700;
        letter-spacing: .04em;
        font-size: 11px;
    }
    #clearancesTable .tabulator-row{
        border-bottom: 1px solid rgba(0,0,0,.04);
        min-height: 40px;
    }
    #clearancesTable .tabulator-cell{
        padding-top: 6px;
        padding-bottom: 6px;
    }
    #clearancesTable .tabulator-row:hover{
        background: rgba(2,6,23,.03);
    }

  /* Filter layout that does NOT depend on Tailwind breakpoints existing */
  .clr-filters {
    display: grid;
    grid-template-columns: 1fr;
    gap: .5rem;
  }

  /* Desktop: single line */
  @media (min-width: 1024px) {
    .clr-filters {
      grid-template-columns: 12rem 9rem 1fr 10rem 10rem auto;
      align-items: end;
    }
  }

  /* Prevent the last actions cell from dropping */
  .clr-filters-actions {
    display: flex;
    justify-content: flex-end;
    gap: .5rem;
    white-space: nowrap;
  }

  /* Make inputs compact and consistent height */
  .clr-filters input,
  .clr-filters select {
    height: 36px;
  }

</style>

@endpush

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function () {
    // -----------------------------
    // Guard: Tabulator must exist
    // -----------------------------
    if (!window.Tabulator) {
        console.error("Tabulator missing on window. Ensure it is loaded in Vite app.js and @stack('scripts') exists in layout.");
        return;
    }

    const csrf    = @json(csrf_token());
    const canAct  = @json($canAct ?? false);
    const baseUrl = @json(url('depot/compliance/clearances'));
    const dataUrl = @json(route('depot.compliance.clearances.data'));

    // -----------------------------
    // Helpers: URLs
    // -----------------------------
    const showUrl   = (id) => `${baseUrl}/${id}`;
    const submitUrl = (id) => `${baseUrl}/${id}/submit`;
    const issueUrl  = (id) => `${baseUrl}/${id}/issue-tr8`;
    const arriveUrl = (id) => `${baseUrl}/${id}/arrive`;
    const cancelUrl = (id) => `${baseUrl}/${id}/cancel`;

    // -----------------------------
    // Helpers: simple modal open/close
    // -----------------------------
    const openModal = (id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.remove("hidden");
        el.classList.add("flex");
        el.setAttribute("aria-hidden", "false");
    };

    const closeModal = (id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add("hidden");
        el.classList.remove("flex");
        el.setAttribute("aria-hidden", "true");
    };

    // Close on backdrop + close buttons (your modals use data-close-modal)
    document.addEventListener("click", (e) => {
        const closer = e.target.closest("[data-close-modal]");
        if (!closer) return;

        const val = closer.getAttribute("data-close-modal");

        // create modal uses "1"
        if (val === "1") closeModal("createClearanceModal");

        // issue tr8 modal uses "issue_tr8"
        if (val === "issue_tr8") closeModal("issueTr8Modal");
    });

    // -----------------------------
    // Needs attention panel (mobile-safe)
    // -----------------------------
    const attBtn   = document.getElementById("btnAttention");
    const attPanel = document.getElementById("attentionPanel");
    const attWrap  = document.getElementById("attWrap");

    function closeAttention() {
        if (!attPanel) return;
        attPanel.classList.add("hidden");
        attBtn?.setAttribute("aria-expanded", "false");
    }

    function openAttention() {
        if (!attPanel) return;

        attPanel.classList.remove("hidden");
        attBtn?.setAttribute("aria-expanded", "true");

        // Desktop only: clamp horizontally if needed (mobile uses fixed left/right from classes)
        const isDesktop = window.matchMedia("(min-width: 640px)").matches;

        if (isDesktop) {
            attPanel.style.left = "";
            attPanel.style.right = "";

            const pad = 8;
            const rect = attPanel.getBoundingClientRect();

            if (rect.right > window.innerWidth - pad) {
                attPanel.style.right = "0";
                attPanel.style.left = "auto";
            }

            const rect2 = attPanel.getBoundingClientRect();
            if (rect2.left < pad) {
                attPanel.style.left = "0";
                attPanel.style.right = "auto";
            }
        } else {
            // Mobile: never pin with inline styles (prevents overflow bugs)
            attPanel.style.left = "";
            attPanel.style.right = "";
        }
    }

    attBtn?.addEventListener("click", () => {
        if (!attPanel) return;
        const isOpen = !attPanel.classList.contains("hidden");
        isOpen ? closeAttention() : openAttention();
    });

    document.addEventListener("click", (e) => {
        if (!attWrap || !attPanel) return;
        if (e.target.closest("[data-att-close]")) { closeAttention(); return; }
        if (!attWrap.contains(e.target)) closeAttention();
    });

    window.addEventListener("resize", () => {
        if (!attPanel) return;
        if (!attPanel.classList.contains("hidden")) openAttention();
    }, { passive: true });

    window.addEventListener("scroll", () => {
        if (!attPanel) return;
        if (!attPanel.classList.contains("hidden")) closeAttention();
    }, { passive: true });

    // Esc closes modals + attention panel
    document.addEventListener("keydown", (e) => {
        if (e.key !== "Escape") return;
        closeModal("createClearanceModal");
        closeModal("issueTr8Modal");
        closeAttention();
    });

    // Open Create modal
    document.getElementById("btnOpenCreateClearance")?.addEventListener("click", () => {
        openModal("createClearanceModal");
    });

    // -----------------------------
    // Issue TR8 modal wiring
    // -----------------------------
    const issueTr8Form   = document.getElementById("issueTr8Form");
    const issueTr8Submit = document.getElementById("issueTr8Submit");
    const issueTr8Action = document.getElementById("issueTr8Action");

    function openIssueTr8Modal(clearanceId) {
        if (!issueTr8Form) {
            console.error("issueTr8Form not found. Ensure the TR8 modal is included in index blade.");
            return;
        }

        const action = issueUrl(clearanceId);
        issueTr8Form.setAttribute("action", action);
        if (issueTr8Action) issueTr8Action.value = action;

        // clear fields for a clean UX
        const num = document.getElementById("issueTr8Number");
        if (num) num.value = "";
        const ref = document.getElementById("issueTr8Reference");
        if (ref) ref.value = "";
        const doc = document.getElementById("issueTr8Document");
        if (doc) doc.value = "";

        openModal("issueTr8Modal");
        setTimeout(() => document.getElementById("issueTr8Number")?.focus(), 50);
    }

    issueTr8Submit?.addEventListener("click", () => {
        if (!issueTr8Form) return;
        issueTr8Form.submit(); // native submit for multipart
    });

    // -----------------------------
    // POST helper (JSON) for actions
    // -----------------------------
    async function postJson(url, payload = {}) {
        const res = await fetch(url, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-TOKEN": csrf,
            },
            body: JSON.stringify(payload),
        });

        if (!res.ok) {
            const text = await res.text();
            throw new Error(text || "Request failed");
        }
        return true;
    }

    // -----------------------------
    // Confirm modal (assumes #confirmModal exists)
    // -----------------------------
    let confirmResolver = null;

    const confirmModal  = document.getElementById("confirmModal");
    const confirmTitle  = document.getElementById("confirmTitle");
    const confirmText   = document.getElementById("confirmText");
    const confirmOk     = document.getElementById("confirmOk");

    function openConfirm({ title = "Confirm", text = "Are you sure?" } = {}) {
        if (!confirmModal) return Promise.resolve(false);
        if (confirmTitle) confirmTitle.textContent = title;
        if (confirmText)  confirmText.textContent  = text;
        confirmModal.classList.remove("hidden");
        confirmModal.classList.add("flex");
        return new Promise((resolve) => (confirmResolver = resolve));
    }

    function closeConfirm(result) {
        if (!confirmModal) return;
        confirmModal.classList.add("hidden");
        confirmModal.classList.remove("flex");
        if (confirmResolver) { confirmResolver(result); confirmResolver = null; }
    }

    confirmOk?.addEventListener("click", () => closeConfirm(true));

    // close confirm on backdrop + cancel buttons
    document.addEventListener("click", (e) => {
        if (e.target.closest('[data-modal-close="confirm"]')) closeConfirm(false);
        if (e.target.closest('[data-modal-backdrop="confirm"]')) closeConfirm(false);
    });

    // -----------------------------
    // Tabulator
    // - Remove rowClick navigation (you asked)
    // - Add subtle "Open" action button
    // -----------------------------
    const rowToneClass = (status) => {
        switch ((status || "").toString()) {
            case "submitted":  return "row-submitted";
            case "tr8_issued": return "row-tr8";
            case "arrived":    return "row-arrived";
            case "cancelled":  return "row-cancelled";
            default:           return "row-draft";
        }
    };

    const table = new Tabulator("#clearancesTable", {
        layout: "fitColumns",
        responsiveLayout: false,
        height: "560px",
        placeholder: "No clearances found for this filter.",
        pagination: true,
        paginationMode: "remote",
        paginationSize: 20,

        ajaxURL: dataUrl,
        ajaxParams: () => Object.fromEntries(new URLSearchParams(window.location.search).entries()),

        ajaxResponse: function (url, params, resp) {
            return {
                data: Array.isArray(resp?.data) ? resp.data : [],
                current_page: resp?.meta?.current_page ?? 1,
                last_page: resp?.meta?.last_page ?? 1,
                per_page: resp?.meta?.per_page ?? 20,
                total: resp?.meta?.total ?? (Array.isArray(resp?.data) ? resp.data.length : 0),
            };
        },

        paginationDataReceived: {
            last_page: "last_page",
            data: "data",
            current_page: "current_page",
            total: "total",
        },

        rowFormatter: function (row) {
            const el = row.getElement();
            const s  = row.getData().status;
            el.classList.add(rowToneClass(s));
        },

        // NOTE: no rowClick navigation now (per your request)

        columns: [
            {
                title: "STATUS",
                field: "status",
                width: 150,
                formatter: (cell) => {
                    const s = (cell.getValue() || "").toString();
                    const label = s.replaceAll("_", " ").toUpperCase();
                    const cls = ({
                        draft: "border-gray-200 bg-gray-50 text-gray-900",
                        submitted: "border-amber-200 bg-amber-50 text-amber-900",
                        tr8_issued: "border-blue-200 bg-blue-50 text-blue-900",
                        arrived: "border-emerald-200 bg-emerald-50 text-emerald-900",
                        cancelled: "border-rose-200 bg-rose-50 text-rose-900",
                    })[s] || "border-gray-200 bg-gray-50 text-gray-900";

                    return `<span class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] font-semibold ${cls}">${label || "-"}</span>`;
                },
            },
            { title: "CLIENT", field: "client_name", minWidth: 180 },
            { title: "TRUCK", field: "truck_number", width: 130 },
            { title: "TRAILER", field: "trailer_number", width: 140 },
            { title: "LOADED @20°C", field: "loaded_20_l", hozAlign: "right", width: 150 },
            { title: "TR8", field: "tr8_number", width: 140 },
            { title: "BORDER", field: "border_point", width: 150 },
            { title: "SUBMITTED", field: "submitted_at", width: 165 },
            { title: "ISSUED", field: "tr8_issued_at", width: 165 },
            { title: "UPDATED BY", field: "updated_by_name", width: 170 },
            { title: "AGE", field: "age_human", width: 120 },

            {
                title: "ACTIONS",
                field: "id",
                headerSort: false,
                width: 380,
                formatter: (cell) => {
                    const d = cell.getRow().getData();
                    const id = d.id;
                    const s = d.status;

                    const btn = (label, action, tone = "gray") => {
                        const toneClass = ({
                            gray: "border-gray-200 bg-white text-gray-800 hover:bg-gray-50",
                            subtle: "border-transparent bg-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900",
                            dark: "border-gray-900 bg-gray-900 text-white hover:bg-gray-800",
                            amber: "border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100",
                            rose: "border-rose-200 bg-rose-50 text-rose-900 hover:bg-rose-100",
                            emerald: "border-emerald-200 bg-emerald-50 text-emerald-900 hover:bg-emerald-100",
                        })[tone] || "border-gray-200 bg-white text-gray-800 hover:bg-gray-50";

                        return `<button type="button"
                                    class="px-2.5 py-1.5 rounded-xl border text-[11px] font-semibold whitespace-nowrap ${toneClass}"
                                    data-action="${action}" data-id="${id}"
                                >${label}</button>`;
                    };

                    let html = `<div class="inline-flex items-center gap-2 whitespace-nowrap">`;

                    // ✅ Always present: Open (subtle)
                    html += btn("Open", "open", "subtle");

                    if (!canAct) {
                        html += `</div>`;
                        return html;
                    }

                    if (s === "draft") {
                        html += btn("Submit", "submit", "dark");
                    }
                    if (s === "submitted") {
                        html += btn("Issue TR8", "issue", "amber");
                        html += btn("Cancel", "cancel", "rose");
                    }
                    if (s === "tr8_issued") {
                        html += btn("Arrived", "arrive", "emerald");
                        html += btn("Cancel", "cancel", "rose");
                    }
                    if (s === "arrived") {
                        html += btn("Cancel", "cancel", "rose");
                    }

                    html += `</div>`;
                    return html;
                },

                cellClick: async (e, cell) => {
                    const btn = e.target.closest("button[data-action][data-id]");
                    if (!btn) return;

                    e.preventDefault();
                    e.stopPropagation();

                    const action = btn.getAttribute("data-action");
                    const id = btn.getAttribute("data-id");

                    try {
                        if (action === "open") {
                            window.location.href = showUrl(id);
                            return;
                        }

                        if (!canAct) return;

                        if (action === "submit") {
                            const ok = await openConfirm({ title: "Submit clearance", text: "Submit this clearance now?" });
                            if (!ok) return;
                            await postJson(submitUrl(id));
                            window.location.reload();
                            return;
                        }

                        if (action === "arrive") {
                            const ok = await openConfirm({ title: "Mark arrived", text: "Mark this clearance as arrived?" });
                            if (!ok) return;
                            await postJson(arriveUrl(id));
                            window.location.reload();
                            return;
                        }

                        if (action === "cancel") {
                            const ok = await openConfirm({ title: "Cancel clearance", text: "Cancel this clearance? This is a workflow action." });
                            if (!ok) return;
                            await postJson(cancelUrl(id));
                            window.location.reload();
                            return;
                        }

                        if (action === "issue") {
                            openIssueTr8Modal(id);
                            return;
                        }
                    } catch (err) {
                        alert(("Action failed:\n\n" + (err?.message || err)).slice(0, 600));
                        console.error(err);
                    }
                },
            },
        ],
    });

    // -----------------------------
    // Exports (client-side)
    // -----------------------------
    document.getElementById("btnExportXlsx")?.addEventListener("click", () => {
        table.download("xlsx", "clearances.xlsx", { sheetName: "Clearances" });
    });

    document.getElementById("btnExportPdf")?.addEventListener("click", () => {
        table.download("pdf", "clearances.pdf", { orientation: "landscape", title: "Clearances" });
    });

    // -----------------------------
    // Attention links: smooth scroll
    // -----------------------------
    if (window.location.hash === "#clearances") {
        const el = document.getElementById("clearances");
        if (el) el.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    // auto-hide toast
    setTimeout(() => document.getElementById('successToast')?.remove(), 3200);
});
</script>
@endpush