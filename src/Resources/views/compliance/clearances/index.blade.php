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
    // Your User::hasRole expects string only.
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

    // -------------------------------
    // Build table dataset (current page only)
    // If you want "export ALL filtered", we can add a dedicated "export-data" route later.
    // -------------------------------
    $items = [];
    if ($clearances && method_exists($clearances, 'items')) {
        $items = $clearances->items();
    } elseif (is_iterable($clearances)) {
        $items = $clearances;
    }

    // Helpers for UI
    $statusMeta = [
        'draft'     => ['label' => 'Draft', 'pill' => 'bg-gray-100 text-gray-800 border-gray-200'],
        'submitted' => ['label' => 'Submitted', 'pill' => 'bg-amber-50 text-amber-900 border-amber-200'],
        'tr8_issued'=> ['label' => 'TR8 Issued', 'pill' => 'bg-blue-50 text-blue-900 border-blue-200'],
        'arrived'   => ['label' => 'Arrived', 'pill' => 'bg-emerald-50 text-emerald-900 border-emerald-200'],
        'cancelled' => ['label' => 'Cancelled', 'pill' => 'bg-rose-50 text-rose-900 border-rose-200'],
    ];

    $now = now();
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    {{-- Header --}}
    <div class="mt-6">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="p-5 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="text-sm text-gray-500">Compliance</div>
                        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Clearances &amp; TR8</h1>
                        <p class="mt-1 text-sm text-gray-600">
                            A task-focused inbox for what’s stuck, missing, or overdue — plus a high-density list for fast updates.
                        </p>

                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                            <span class="inline-flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                Live
                            </span>
                            <span class="hidden sm:inline">•</span>
                            <span>Last refreshed: <span class="font-medium text-gray-700">{{ $now->format('m/d/Y, g:i:s A') }}</span></span>
                            <span class="hidden sm:inline">•</span>
                            <span class="font-medium text-gray-700">{{ count($items) }}</span> rows on this page
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 justify-start sm:justify-end">
                        @if($canCreate)
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                                id="btnOpenCreateClearance"
                            >
                                <span class="text-base leading-none">+</span>
                                New clearance
                            </button>
                        @endif

                        {{-- Export buttons (MOVED just above list per your request) --}}
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50"
                            id="btnExportXlsxTop"
                        >
                            Export Excel
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50"
                            id="btnExportPdfTop"
                        >
                            Export PDF
                        </button>
                    </div>
                </div>

                {{-- Status strip (clickable pills) --}}
                <div class="mt-5 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    @php
                        $pill = function($label, $count, $key, $class) use ($fClient, $fSearch, $fFrom, $fTo) {
                            $qs = array_filter([
                                'status' => $key,
                                'client_id' => $fClient,
                                'q' => $fSearch,
                                'from' => $fFrom,
                                'to' => $fTo,
                            ], fn($v) => $v !== null && $v !== '');
                            $href = url()->current() . (count($qs) ? ('?' . http_build_query($qs)) : '');
                            return <<<HTML
                                <a href="{$href}" class="group rounded-2xl border {$class} bg-white p-3 shadow-sm hover:shadow transition">
                                    <div class="text-xs font-medium text-gray-600 group-hover:text-gray-900">{$label}</div>
                                    <div class="mt-1 flex items-baseline gap-2">
                                        <div class="text-2xl font-semibold text-gray-900">{$count}</div>
                                        <div class="text-xs text-gray-500">cases</div>
                                    </div>
                                </a>
                            HTML;
                        };
                    @endphp

                    {!! $pill('Total', (int)($stats['total'] ?? 0), '', 'border-gray-200') !!}
                    {!! $pill('Draft', (int)($stats['draft'] ?? 0), 'draft', 'border-gray-200') !!}
                    {!! $pill('Submitted', (int)($stats['submitted'] ?? 0), 'submitted', 'border-amber-200') !!}
                    {!! $pill('TR8 issued', (int)($stats['tr8_issued'] ?? 0), 'tr8_issued', 'border-blue-200') !!}
                    {!! $pill('Arrived', (int)($stats['arrived'] ?? 0), 'arrived', 'border-emerald-200') !!}
                    {!! $pill('Cancelled', (int)($stats['cancelled'] ?? 0), 'cancelled', 'border-rose-200') !!}
                </div>

                {{-- Attention inbox --}}
                <div class="mt-5 rounded-2xl border border-gray-200 bg-gradient-to-b from-gray-50 to-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Needs attention</div>
                            <div class="mt-1 text-xs text-gray-600">
                                Flags for overdue stages, missing TR8/docs, and “stuck” items. Treat this like a task inbox.
                            </div>
                        </div>
                        <div class="text-xs text-gray-500">
                            Thresholds: Submitted &gt; 24h • TR8 issued &gt; 24h (not arrived)
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                            <div class="text-xs font-semibold text-amber-900">Stuck in Submitted</div>
                            <div class="mt-2 flex items-baseline gap-2">
                                <div class="text-2xl font-semibold text-amber-900">{{ (int)($stats['stuck_submitted'] ?? 0) }}</div>
                                <div class="text-xs text-amber-800">items</div>
                            </div>
                            <div class="mt-1 text-xs text-amber-800/80">Chase border/agent</div>
                        </div>

                        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4">
                            <div class="text-xs font-semibold text-blue-900">TR8 issued, not arrived</div>
                            <div class="mt-2 flex items-baseline gap-2">
                                <div class="text-2xl font-semibold text-blue-900">{{ (int)($stats['stuck_tr8_issued'] ?? 0) }}</div>
                                <div class="text-xs text-blue-800">items</div>
                            </div>
                            <div class="mt-1 text-xs text-blue-800/80">Chase truck/dispatch</div>
                        </div>

                        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                            <div class="text-xs font-semibold text-rose-900">Missing TR8 number</div>
                            <div class="mt-2 flex items-baseline gap-2">
                                <div class="text-2xl font-semibold text-rose-900">{{ (int)($stats['missing_tr8_number'] ?? 0) }}</div>
                                <div class="text-xs text-rose-800">items</div>
                            </div>
                            <div class="mt-1 text-xs text-rose-800/80">Data risk</div>
                        </div>

                        <div class="rounded-2xl border border-gray-200 bg-white p-4">
                            <div class="text-xs font-semibold text-gray-900">Missing documents</div>
                            <div class="mt-2 flex items-baseline gap-2">
                                <div class="text-2xl font-semibold text-gray-900">{{ (int)($stats['missing_documents'] ?? 0) }}</div>
                                <div class="text-xs text-gray-600">items</div>
                            </div>
                            <div class="mt-1 text-xs text-gray-600">Audit risk</div>
                        </div>
                    </div>
                </div>

                {{-- Filters (complete overhaul) --}}
                <form method="GET" class="mt-5 rounded-2xl border border-gray-200 bg-white p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Filters</div>
                            <div class="mt-1 text-xs text-gray-600">Filters affect the list and exports (current page).</div>
                        </div>

                        <div class="flex gap-2">
                            <button type="submit"
                                class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">
                                Apply
                            </button>
                            <a href="{{ url()->current() }}"
                                class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                                Reset
                            </a>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
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
                            <input
                                name="q"
                                value="{{ $fSearch }}"
                                placeholder="Truck, trailer, TR8, invoice, border…"
                                class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                            />
                        </div>

                        <div class="lg:col-span-3 grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-medium text-gray-700">From</label>
                                <input
                                    name="from"
                                    value="{{ $fFrom }}"
                                    placeholder="YYYY-MM-DD"
                                    class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-700">To</label>
                                <input
                                    name="to"
                                    value="{{ $fTo }}"
                                    placeholder="YYYY-MM-DD"
                                    class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                                />
                            </div>
                        </div>
                    </div>
                </form>

                {{-- List header + export buttons moved here too (per request: “just above the list”) --}}
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">Clearances</div>
                        <div class="text-xs text-gray-600">High density list, quick actions, and clean exports.</div>
                    </div>

                    <div class="flex items-center gap-2 justify-start sm:justify-end">
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

                {{-- Tabulator container --}}
                <div class="mt-3 rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="p-3 border-b border-gray-100 flex items-center justify-between">
                        <div class="text-xs text-gray-500">
                            Showing <span class="font-semibold text-gray-700">{{ count($items) }}</span>
                            row(s) on this page
                        </div>
                        <div class="text-xs text-gray-500">
                            Tip: click a row to open, use Actions for workflow.
                        </div>
                    </div>

                    <div class="p-3">
                        <div id="clearancesTable"></div>
                    </div>
                </div>

                {{-- Pagination (if paginator) --}}
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

