@extends('depot-stock::layouts.app')

@section('content')
@php
    // -------------------------------
    // Safety defaults (avoid undefined)
    // -------------------------------
    $clients   = $clients ?? collect();
    $clearances = $clearances ?? null;

    $stats = $stats ?? [
        'total' => 0,
        'draft' => 0,
        'submitted' => 0,
        'tr8_issued' => 0,
        'arrived' => 0,
        'cancelled' => 0,
        'stuck_submitted' => 0,
        'stuck_tr8_issued' => 0,
        'missing_tr8_number' => 0,
        'missing_documents' => 0,
    ];

    // -------------------------------
    // Role gating (MATCH app.blade.php style)
    // -------------------------------
    $u = auth()->user();
    $roleNames = $u?->roles?->pluck('name')->map(fn($r) => strtolower($r))->all() ?? [];
    $canCreate = in_array('admin', $roleNames) || in_array('owner', $roleNames) || in_array('compliance', $roleNames);
    $canAct    = $canCreate;

    // -------------------------------
    // Current filters (server-side GET)
    // -------------------------------
    $fClient = request('client_id');
    $fStatus = request('status');
    $fSearch = request('q');
    $fFrom   = request('from');
    $fTo     = request('to');
    $fAttention = request('attention');

    // -------------------------------
    // Dataset (current page only)
    // -------------------------------
    $items = [];
    if ($clearances && method_exists($clearances, 'items')) {
        $items = $clearances->items();
    } elseif (is_iterable($clearances)) {
        $items = $clearances;
    }

    $statusMeta = [
        'draft'     => ['label' => 'Draft'],
        'submitted' => ['label' => 'Submitted'],
        'tr8_issued'=> ['label' => 'TR8 Issued'],
        'arrived'   => ['label' => 'Arrived'],
        'cancelled' => ['label' => 'Cancelled'],
    ];

    $now = now();

    // Total attention badge count (ops-style; no double-count logic yet)
    $attentionTotal =
        (int)($stats['stuck_submitted'] ?? 0) +
        (int)($stats['stuck_tr8_issued'] ?? 0) +
        (int)($stats['missing_tr8_number'] ?? 0) +
        (int)($stats['missing_documents'] ?? 0);
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="p-5 sm:p-6">
            {{-- Header row --}}
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-xs font-medium text-gray-500">Compliance</div>
                    <h1 class="mt-1 text-xl sm:text-2xl font-semibold tracking-tight text-gray-900">
                        Clearances &amp; TR8
                    </h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Compact operations view: keep it moving, chase what’s stuck, and offload clean.
                    </p>

                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                        <span class="inline-flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Live
                        </span>
                        <span class="hidden sm:inline">•</span>
                        <span>Refreshed: <span class="font-medium text-gray-700">{{ $now->format('m/d/Y, g:i:s A') }}</span></span>
                        <span class="hidden sm:inline">•</span>
                        <span><span class="font-medium text-gray-700">{{ count($items) }}</span> row(s) on this page</span>
                    </div>
                </div>

                {{-- Actions (tight + ops) --}}
                <div class="flex items-center gap-2 shrink-0">
                    @if($canCreate)
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                            id="btnOpenCreateClearance"
                        >
                            <span class="text-base leading-none">+</span>
                            New
                        </button>
                    @endif

                    {{-- Attention button (far right, anchored popover) --}}
                    <div class="relative">
                        <button
                            type="button"
                            id="btnAttention"
                            class="relative inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                            aria-haspopup="true"
                            aria-expanded="false"
                        >
                            {{-- alert icon --}}
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <path d="M12 9v4"/>
                                <path d="M12 17h.01"/>
                            </svg>

                            @if($attentionTotal > 0)
                                <span class="absolute -top-1.5 -right-1.5 inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-rose-600 px-1.5 text-[11px] font-bold text-white">
                                    {{ $attentionTotal }}
                                </span>
                            @endif
                        </button>

                        {{-- Popover --}}
                        <div
                            id="attentionPopover"
                            class="hidden absolute right-0 mt-2 w-[340px] max-w-[90vw] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl"
                            role="menu"
                            aria-label="Needs attention"
                        >
                            <div class="px-4 py-3 border-b border-gray-100">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900">Needs attention</div>
                                        <div class="mt-0.5 text-xs text-gray-500">
                                            Submitted &gt; 24h • TR8 issued &gt; 24h (not arrived)
                                        </div>
                                    </div>
                                    <button type="button" class="rounded-lg px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50" data-close-attention="1">
                                        Close
                                    </button>
                                </div>
                            </div>

                            <div class="max-h-[360px] overflow-auto p-2">
                                @php
                                    // Helper to build URL that scrolls to list after reload
                                    $baseParams = array_filter([
                                        'client_id' => $fClient,
                                        'q' => $fSearch,
                                        'from' => $fFrom,
                                        'to' => $fTo,
                                    ], fn($v) => $v !== null && $v !== '');

                                    // Map attention items to filter intent (status is a good baseline now)
                                    $attentionItems = [
                                        [
                                            'key' => 'stuck_submitted',
                                            'label' => 'Stuck in Submitted',
                                            'hint' => 'Chase border/agent',
                                            'count' => (int)($stats['stuck_submitted'] ?? 0),
                                            'params' => array_merge($baseParams, ['attention' => 'stuck_submitted', 'status' => 'submitted']),
                                        ],
                                        [
                                            'key' => 'stuck_tr8_issued',
                                            'label' => 'TR8 issued, not arrived',
                                            'hint' => 'Chase dispatch/truck',
                                            'count' => (int)($stats['stuck_tr8_issued'] ?? 0),
                                            'params' => array_merge($baseParams, ['attention' => 'stuck_tr8_issued', 'status' => 'tr8_issued']),
                                        ],
                                        [
                                            'key' => 'missing_tr8_number',
                                            'label' => 'Missing TR8 number',
                                            'hint' => 'Fix data risk',
                                            'count' => (int)($stats['missing_tr8_number'] ?? 0),
                                            'params' => array_merge($baseParams, ['attention' => 'missing_tr8_number', 'status' => 'submitted']),
                                        ],
                                        [
                                            'key' => 'missing_documents',
                                            'label' => 'Missing documents',
                                            'hint' => 'Upload for audit',
                                            'count' => (int)($stats['missing_documents'] ?? 0),
                                            'params' => array_merge($baseParams, ['attention' => 'missing_documents']),
                                        ],
                                    ];
                                @endphp

                                @if($attentionTotal <= 0)
                                    <div class="p-4 text-sm text-gray-600">
                                        <div class="font-semibold text-gray-900">All clear.</div>
                                        <div class="mt-1 text-xs text-gray-500">No items currently need attention.</div>
                                    </div>
                                @else
                                    @foreach($attentionItems as $it)
                                        @php
                                            $href = url()->current() . '?' . http_build_query($it['params']) . '#clearances-list';
                                            $disabled = $it['count'] <= 0;
                                        @endphp

                                        <a href="{{ $href }}"
                                           class="group flex items-center justify-between rounded-xl px-3 py-3 hover:bg-gray-50 {{ $disabled ? 'opacity-50 pointer-events-none' : '' }}"
                                           data-attention-link="1"
                                        >
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-gray-900 group-hover:text-gray-950">
                                                    {{ $it['label'] }}
                                                </div>
                                                <div class="mt-0.5 text-xs text-gray-500">
                                                    {{ $it['hint'] }}
                                                </div>
                                            </div>

                                            <span class="ml-3 inline-flex items-center justify-center rounded-full bg-gray-900/5 px-2 py-1 text-xs font-bold text-gray-900">
                                                {{ $it['count'] }}
                                            </span>
                                        </a>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Compact stats chips (dashboard-only, not clickable) --}}
            <div class="mt-5 flex flex-wrap gap-2">
                @php
                    $chip = function($label, $value, $tone='gray') {
                        $cls = match($tone){
                            'amber' => 'border-amber-200 bg-amber-50 text-amber-900',
                            'blue' => 'border-blue-200 bg-blue-50 text-blue-900',
                            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                            'rose' => 'border-rose-200 bg-rose-50 text-rose-900',
                            default => 'border-gray-200 bg-gray-50 text-gray-900',
                        };

                        return <<<HTML
                        <div class="inline-flex items-center gap-2 rounded-full border {$cls} px-3 py-1.5 text-xs font-semibold">
                            <span class="opacity-80">{$label}</span>
                            <span class="font-bold">{$value}</span>
                        </div>
                        HTML;
                    };
                @endphp

                {!! $chip('Total', (int)($stats['total'] ?? 0)) !!}
                {!! $chip('Draft', (int)($stats['draft'] ?? 0)) !!}
                {!! $chip('Submitted', (int)($stats['submitted'] ?? 0), 'amber') !!}
                {!! $chip('TR8 Issued', (int)($stats['tr8_issued'] ?? 0), 'blue') !!}
                {!! $chip('Arrived', (int)($stats['arrived'] ?? 0), 'emerald') !!}
                {!! $chip('Cancelled', (int)($stats['cancelled'] ?? 0), 'rose') !!}
            </div>

            {{-- Filters (tight ops bar) --}}
            <form method="GET" class="mt-5 rounded-2xl border border-gray-200 bg-white p-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                    <div class="lg:col-span-3">
                        <label class="text-xs font-medium text-gray-700">Client</label>
                        <select name="client_id"
                                class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10">
                            <option value="">All clients</option>
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}" @selected((string)$fClient === (string)$c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="text-xs font-medium text-gray-700">Status</label>
                        <select name="status"
                                class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10">
                            <option value="">All</option>
                            <option value="draft" @selected($fStatus==='draft')>Draft</option>
                            <option value="submitted" @selected($fStatus==='submitted')>Submitted</option>
                            <option value="tr8_issued" @selected($fStatus==='tr8_issued')>TR8 issued</option>
                            <option value="arrived" @selected($fStatus==='arrived')>Arrived</option>
                            <option value="cancelled" @selected($fStatus==='cancelled')>Cancelled</option>
                        </select>
                    </div>

                    <div class="lg:col-span-4">
                        <label class="text-xs font-medium text-gray-700">Search</label>
                        <input name="q"
                               value="{{ $fSearch }}"
                               placeholder="Truck, trailer, TR8, invoice, border…"
                               class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"/>
                    </div>

                    <div class="lg:col-span-3 grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-medium text-gray-700">From</label>
                            <input name="from"
                                   value="{{ $fFrom }}"
                                   placeholder="YYYY-MM-DD"
                                   class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"/>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-700">To</label>
                            <input name="to"
                                   value="{{ $fTo }}"
                                   placeholder="YYYY-MM-DD"
                                   class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"/>
                        </div>
                    </div>

                    {{-- preserve attention param if present --}}
                    @if($fAttention)
                        <input type="hidden" name="attention" value="{{ $fAttention }}">
                    @endif

                    <div class="lg:col-span-12 flex justify-end gap-2 pt-1">
                        <a href="{{ url()->current() }}"
                           class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                            Reset
                        </a>
                        <button type="submit"
                                class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">
                            Apply
                        </button>
                    </div>
                </div>
            </form>

            {{-- List header + exports (ONLY HERE now) --}}
            <div id="clearances-list" class="mt-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-sm font-semibold text-gray-900">Clearances</div>
                    <div class="text-xs text-gray-600">Table is the workspace. Use actions for workflow.</div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button"
                            class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50"
                            id="btnExportXlsx">
                        Export Excel
                    </button>
                    <button type="button"
                            class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50"
                            id="btnExportPdf">
                        Export PDF
                    </button>
                </div>
            </div>

            {{-- Table container --}}
            <div class="mt-3 rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="p-3 border-b border-gray-100 flex items-center justify-between">
                    <div class="text-xs text-gray-500">
                        Showing <span class="font-semibold text-gray-700">{{ count($items) }}</span> row(s) on this page
                    </div>
                    <div class="text-xs text-gray-500">
                        Tip: click row to open • use Actions buttons for status updates
                    </div>
                </div>

                <div class="p-3">
                    <div id="clearancesTable"></div>
                </div>
            </div>

            {{-- Pagination --}}
            @if($clearances && method_exists($clearances, 'links'))
                <div class="mt-4">
                    {{ $clearances->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Create modal --}}
