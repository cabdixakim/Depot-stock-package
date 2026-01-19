{{-- resources/views/vendor/depot-stock/compliance/clearances/index.blade.php --}}
@extends('depot-stock::layouts.app')

@section('content')
@php
    // -------------------------------
    // Safety defaults
    // -------------------------------
    $clients    = $clients ?? collect();
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
    // Role gating (avoid User::hasRole signature mismatch)
    // -------------------------------
    $u = auth()->user();
    $roleNames = $u?->roles?->pluck('name')->map(fn($r) => strtolower((string)$r))->all() ?? [];

    $canCreate = in_array('admin', $roleNames, true) || in_array('owner', $roleNames, true) || in_array('compliance', $roleNames, true);
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
    // Current page items (server-filtered)
    // -------------------------------
    $items = [];
    if ($clearances && method_exists($clearances, 'items')) {
        $items = $clearances->items();
    } elseif (is_iterable($clearances)) {
        $items = $clearances;
    }

    $statusMeta = [
        'draft'      => ['label' => 'Draft'],
        'submitted'  => ['label' => 'Submitted'],
        'tr8_issued' => ['label' => 'TR8 Issued'],
        'arrived'    => ['label' => 'Arrived'],
        'cancelled'  => ['label' => 'Cancelled'],
    ];

    $now = now();

    // Needs attention totals
    $attStuckSubmitted = (int)($stats['stuck_submitted'] ?? 0);
    $attStuckTr8       = (int)($stats['stuck_tr8_issued'] ?? 0);
    $attMissingTr8     = (int)($stats['missing_tr8_number'] ?? 0);
    $attMissingDocs    = (int)($stats['missing_documents'] ?? 0);
    $attTotal          = $attStuckSubmitted + $attStuckTr8 + $attMissingTr8 + $attMissingDocs;

    // Base query string (preserve user filters)
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

    // Build JSON rows for Tabulator (CURRENT PAGE ONLY)
    $rows = [];
    foreach ($items as $c) {
        $status = $c->status ?? 'draft';
        $meta = $statusMeta[$status] ?? ['label' => ucfirst((string)$status)];

        $rows[] = [
            'id'           => $c->id,
            'status'       => $status,
            'status_label' => $meta['label'],
            'client'       => $c->client->name ?? '-',
            'truck'        => $c->truck_number ?? '-',
            'trailer'      => $c->trailer_number ?? '-',
            'loaded_20_l'  => $c->loaded_20_l ?? null,
            'tr8_number'   => $c->tr8_number ?? null,
            'border_point' => $c->border_point ?? null,
            'invoice_number' => $c->invoice_number ?? null,
            'submitted_at' => optional($c->submitted_at ?? null)?->toDateTimeString(),
            'tr8_issued_at'=> optional($c->tr8_issued_at ?? null)?->toDateTimeString(),
            'updated_by'   => $c->updatedBy->name ?? $c->updated_by_name ?? null,
            'updated_at'   => optional($c->updated_at ?? null)?->toDateTimeString(),
            'urls' => [
                'show'      => route('depot.compliance.clearances.show', $c),
                'submit'    => route('depot.compliance.clearances.submit', $c),
                'issue_tr8' => route('depot.compliance.clearances.issue_tr8', $c),
                'arrive'    => route('depot.compliance.clearances.arrive', $c),
                'cancel'    => route('depot.compliance.clearances.cancel', $c),
            ],
        ];
    }

    $pill = function($label, $count, $tone = 'gray') {
        $base = "inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold";
        $toneClass = match($tone) {
            'amber'   => "border-amber-200 bg-amber-50 text-amber-900",
            'blue'    => "border-blue-200 bg-blue-50 text-blue-900",
            'emerald' => "border-emerald-200 bg-emerald-50 text-emerald-900",
            'rose'    => "border-rose-200 bg-rose-50 text-rose-900",
            default   => "border-gray-200 bg-gray-50 text-gray-800",
        };
        $count = (int)$count;
        return <<<HTML
            <div class="{$base} {$toneClass}">
                <span>{$label}</span>
                <span class="inline-flex items-center justify-center rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-bold border border-black/5">{$count}</span>
            </div>
        HTML;
    };
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mt-6">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="p-5 sm:p-6">

                {{-- Header (compact ops) --}}
                <div class="flex items-start justify-between gap-4">
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
                            <span class="hidden sm:inline">•</span>
                            <span><span class="font-semibold text-gray-700">{{ count($items) }}</span> row(s) on this page</span>
                        </div>
                    </div>

                    {{-- Actions (no squashing) --}}
                    <div class="flex items-center gap-2">
                        @if($canCreate)
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                                id="btnOpenCreateClearance"
                            >
                                <span class="text-base leading-none">+</span>
                                <span>New</span>
                            </button>
                        @endif

                        {{-- Attention button (notification style) --}}
                        <div class="relative" id="attWrap">
                            <button
                                type="button"
                                id="btnAttention"
                                class="group relative inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-3 py-2 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                                aria-haspopup="true"
                                aria-expanded="false"
                                title="Needs attention"
                            >
                                <svg class="h-5 w-5 text-gray-800 group-hover:text-gray-900" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 22a2.5 2.5 0 0 0 2.45-2H9.55A2.5 2.5 0 0 0 12 22Z" fill="currentColor" opacity=".9"/>
                                    <path d="M20 17H4c1.8-1.5 2.5-3.3 2.5-6V9.5C6.5 6.46 8.7 4 12 4s5.5 2.46 5.5 5.5V11c0 2.7.7 4.5 2.5 6Z"
                                          stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                                    <path d="M18.7 7.2c.55.5.95 1.1 1.2 1.8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" opacity=".65"/>
                                </svg>

                                @if($attTotal > 0)
                                    <span class="absolute -top-2 -right-2 inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1.5 text-[11px] font-bold text-white shadow">
                                        {{ $attTotal }}
                                    </span>
                                @endif
                            </button>

                            {{-- Anchored panel (wider, no fluff) --}}
                            <div
                                id="attentionPanel"
                                class="hidden absolute right-0 mt-2 w-[24rem] sm:w-[28rem] rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden z-40"
                            >
                                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                    <div class="text-sm font-semibold text-gray-900">Needs attention</div>
                                    <button type="button" class="rounded-lg px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50" data-att-close="1">
                                        Close
                                    </button>
                                </div>

                                <div class="p-2">
                                    <a href="{{ $makeFilterUrl(['status' => 'submitted', '__att' => 'stuck_submitted']) }}#clearances"
                                       class="flex items-center justify-between rounded-xl px-3 py-2 hover:bg-gray-50">
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-gray-900">Stuck in Submitted</div>
                                            <div class="text-xs text-gray-500">Chase border/agent</div>
                                        </div>
                                        <div class="inline-flex items-center gap-2">
                                            <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-bold text-amber-900 border border-amber-200">{{ $attStuckSubmitted }}</span>
                                            <span class="text-gray-300">›</span>
                                        </div>
                                    </a>

                                    <a href="{{ $makeFilterUrl(['status' => 'tr8_issued', '__att' => 'stuck_tr8_issued']) }}#clearances"
                                       class="flex items-center justify-between rounded-xl px-3 py-2 hover:bg-gray-50">
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-gray-900">TR8 issued, not arrived</div>
                                            <div class="text-xs text-gray-500">Chase truck/dispatch</div>
                                        </div>
                                        <div class="inline-flex items-center gap-2">
                                            <span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-bold text-blue-900 border border-blue-200">{{ $attStuckTr8 }}</span>
                                            <span class="text-gray-300">›</span>
                                        </div>
                                    </a>

                                    <a href="{{ $makeFilterUrl(['__att' => 'missing_tr8_number']) }}#clearances"
                                       class="flex items-center justify-between rounded-xl px-3 py-2 hover:bg-gray-50">
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-gray-900">Missing TR8 number</div>
                                            <div class="text-xs text-gray-500">Data risk</div>
                                        </div>
                                        <div class="inline-flex items-center gap-2">
                                            <span class="rounded-full bg-rose-50 px-2 py-1 text-xs font-bold text-rose-900 border border-rose-200">{{ $attMissingTr8 }}</span>
                                            <span class="text-gray-300">›</span>
                                        </div>
                                    </a>

                                    <a href="{{ $makeFilterUrl(['__att' => 'missing_documents']) }}#clearances"
                                       class="flex items-center justify-between rounded-xl px-3 py-2 hover:bg-gray-50">
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-gray-900">Missing documents</div>
                                            <div class="text-xs text-gray-500">Audit risk</div>
                                        </div>
                                        <div class="inline-flex items-center gap-2">
                                            <span class="rounded-full bg-gray-50 px-2 py-1 text-xs font-bold text-gray-800 border border-gray-200">{{ $attMissingDocs }}</span>
                                            <span class="text-gray-300">›</span>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Status pills (simple, informational) --}}
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    {!! $pill('Total', (int)($stats['total'] ?? 0), 'gray') !!}
                    {!! $pill('Draft', (int)($stats['draft'] ?? 0), 'gray') !!}
                    {!! $pill('Submitted', (int)($stats['submitted'] ?? 0), 'amber') !!}
                    {!! $pill('TR8 Issued', (int)($stats['tr8_issued'] ?? 0), 'blue') !!}
                    {!! $pill('Arrived', (int)($stats['arrived'] ?? 0), 'emerald') !!}
                    {!! $pill('Cancelled', (int)($stats['cancelled'] ?? 0), 'rose') !!}
                </div>

                {{-- Filters (compact) --}}
                <form method="GET" class="mt-4 rounded-2xl border border-gray-200 bg-white p-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                        <div class="lg:col-span-3">
                            <label class="text-[11px] font-semibold text-gray-600">Client</label>
                            <select name="client_id"
                                    class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10">
                                <option value="">All clients</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}" @selected((string)$fClient === (string)$c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="text-[11px] font-semibold text-gray-600">Status</label>
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
                            <label class="text-[11px] font-semibold text-gray-600">Search</label>
                            <input name="q" value="{{ $fSearch }}"
                                   placeholder="Truck, trailer, TR8, invoice, border…"
                                   class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10" />
                        </div>

                        <div class="lg:col-span-3 grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-[11px] font-semibold text-gray-600">From</label>
                                <input type="date" name="from" value="{{ $fFrom }}"
                                       class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10" />
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold text-gray-600">To</label>
                                <input type="date" name="to" value="{{ $fTo }}"
                                       class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10" />
                            </div>
                        </div>

                        <div class="lg:col-span-12 flex items-center justify-end gap-2 pt-1">
                            <a href="{{ url()->current() }}"
                               class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                                Reset
                            </a>
                            <button type="submit"
                                    class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">
                                Apply
                            </button>
                        </div>
                    </div>
                </form>

                {{-- Table header + compact exports (ONLY PLACE exports live) --}}
                <div id="clearances" class="mt-5 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">Clearances</div>
                        <div class="text-xs text-gray-500">Table is the workspace. Exports follow current filters (this page).</div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button"
                                class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50"
                                id="btnExportXlsx">
                            Export Excel
                        </button>
                        <button type="button"
                                class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50"
                                id="btnExportPdf">
                            Export PDF
                        </button>
                    </div>
                </div>

                {{-- Tabulator container --}}
                <div class="mt-3 rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between">
                        <div class="text-xs text-gray-500">
                            Showing <span class="font-semibold text-gray-700">{{ count($items) }}</span> row(s) on this page
                        </div>
                        <div class="text-xs text-gray-400">Tip: click row to open</div>
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
</div>