@php
    // Build JSON rows for Tabulator
    $rows = [];
    foreach ($items as $c) {
        $status = $c->status ?? 'draft';
        $meta = $statusMeta[$status] ?? ['label' => ucfirst($status), 'pill' => 'bg-gray-100 text-gray-800 border-gray-200'];

        $rows[] = [
            'id' => $c->id,
            'status' => $status,
            'status_label' => $meta['label'],
            'client' => $c->client->name ?? '-',
            'truck' => $c->truck_number ?? '-',
            'trailer' => $c->trailer_number ?? '-',
            'loaded_20_l' => $c->loaded_20_l ?? null,
            'tr8_number' => $c->tr8_number ?? null,
            'border_point' => $c->border_point ?? null,
            'invoice_number' => $c->invoice_number ?? null,
            'submitted_at' => optional($c->submitted_at ?? null)?->toDateTimeString(),
            'tr8_issued_at' => optional($c->tr8_issued_at ?? null)?->toDateTimeString(),
            'updated_by' => $c->updatedBy->name ?? $c->updated_by_name ?? null, // adjust to your relationship/field
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

<script>
(function(){
    // Ensure Tabulator exists (you already import TabulatorFull in app.js)
    if (!window.Tabulator) {
        console.error("Tabulator not found on window. Check app.js imports.");
        return;
    }

    const csrf = @json(csrf_token());
    const canAct = @json($canAct);

    const rows = @json($rows);

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
        // Minimal, fast “ops” actions
        // You can expand rules later.
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

        if (s === 'draft') {
            html += btn("Submit", "submit", "dark");
        }
        if (s === 'submitted') {
            html += btn("Issue TR8", "issue_tr8", "amber");
            html += btn("Cancel", "cancel", "rose");
        }
        if (s === 'tr8_issued') {
            html += btn("Mark arrived", "arrive", "emerald");
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
            // Avoid hijacking clicks on buttons/links
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

        const cell = btn.closest(".tabulator-cell");
        if (!cell) return;

        const rowComponent = Tabulator.prototype.findTable("#clearancesTable")[0].getRowFromElement(btn.closest(".tabulator-row"));
        const data = rowComponent ? rowComponent.getData() : null;
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
            // If your controller returns redirect, Laravel may respond with HTML.
            // We just hard refresh after success-ish response.
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
    const exportXlsx = () => {
        table.download("xlsx", "clearances.xlsx", {sheetName:"Clearances"});
    };

    const exportPdf = () => {
        // Tabulator uses jsPDF on window.jsPDF or window.jspdf.jsPDF depending setup;
        // you already expose both in app.js.
        table.download("pdf", "clearances.pdf", {
            orientation: "landscape",
            title: "Clearances"
        });
    };

    document.getElementById("btnExportXlsx")?.addEventListener("click", exportXlsx);
    document.getElementById("btnExportPdf")?.addEventListener("click", exportPdf);
    document.getElementById("btnExportXlsxTop")?.addEventListener("click", exportXlsx);
    document.getElementById("btnExportPdfTop")?.addEventListener("click", exportPdf);

    // Open modal
    document.getElementById("btnOpenCreateClearance")?.addEventListener("click", function(){
        const modal = document.getElementById("createClearanceModal");
        if (!modal) return;
        modal.classList.remove("hidden");
        modal.classList.add("flex");
    });

})();
</script>
@endsection