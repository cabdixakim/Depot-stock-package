@extends('depot-stock::layouts.app')

@section('content')
@php
    use Optima\DepotStock\Models\Clearance;
    use Illuminate\Support\Facades\DB;

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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mt-6">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="p-5 sm:p-6">

                {{-- HEADER --}}
                <div class="flex items-start justify-between gap-6">
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
                    <div class="flex items-center gap-8">
                        @if($canCreate)
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                                id="btnOpenCreateClearance"
                            >
                                <span class="text-base leading-none">+</span>
                                <span class="hidden sm:inline">New</span>
                            </button>
                        @endif

                        {{-- Needs attention (icon button + badge + responsive panel) --}}
                        <div class="relative" id="attWrap">
                            <button
                                type="button"
                                id="btnAttention"
                                class="group relative inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-3 py-2 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                                aria-haspopup="true"
                                aria-expanded="false"
                                title="Needs attention"
                            >
                                {{-- “Warning/queue” icon (better than bell for ops) --}}
                                <svg class="h-5 w-5 text-gray-700 group-hover:text-gray-900" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 8v5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                    <path d="M12 17h.01" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>
                                    <path d="M10.3 4.9 3.8 16.2a2 2 0 0 0 1.7 3h13a2 2 0 0 0 1.7-3L13.7 4.9a2 2 0 0 0-3.4 0Z"
                                          stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                </svg>

                                {{-- ALWAYS show badge container (so it doesn’t “vanish” visually) --}}
                                <span class="absolute -top-2 -right-2 inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1.5 text-[11px] font-bold shadow
                                    {{ $attTotal > 0 ? 'bg-rose-600 text-white' : 'bg-gray-200 text-gray-700' }}">
                                    {{ $attTotal }}
                                </span>
                            </button>

                            {{-- PANEL: 3x wider + responsive + never off-screen --}}
                            <div
                                id="attentionPanel"
                                class="hidden absolute right-0 mt-2 z-40 rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden
                                       w-[min(48rem,calc(100vw-1rem))]"
                                style="max-width: calc(100vw - 1rem);"
                            >
                                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                    <div class="text-sm font-semibold text-gray-900">Needs attention</div>
                                    <button type="button" class="rounded-lg px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50" data-att-close="1">
                                        Close
                                    </button>
                                </div>

                                <div class="p-3 text-[12px] text-gray-600">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <a href="{{ $makeFilterUrl(['status' => 'submitted', '__att' => 'stuck_submitted']) }}#clearances"
                                           class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-3 hover:bg-gray-50">
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-gray-900">Stuck in Submitted</div>
                                                <div class="text-xs text-gray-500">Waiting TR8 issuance</div>
                                            </div>
                                            <div class="inline-flex items-center gap-3">
                                                <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-bold text-amber-900 border border-amber-200">{{ $attStuckSubmitted }}</span>
                                                <span class="text-gray-300">›</span>
                                            </div>
                                        </a>

                                        <a href="{{ $makeFilterUrl(['status' => 'tr8_issued', '__att' => 'stuck_tr8_issued']) }}#clearances"
                                           class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-3 hover:bg-gray-50">
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-gray-900">TR8 issued, not arrived</div>
                                                <div class="text-xs text-gray-500">Chase truck / dispatch</div>
                                            </div>
                                            <div class="inline-flex items-center gap-3">
                                                <span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-bold text-blue-900 border border-blue-200">{{ $attStuckTr8 }}</span>
                                                <span class="text-gray-300">›</span>
                                            </div>
                                        </a>

                                        <a href="{{ $makeFilterUrl(['status' => 'tr8_issued', '__att' => 'missing_tr8_number']) }}#clearances"
                                           class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-3 hover:bg-gray-50">
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-gray-900">Missing TR8 number</div>
                                                <div class="text-xs text-gray-500">Data risk</div>
                                            </div>
                                            <div class="inline-flex items-center gap-3">
                                                <span class="rounded-full bg-rose-50 px-2 py-1 text-xs font-bold text-rose-900 border border-rose-200">{{ $attMissingTr8 }}</span>
                                                <span class="text-gray-300">›</span>
                                            </div>
                                        </a>

                                        <a href="{{ $makeFilterUrl(['__att' => 'missing_documents']) }}#clearances"
                                           class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-3 hover:bg-gray-50">
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-gray-900">Missing documents</div>
                                                <div class="text-xs text-gray-500">Audit risk</div>
                                            </div>
                                            <div class="inline-flex items-center gap-3">
                                                <span class="rounded-full bg-gray-50 px-2 py-1 text-xs font-bold text-gray-800 border border-gray-200">{{ $attMissingDocs }}</span>
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

                {{-- FILTERS: ONE LINE ON DESKTOP, STACK ON MOBILE --}}
                <form method="GET" class="mt-4 rounded-2xl border border-gray-200 bg-white p-3">
                    <div class="flex flex-col gap-2 lg:flex-row lg:flex-wrap lg:items-end lg:gap-2">
                        <div class="w-full lg:w-[14rem]">
                            <label class="text-[11px] font-semibold text-gray-600">Client</label>
                            <select name="client_id" class="mt-1 h-9 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10">
                                <option value="">All</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}" @selected((string)$fClient === (string)$c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="w-full lg:w-[10rem]">
                            <label class="text-[11px] font-semibold text-gray-600">Status</label>
                            <select name="status" class="mt-1 h-9 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10">
                                <option value="">All</option>
                                <option value="draft" @selected($fStatus==='draft')>Draft</option>
                                <option value="submitted" @selected($fStatus==='submitted')>Submitted</option>
                                <option value="tr8_issued" @selected($fStatus==='tr8_issued')>TR8 issued</option>
                                <option value="arrived" @selected($fStatus==='arrived')>Arrived</option>
                                <option value="cancelled" @selected($fStatus==='cancelled')>Cancelled</option>
                            </select>
                        </div>

                        <div class="w-full lg:flex-1 lg:min-w-[16rem]">
                            <label class="text-[11px] font-semibold text-gray-600">Search</label>
                            <input name="q" value="{{ $fSearch }}" placeholder="Truck, trailer, TR8, invoice…" class="mt-1 h-9 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"/>
                        </div>

                        <div class="w-full sm:flex sm:gap-2 lg:w-auto lg:flex lg:gap-2">
                            <div class="flex-1 lg:w-[10.5rem]">
                                <label class="text-[11px] font-semibold text-gray-600">From</label>
                                <input type="date" name="from" value="{{ $fFrom }}" class="mt-1 h-9 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"/>
                            </div>
                            <div class="flex-1 lg:w-[10.5rem]">
                                <label class="text-[11px] font-semibold text-gray-600">To</label>
                                <input type="date" name="to" value="{{ $fTo }}" class="mt-1 h-9 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"/>
                            </div>
                        </div>

                        <div class="w-full lg:w-auto lg:ml-auto flex items-center justify-end gap-2 pt-1">
                            <a href="{{ url()->current() }}" class="h-9 inline-flex items-center rounded-xl border border-gray-200 bg-white px-3 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                                Reset
                            </a>
                            <button type="submit" class="h-9 inline-flex items-center rounded-xl bg-gray-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">
                                Apply
                            </button>
                        </div>
                    </div>
                </form>

                {{-- LIST HEADER + EXPORTS --}}
                <div id="clearances" class="mt-5 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">Clearances</div>
                        <div class="text-xs text-gray-500">Row click opens record. Actions update workflow.</div>
                    </div>

                    <div class="flex items-center gap-2">
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
                    <div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between">
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
    /* Tabulator polish */
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
    }
    #clearancesTable .tabulator-row:hover{
        background: rgba(2,6,23,.03);
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function(){
    // --- Guards
    if (!window.Tabulator) {
        console.error("Tabulator missing on window. Ensure it is loaded in Vite app.js and @stack('scripts') exists in layout.");
        return;
    }

    const csrf = @json(csrf_token());
    const canAct = @json($canAct);
    const baseUrl = @json(url('depot/compliance/clearances'));
    const dataUrl = @json(route('depot.compliance.clearances.data'));

    // --- Modal helpers (close on backdrop / esc)
    const openFlex = (id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('hidden');
        el.classList.add('flex');
    };
    const closeModal = (id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
        el.classList.remove('flex');
    };

    document.addEventListener('click', (e) => {
        const bd = e.target.closest('[data-modal-backdrop]');
        if (bd) {
            closeModal(bd.getAttribute('data-modal-backdrop') === 'confirm' ? 'confirmModal' : bd.getAttribute('data-modal-backdrop'));
        }
        const cl = e.target.closest('[data-modal-close]');
        if (cl) {
            const which = cl.getAttribute('data-modal-close');
            if (which === 'confirm') closeModal('confirmModal');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal('confirmModal');
            // create modal id is inside include; if yours is "createClearanceModal" this will close it too:
            const cm = document.getElementById('createClearanceModal');
            if (cm && !cm.classList.contains('hidden')) closeModal('createClearanceModal');
            // attention panel:
            closeAttention();
        }
    });

    // --- Confirm modal API
    let confirmResolver = null;
    function confirmUI({title="Confirm", text="Are you sure?"}){
        document.getElementById('confirmTitle').textContent = title;
        document.getElementById('confirmText').textContent = text;
        openFlex('confirmModal');
        return new Promise((resolve) => { confirmResolver = resolve; });
    }
    document.getElementById('confirmOk').addEventListener('click', () => {
        closeModal('confirmModal');
        if (confirmResolver) { confirmResolver(true); confirmResolver = null; }
    });
    document.querySelectorAll('[data-modal-close="confirm"]').forEach(btn => {
        btn.addEventListener('click', () => {
            closeModal('confirmModal');
            if (confirmResolver) { confirmResolver(false); confirmResolver = null; }
        });
    });

    // --- Attention panel toggle (responsive)
    const attBtn = document.getElementById("btnAttention");
    const attPanel = document.getElementById("attentionPanel");
    const attWrap = document.getElementById("attWrap");

    function closeAttention(){
        if (!attPanel) return;
        attPanel.classList.add("hidden");
        attBtn?.setAttribute("aria-expanded","false");
    }
    function openAttention(){
        if (!attPanel) return;
        attPanel.classList.remove("hidden");
        attBtn?.setAttribute("aria-expanded","true");

        // Ensure it never falls off screen left
        const rect = attPanel.getBoundingClientRect();
        if (rect.left < 8) {
            attPanel.style.left = '0.5rem';
            attPanel.style.right = 'auto';
        } else {
            attPanel.style.left = '';
            attPanel.style.right = '0';
        }
    }

    attBtn?.addEventListener("click", function(){
        if (!attPanel) return;
        const isOpen = !attPanel.classList.contains("hidden");
        isOpen ? closeAttention() : openAttention();
    });

    document.addEventListener("click", function(e){
        if (!attWrap || !attPanel) return;
        if (e.target.closest("[data-att-close]")) { closeAttention(); return; }
        if (!attWrap.contains(e.target)) closeAttention();
    });

    // --- URL builders (so actions NEVER depend on server-provided urls)
    const showUrl   = (id) => `${baseUrl}/${id}`;
    const submitUrl = (id) => `${baseUrl}/${id}/submit`;
    const issueUrl  = (id) => `${baseUrl}/${id}/issue-tr8`;
    const arriveUrl = (id) => `${baseUrl}/${id}/arrive`;
    const cancelUrl = (id) => `${baseUrl}/${id}/cancel`;

    // --- POST helper
    async function postJson(url, payload={}){
        const res = await fetch(url, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-TOKEN": csrf
            },
            body: JSON.stringify(payload)
        });
        if (!res.ok) {
            const text = await res.text();
            throw new Error(text || "Request failed");
        }
        return true;
    }

    // --- Tabulator setup (REMOTE pagination + reshape response)
    const table = new Tabulator("#clearancesTable", {
        layout: "fitColumns",
        responsiveLayout: "collapse",
        height: "560px",
        placeholder: "No clearances found for this filter.",
        pagination: true,
        paginationMode: "remote",
        paginationSize: 20,
        ajaxURL: dataUrl,
        ajaxParams: () => Object.fromEntries(new URLSearchParams(window.location.search).entries()),

        // THIS is the key fix: reshape your controller response to Tabulator’s expected shape
        ajaxResponse: function(url, params, resp){
            // resp is: {data: [...], meta: {...}}
            const shaped = {
                data: Array.isArray(resp?.data) ? resp.data : [],
                current_page: resp?.meta?.current_page ?? 1,
                last_page: resp?.meta?.last_page ?? 1,
                per_page: resp?.meta?.per_page ?? 20,
                total: resp?.meta?.total ?? (Array.isArray(resp?.data) ? resp.data.length : 0),
            };
            console.log("Tabulator shaped response:", shaped);
            return shaped;
        },

        paginationDataReceived: {
            "last_page":"last_page",
            "data":"data",
            "current_page":"current_page",
            "total":"total"
        },

        rowClick: function(e, row){
            // Click on action buttons shouldn't navigate
            if (e.target.closest("button")) return;
            const id = row.getData().id;
            if (id) window.location.href = showUrl(id);
        },

        columns: [
            {
                title: "STATUS",
                field: "status",
                width: 160,
                formatter: (cell) => {
                    const s = (cell.getValue() || "").toString();
                    const label = s.replaceAll("_"," ").toUpperCase();
                    const cls = ({
                        draft: "border-gray-200 bg-gray-50 text-gray-900",
                        submitted: "border-amber-200 bg-amber-50 text-amber-900",
                        tr8_issued: "border-blue-200 bg-blue-50 text-blue-900",
                        arrived: "border-emerald-200 bg-emerald-50 text-emerald-900",
                        cancelled: "border-rose-200 bg-rose-50 text-rose-900",
                    })[s] || "border-gray-200 bg-gray-50 text-gray-900";
                    return `<span class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-semibold ${cls}">${label || '-'}</span>`;
                }
            },
            { title: "CLIENT", field: "client_name", minWidth: 180 },
            { title: "TRUCK", field: "truck_number", width: 140 },
            { title: "TRAILER", field: "trailer_number", width: 150 },
            { title: "LOADED @20°C", field: "loaded_20_l", hozAlign:"right", width: 150 },
            { title: "TR8", field: "tr8_number", width: 140 },
            { title: "BORDER", field: "border_point", width: 150 },
            { title: "SUBMITTED", field: "submitted_at", width: 170 },
            { title: "ISSUED", field: "tr8_issued_at", width: 170 },
            { title: "UPDATED BY", field: "updated_by_name", width: 170 },
            { title: "AGE", field: "age_human", width: 120 },
            {
                title: "ACTIONS",
                field: "id",
                headerSort: false,
                minWidth: 320,
                formatter: (cell) => {
                    const data = cell.getRow().getData();
                    const id = data.id;
                    const s  = data.status;

                    const btn = (label, action, tone="gray") => {
                        const toneClass = ({
                            gray: "border-gray-200 hover:bg-gray-50 text-gray-800",
                            dark: "border-gray-900 bg-gray-900 text-white hover:bg-gray-800",
                            amber:"border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100",
                            blue: "border-blue-200 bg-blue-50 text-blue-900 hover:bg-blue-100",
                            rose: "border-rose-200 bg-rose-50 text-rose-900 hover:bg-rose-100",
                            emerald:"border-emerald-200 bg-emerald-50 text-emerald-900 hover:bg-emerald-100",
                        })[tone] || "border-gray-200 hover:bg-gray-50 text-gray-800";

                        return `<button type="button" class="px-3 py-1.5 rounded-xl border text-xs font-semibold ${toneClass}" data-action="${action}" data-id="${id}">${label}</button>`;
                    };

                    if (!canAct) return `<span class="text-xs text-gray-400">No actions</span>`;

                    let html = `<div class="flex flex-wrap items-center gap-2">`;

                    if (s === 'draft') html += btn("Submit", "submit", "dark");
                    if (s === 'submitted') {
                        html += btn("Issue TR8", "issue", "amber");
                        html += btn("Cancel", "cancel", "rose");
                    }
                    if (s === 'tr8_issued') {
                        html += btn("Mark arrived", "arrive", "emerald");
                        html += btn("Cancel", "cancel", "rose");
                    }
                    if (s === 'arrived') {
                        html += btn("Cancel", "cancel", "rose");
                    }

                    html += `</div>`;
                    return html;
                }
            },
        ],
    });

    // --- Action handler (with confirm modal)
    document.addEventListener("click", async function(e){
        const btn = e.target.closest("button[data-action][data-id]");
        if (!btn) return;

        const action = btn.getAttribute("data-action");
        const id = btn.getAttribute("data-id");

        try {
            if (action === "submit") {
                const ok = await confirmUI({title:"Submit clearance", text:"Submit this clearance now?"});
                if (!ok) return;
                await postJson(submitUrl(id));
                window.location.reload();
            }

            if (action === "arrive") {
                const ok = await confirmUI({title:"Mark arrived", text:"Mark this clearance as arrived?"});
                if (!ok) return;
                await postJson(arriveUrl(id));
                window.location.reload();
            }

            if (action === "cancel") {
                const ok = await confirmUI({title:"Cancel clearance", text:"Cancel this clearance? This is a workflow action."});
                if (!ok) return;
                await postJson(cancelUrl(id));
                window.location.reload();
            }

            if (action === "issue") {
                // v1: prompt (you said later you want a real modal with file upload)
                const tr8 = prompt("Enter TR8 number:");
                if (!tr8) return;
                await postJson(issueUrl(id), { tr8_number: tr8 });
                window.location.reload();
            }
        } catch(err) {
            alert(("Action failed:\n\n" + (err?.message || err)).slice(0, 500));
            console.error(err);
        }
    });

    // --- Exports (client-side on current page only; remote pagination means it exports current loaded set)
    document.getElementById("btnExportXlsx")?.addEventListener("click", () => {
        table.download("xlsx", "clearances.xlsx", {sheetName:"Clearances"});
    });
    document.getElementById("btnExportPdf")?.addEventListener("click", () => {
        table.download("pdf", "clearances.pdf", {orientation:"landscape", title:"Clearances"});
    });

    // --- Create modal open (your include uses createClearanceModal)
    document.getElementById("btnOpenCreateClearance")?.addEventListener("click", () => {
        const modal = document.getElementById("createClearanceModal");
        if (!modal) return;
        modal.classList.remove("hidden");
        modal.classList.add("flex");
    });

    // --- Click outside close for create modal (if it has a backdrop)
    document.addEventListener('click', (e) => {
        const cm = document.getElementById("createClearanceModal");
        if (!cm || cm.classList.contains("hidden")) return;
        if (e.target === cm) { // if modal wrapper is backdrop
            cm.classList.add("hidden");
            cm.classList.remove("flex");
        }
    });

    // When coming from attention links, smooth scroll
    if (window.location.hash === "#clearances") {
        const el = document.getElementById("clearances");
        if (el) el.scrollIntoView({behavior:"smooth", block:"start"});
    }
});
</script>
@endpush