@if($canCreate)
    @include('depot-stock::compliance.clearances._create_modal', ['clients' => $clients])
@endif

@php
    // Build JSON rows for Tabulator
    $rows = [];
    foreach ($items as $c) {
        $status = $c->status ?? 'draft';
        $label = $statusMeta[$status]['label'] ?? ucfirst($status);

        $rows[] = [
            'id' => $c->id,
            'status' => $status,
            'status_label' => $label,
            'client' => $c->client->name ?? '-',
            'truck' => $c->truck_number ?? '-',
            'trailer' => $c->trailer_number ?? '-',
            'loaded_20_l' => $c->loaded_20_l ?? null,
            'tr8_number' => $c->tr8_number ?? null,
            'border_point' => $c->border_point ?? null,
            'invoice_number' => $c->invoice_number ?? null,
            'submitted_at' => optional($c->submitted_at ?? null)?->toDateTimeString(),
            'tr8_issued_at' => optional($c->tr8_issued_at ?? null)?->toDateTimeString(),
            'updated_by' => $c->updatedBy->name ?? ($c->updated_by_name ?? null),
            'updated_at' => optional($c->updated_at ?? null)?->toDateTimeString(),

            'urls' => [
                'show'   => route('depot.compliance.clearances.show', $c),
                'submit' => route('depot.compliance.clearances.submit', $c),
                'issue_tr8' => route('depot.compliance.clearances.issue_tr8', $c),
                'arrive' => route('depot.compliance.clearances.arrive', $c),
                'cancel' => route('depot.compliance.clearances.cancel', $c),
            ],
        ];
    }