{{-- Create modal (keep; working) --}}
@if($canCreate)
    @include('depot-stock::compliance.clearances._create_modal', ['clients' => $clients])
@endif
@endsection

@push('styles')
<style>
    /* Make Tabulator feel native */
    #clearancesTable .tabulator { border: 0; border-radius: 14px; }
    #clearancesTable .tabulator-header { border-bottom: 1px solid rgba(0,0,0,.06); }
    #clearancesTable .tabulator-row { border-bottom: 1px solid rgba(0,0,0,.04); }
    #clearancesTable .tabulator-row:hover { background: rgba(0,0,0,.02); }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function () {
    // If this logs, your pushed scripts are running but Vite/app.js didn't load before stacks.
    if (!window.Tabulator) {
        console.error("Tabulator missing on window. Check: Vite app.js loaded BEFORE @stack('scripts').");
        return;
    }

    const csrf  = @json(csrf_token());
    const canAct = @json($canAct);
    const rows  = @json($rows);

    const statusPillClass = (status) => {
        switch (status) {
            case "submitted":  return "border-amber-200 bg-amber-50 text-amber-900";
            case "tr8_issued": return "border-blue-200 bg-blue-50 text-blue-900";
            case "arrived":    return "border-emerald-200 bg-emerald-50 text-emerald-900";
            case "cancelled":  return "border-rose-200 bg-rose-50 text-rose-900";
            default:           return "border-gray-200 bg-gray-50 text-gray-900";
        }
    };

    const actionHtml = (data) => {
        const s = data.status;

        const btn = (label, action, tone="gray") => {
            const toneClass = ({
                gray:    "border-gray-200 hover:bg-gray-50 text-gray-800",
                dark:    "border-gray-900 bg-gray-900 text-white hover:bg-gray-800",
                amber:   "border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100",
                blue:    "border-blue-200 bg-blue-50 text-blue-900 hover:bg-blue-100",
                rose:    "border-rose-200 bg-rose-50 text-rose-900 hover:bg-rose-100",
                emerald: "border-emerald-200 bg-emerald-50 text-emerald-900 hover:bg-emerald-100",
            })[tone] || "border-gray-200 hover:bg-gray-50 text-gray-800";

            return `<button type="button" class="px-3 py-1.5 rounded-xl border text-xs font-semibold ${toneClass}" data-action="${action}">${label}</button>`;
        };

        let html = `<div class="flex flex-wrap items-center gap-2">`;
        html += `<a href="${data.urls.show}" class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-semibold text-gray-800 hover:bg-gray-50">Open</a>`;

        if (!canAct) return html + `</div>`;

        if (s === "draft") {
            html += btn("Submit", "submit", "dark");
        }
        if (s === "submitted") {
            html += btn("Issue TR8", "issue_tr8", "amber");
            html += btn("Cancel", "cancel", "rose");
        }
        if (s === "tr8_issued") {
            html += btn("Mark arrived", "arrive", "emerald");
            html += btn("Cancel", "cancel", "rose");
        }
        return html + `</div>`;
    };

    const table = new Tabulator("#clearancesTable", {
        data: rows,
        layout: "fitColumns",
        height: "520px",
        placeholder: "No clearances found for this filter.",
        rowHeight: 48,
        selectable: 1,
        columns: [
            {
                title: "STATUS",
                field: "status_label",
                width: 150,
                formatter: (cell) => {
                    const d = cell.getRow().getData();
                    return `<span class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-semibold ${statusPillClass(d.status)}">${d.status_label}</span>`;
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
                    try { return Number(v).toLocaleString(); } catch(e) { return v; }
                }
            },
            { title: "TR8", field: "tr8_number", width: 140 },
            { title: "BORDER", field: "border_point", width: 150 },
            { title: "INVOICE", field: "invoice_number", width: 130 },
            { title: "SUBMITTED", field: "submitted_at", width: 170 },
            { title: "ISSUED", field: "tr8_issued_at", width: 170 },
            { title: "UPDATED BY", field: "updated_by", width: 170 },
            { title: "UPDATED", field: "updated_at", width: 170 },
            { title: "ACTIONS", field: "actions", minWidth: 260, formatter: (cell) => actionHtml(cell.getRow().getData()), headerSort: false },
        ],
        rowClick: function (e, row) {
            const t = e.target;
            if (t.closest("button") || t.closest("a")) return;
            const d = row.getData();
            if (d.urls && d.urls.show) window.location.href = d.urls.show;
        }
    });

    // Actions (POST via fetch)
    document.addEventListener("click", async function (e) {
        const btn = e.target.closest("button[data-action]");
        if (!btn) return;

        const rowEl = btn.closest(".tabulator-row");
        if (!rowEl) return;

        const row = table.getRow(rowEl);
        const data = row ? row.getData() : null;
        if (!data) return;

        const action = btn.getAttribute("data-action");

        const post = async (url, payload = {}) => {
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
            if (await post(data.urls.submit)) window.location.reload();
        }

        if (action === "arrive") {
            if (!confirm("Mark this clearance as arrived?")) return;
            if (await post(data.urls.arrive)) window.location.reload();
        }

        if (action === "cancel") {
            if (!confirm("Cancel this clearance?")) return;
            if (await post(data.urls.cancel)) window.location.reload();
        }

        if (action === "issue_tr8") {
            const tr8 = prompt("Enter TR8 number:");
            if (!tr8) return;
            if (await post(data.urls.issue_tr8, { tr8_number: tr8 })) window.location.reload();
        }
    });

    // Exports (client-side; exports what the table currently has)
    document.getElementById("btnExportXlsx")?.addEventListener("click", function () {
        table.download("xlsx", "clearances.xlsx", { sheetName: "Clearances" });
    });

    document.getElementById("btnExportPdf")?.addEventListener("click", function () {
        table.download("pdf", "clearances.pdf", { orientation: "landscape", title: "Clearances" });
    });

    // Open createZI modal (keep)
    document.getElementById("btnOpenCreateClearance")?.addEventListener("click", function () {
        const modal = document.getElementById("createClearanceModal");
        if (!modal) return;
        modal.classList.remove("hidden");
        modal.classList.add("flex");
    });

    // Attention panel toggle (anchored)
    const attBtn = document.getElementById("btnAttention");
    const attPanel = document.getElementById("attentionPanel");
    const attWrap = document.getElementById("attWrap");

    const closeAttention = () => {
        if (!attPanel) return;
        attPanel.classList.add("hidden");
        attBtn?.setAttribute("aria-expanded", "false");
    };

    const openAttention = () => {
        if (!attPanel) return;
        attPanel.classList.remove("hidden");
        attBtn?.setAttribute("aria-expanded", "true");
    };

    attBtn?.addEventListener("click", function () {
        if (!attPanel) return;
        const isOpen = !attPanel.classList.contains("hidden");
        isOpen ? closeAttention() : openAttention();
    });

    document.addEventListener("click", function (e) {
        if (!attWrap || !attPanel) return;

        if (e.target.closest("[data-att-close]")) {
            closeAttention();
            return;
        }

        if (!attWrap.contains(e.target)) closeAttention();
    });

    // Smooth scroll when coming from attention links
    if (window.location.hash === "#clearances") {
        const el = document.getElementById("clearances");
        if (el) el.scrollIntoView({ behavior: "smooth", block: "start" });
    }
});
</script>
@endpush