@endphp
@endsection

@push('scripts')
<script>
(function(){
    const csrf = @json(csrf_token());
    const canAct = @json($canAct);
    const rows = @json($rows);

    // -------------------------
    // Attention popover (always works)
    // -------------------------
    const btnAttention = document.getElementById("btnAttention");
    const popover = document.getElementById("attentionPopover");

    function closeAttention(){
        if (!popover) return;
        popover.classList.add("hidden");
        btnAttention?.setAttribute("aria-expanded", "false");
    }
    function openAttention(){
        if (!popover) return;
        popover.classList.remove("hidden");
        btnAttention?.setAttribute("aria-expanded", "true");
    }
    function toggleAttention(){
        if (!popover) return;
        popover.classList.contains("hidden") ? openAttention() : closeAttention();
    }

    btnAttention?.addEventListener("click", function(e){
        e.preventDefault();
        e.stopPropagation();
        toggleAttention();
    });

    document.addEventListener("click", function(e){
        if (!popover || popover.classList.contains("hidden")) return;
        if (popover.contains(e.target) || btnAttention?.contains(e.target)) return;
        closeAttention();
    });

    document.addEventListener("keydown", function(e){
        if (e.key === "Escape") closeAttention();
    });

    document.querySelectorAll("[data-close-attention]").forEach(el => {
        el.addEventListener("click", function(e){
            e.preventDefault();
            closeAttention();
        });
    });

    // -------------------------
    // Modal open/close (always works)
    // -------------------------
    const btnOpenCreate = document.getElementById("btnOpenCreateClearance");
    const modal = document.getElementById("createClearanceModal");

    function openModal(){
        if (!modal) return;
        modal.classList.remove("hidden");
        modal.classList.add("flex");
        closeAttention();
    }
    function closeModal(){
        if (!modal) return;
        modal.classList.add("hidden");
        modal.classList.remove("flex");
    }

    btnOpenCreate?.addEventListener("click", function(e){
        e.preventDefault();
        openModal();
    });

    document.addEventListener("click", function(e){
        const close = e.target.closest("[data-close-modal]");
        if (!close) return;
        e.preventDefault();
        closeModal();
    });

    // -------------------------
    // Smooth scroll to list on hash
    // -------------------------
    if (window.location.hash === "#clearances-list") {
        const anchor = document.getElementById("clearances-list");
        if (anchor) {
            setTimeout(() => {
                anchor.scrollIntoView({behavior: "smooth", block: "start"});
            }, 150);
        }
        closeAttention();
    }

    // -------------------------
    // Tabulator init (only if available)
    // IMPORTANT: Do NOT return early; rest of page still works.
    // -------------------------
    if (!window.Tabulator) {
        console.warn("Tabulator not found on window. Ensure Vite app.js is loaded and scripts are stacked after it.");
        return; // table features won't run, but modal/popover already bound above
    }

    const statusPillClass = (status) => {
        switch(status){
            case 'submitted': return "border-amber-200 bg-amber-50 text-amber-900";
            case 'tr8_issued': return "border-blue-200 bg-blue-50 text-blue-900";
            case 'arrived': return "border-emerald-200 bg-emerald-50 text-emerald-900";
            case 'cancelled': return "border-rose-200 bg-rose-50 text-rose-900";
            default: return "border-gray-200 bg-gray-50 text-gray-900";
        }
    };

    const actionHtml = (data) => {
        const s = data.status;

        const btn = (label, action, tone="gray") => {
            const toneClass = ({
                gray: "border-gray-200 hover:bg-gray-50 text-gray-800",
                dark: "border-gray-900 bg-gray-900 text-white hover:bg-gray-800",
                amber:"border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100",
                blue: "border-blue-200 bg-blue-50 text-blue-900 hover:bg-blue-100",
                rose: "border-rose-200 bg-rose-50 text-rose-900 hover:bg-rose-100",
                emerald:"border-emerald-200 bg-emerald-50 text-emerald-900 hover:bg-emerald-100",
            })[tone] || "border-gray-200 hover:bg-gray-50 text-gray-800";

            return `<button type="button"
                        class="px-3 py-1.5 rounded-xl border text-xs font-semibold ${toneClass}"
                        data-action="${action}"
                    >${label}</button>`;
        };

        let html = `<div class="flex flex-wrap items-center gap-2">`;
        html += `<a href="${data.urls.show}" class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-semibold text-gray-800 hover:bg-gray-50">Open</a>`;

        if (!canAct) {
            html += `</div>`;
            return html;
        }

        if (s === 'draft') html += btn("Submit", "submit", "dark");
        if (s === 'submitted') {
            html += btn("Issue TR8", "issue_tr8", "amber");
            html += btn("Cancel", "cancel", "rose");
        }
        if (s === 'tr8_issued') {
            html += btn("Arrived", "arrive", "emerald");
            html += btn("Cancel", "cancel", "rose");
        }

        html += `</div>`;
        return html;
    };

    const table = new Tabulator("#clearancesTable", {
        data: rows,
        layout: "fitColumns",
        reactiveData: false,
        height: "520px",
        selectable: 1,
        placeholder: "No clearances found for this filter.",
        rowHeight: 52,
        columns: [
            {
                title: "STATUS",
                field: "status_label",
                width: 140,
                formatter: (cell) => {
                    const data = cell.getRow().getData();
                    const s = data.status;
                    return `<span class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-semibold ${statusPillClass(s)}">${data.status_label}</span>`;
                }
            },
            { title: "CLIENT", field: "client", minWidth: 180 },
            { title: "TRUCK", field: "truck", width: 120 },
            { title: "TRAILER", field: "trailer", width: 140 },
            {
                title: "LOADED @20°C",
                field: "loaded_20_l",
                width: 140,
                hozAlign: "right",
                formatter: (cell) => {
                    const v = cell.getValue();
                    if (v === null || v === undefined || v === "") return "-";
                    try { return Number(v).toLocaleString(); } catch(e){ return v; }
                }
            },
            { title: "TR8", field: "tr8_number", width: 140 },
            { title: "BORDER", field: "border_point", width: 150 },
            { title: "INVOICE", field: "invoice_number", width: 130 },
            { title: "SUBMITTED", field: "submitted_at", width: 170 },
            { title: "ISSUED", field: "tr8_issued_at", width: 170 },
            { title: "UPDATED BY", field: "updated_by", width: 170 },
            { title: "UPDATED", field: "updated_at", width: 170 },
            {
                title: "ACTIONS",
                field: "actions",
                minWidth: 240,
                formatter: (cell) => actionHtml(cell.getRow().getData()),
                headerSort: false
            },
        ],
        rowClick: function(e, row){
            const t = e.target;
            if (t.closest("button") || t.closest("a")) return;
            const data = row.getData();
            if (data.urls && data.urls.show) window.location.href = data.urls.show;
        }
    });

    // Action handling (POST via fetch)
    document.addEventListener("click", async function(e){
        const btn = e.target.closest("button[data-action]");
        if (!btn) return;

        const rowEl = btn.closest(".tabulator-row");
        if (!rowEl) return;

        const row = table.getRow(rowEl);
        const data = row ? row.getData() : null;
        if (!data) return;

        const action = btn.getAttribute("data-action");

        const post = async (url, payload={}) => {
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
                alert("Action failed.\n\n" + text.slice(0, 300));
                return false;
            }
            return true;
        };

        if (action === "submit") {
            if (!confirm("Submit this clearance?")) return;
            const ok = await post(data.urls.submit);
            if (ok) window.location.reload();
        }

        if (action === "arrive") {
            if (!confirm("Mark this clearance as arrived?")) return;
            const ok = await post(data.urls.arrive);
            if (ok) window.location.reload();
        }

        if (action === "cancel") {
            if (!confirm("Cancel this clearance?")) return;
            const ok = await post(data.urls.cancel);
            if (ok) window.location.reload();
        }

        if (action === "issue_tr8") {
            const tr8 = prompt("Enter TR8 number:");
            if (!tr8) return;
            const ok = await post(data.urls.issue_tr8, { tr8_number: tr8 });
            if (ok) window.location.reload();
        }
    });

    // Exports (client-side)
    const exportXlsx = () => table.download("xlsx", "clearances.xlsx", {sheetName:"Clearances"});
    const exportPdf = () => table.download("pdf", "clearances.pdf", {orientation: "landscape", title: "Clearances"});

    document.getElementById("btnExportXlsx")?.addEventListener("click", exportXlsx);
    document.getElementById("btnExportPdf")?.addEventListener("click", exportPdf);

})();
</script>
@endpush