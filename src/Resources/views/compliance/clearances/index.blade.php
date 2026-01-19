@extends('depot-stock::layouts.app')

@section('content')
@php
    use Carbon\Carbon;

    // safe defaults
    $stats = $stats ?? [];
    $val = function($k, $default = 0) use ($stats) {
        return isset($stats[$k]) && $stats[$k] !== null ? $stats[$k] : $default;
    };

    $submittedStaleHours = $submittedStaleHours ?? 24;
    $tr8IssuedStaleHours = $tr8IssuedStaleHours ?? 24;

    $clients = $clients ?? collect();
@endphp

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h3 class="mb-1">Compliance — Clearances</h3>
        <div class="text-muted small">Guides you to what needs attention first (stuck, missing TR8/docs, overdue stages).</div>
    </div>

    <div class="d-flex gap-2 align-items-center">
        <div class="text-muted small d-none d-md-block">
            <span id="last-refreshed">Last refreshed: —</span>
        </div>

        <button id="export-xlsx" type="button" class="btn btn-outline-secondary btn-sm">
            Export Excel
        </button>
        <button id="export-pdf" type="button" class="btn btn-outline-secondary btn-sm">
            Export PDF
        </button>

        @if(auth()->user()->hasRole('admin|compliance'))
            <a class="btn btn-primary btn-sm" href="{{ route('depot.compliance.clearances.create') }}">
                + New Clearance
            </a>
        @endif
    </div>
</div>

{{-- KPI BAR --}}
<div class="row g-2 mb-3">
    <div class="col-12 col-md-4">
        <div class="kpi-card kpi-main">
            <div class="kpi-title">Total Clearances</div>
            <div class="kpi-value">{{ $val('total') }}</div>
            <div class="kpi-sub">Across all statuses</div>
        </div>
    </div>

    <div class="col-6 col-md-2">
        <div class="kpi-card">
            <div class="kpi-title">Draft</div>
            <div class="kpi-value">{{ $val('draft') }}</div>
            <div class="kpi-sub">Not submitted yet</div>
        </div>
    </div>

    <div class="col-6 col-md-2">
        <div class="kpi-card kpi-warn">
            <div class="kpi-title">Submitted</div>
            <div class="kpi-value">{{ $val('submitted') }}</div>
            <div class="kpi-sub">Waiting TR8</div>
        </div>
    </div>

    <div class="col-6 col-md-2">
        <div class="kpi-card kpi-info">
            <div class="kpi-title">TR8 Issued</div>
            <div class="kpi-value">{{ $val('tr8_issued') }}</div>
            <div class="kpi-sub">In transit</div>
        </div>
    </div>

    <div class="col-6 col-md-2">
        <div class="kpi-card kpi-good">
            <div class="kpi-title">Arrived</div>
            <div class="kpi-value">{{ $val('arrived') }}</div>
            <div class="kpi-sub">Ready to offload</div>
        </div>
    </div>
</div>

{{-- ATTENTION --}}
<div class="mb-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h5 class="mb-0">Needs attention</h5>
        <div class="text-muted small">
            Thresholds: Submitted &gt; {{ $submittedStaleHours }}h, TR8 Issued &gt; {{ $tr8IssuedStaleHours }}h (not arrived).
        </div>
    </div>

    <div class="row g-2">
        <div class="col-12 col-md-3">
            <div class="alert-tile alert-amber">
                <div class="alert-title">Stuck in Submitted</div>
                <div class="alert-value">{{ $val('stuck_submitted') }}</div>
                <div class="alert-sub">Chase agent/border</div>
            </div>
        </div>

        <div class="col-12 col-md-3">
            <div class="alert-tile alert-blue">
                <div class="alert-title">TR8 Issued, not Arrived</div>
                <div class="alert-value">{{ $val('stuck_tr8_issued') }}</div>
                <div class="alert-sub">Follow dispatch</div>
            </div>
        </div>

        <div class="col-12 col-md-3">
            <div class="alert-tile alert-red">
                <div class="alert-title">Missing TR8 Number</div>
                <div class="alert-value">{{ $val('missing_tr8_number') }}</div>
                <div class="alert-sub">Fix data risk</div>
            </div>
        </div>

        <div class="col-12 col-md-3">
            <div class="alert-tile alert-red">
                <div class="alert-title">Missing Documents</div>
                <div class="alert-value">{{ $val('missing_docs') }}</div>
                <div class="alert-sub">Upload for audit</div>
            </div>
        </div>
    </div>
</div>

{{-- FILTERS --}}
<div class="panel mb-3">
    <div class="panel-h">
        <div class="panel-title">Filters</div>
        <div class="text-muted small">Filters affect the table and exports.</div>
    </div>

    <div class="panel-b">
        <form class="row g-2 align-items-end" method="GET" action="{{ route('depot.compliance.clearances.index') }}">
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1">Client</label>
                <select name="client_id" class="form-select form-select-sm">
                    <option value="">All Clients</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" @selected((string)request('client_id') === (string)$c->id)>
                            {{ $c->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(['draft','submitted','tr8_issued','arrived','offloaded','cancelled'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>
                            {{ strtoupper(str_replace('_',' ', $s)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1">Search</label>
                <input class="form-control form-control-sm" name="q" value="{{ request('q') }}"
                       placeholder="Truck, trailer, TR8, invoice...">
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">From</label>
                <input type="date" class="form-control form-control-sm" name="from" value="{{ request('from') }}">
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">To</label>
                <input type="date" class="form-control form-control-sm" name="to" value="{{ request('to') }}">
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary btn-sm">Apply</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('depot.compliance.clearances.index') }}">Reset</a>
            </div>
        </form>
    </div>
</div>

{{-- TABLE --}}
<div class="panel">
    <div class="panel-h">
        <div class="panel-title">Clearances</div>
        <div class="text-muted small">Click “Open” to manage status + documents.</div>
    </div>

    <div class="panel-b">
        <div id="clearances-table"></div>

        {{-- nice empty state if table has no rows --}}
        <div id="empty-state" class="empty-state d-none">
            <div class="empty-title">No clearances found</div>
            <div class="empty-sub">Try changing filters, or create the first clearance.</div>
            @if(auth()->user()->hasRole('admin|compliance'))
                <a class="btn btn-primary btn-sm mt-2" href="{{ route('depot.compliance.clearances.create') }}">
                    + New Clearance
                </a>
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Panels */
    .panel{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;overflow:hidden}
    .panel-h{padding:14px 16px;border-bottom:1px solid rgba(0,0,0,.06);display:flex;align-items:center;justify-content:space-between}
    .panel-title{font-weight:700}
    .panel-b{padding:12px}

    /* KPI */
    .kpi-card{border:1px solid rgba(0,0,0,.08);border-radius:14px;background:#fff;padding:14px;min-height:92px}
    .kpi-main{background:linear-gradient(135deg, rgba(0,0,0,.02), rgba(0,0,0,.00))}
    .kpi-warn{background:linear-gradient(135deg, rgba(255,193,7,.14), rgba(255,255,255,0))}
    .kpi-info{background:linear-gradient(135deg, rgba(13,202,240,.14), rgba(255,255,255,0))}
    .kpi-good{background:linear-gradient(135deg, rgba(25,135,84,.14), rgba(255,255,255,0))}
    .kpi-title{font-size:12px;color:rgba(0,0,0,.55);font-weight:600}
    .kpi-value{font-size:26px;font-weight:800;line-height:1.1;margin-top:6px}
    .kpi-sub{font-size:12px;color:rgba(0,0,0,.55);margin-top:4px}

    /* Attention tiles */
    .alert-tile{border:1px solid rgba(0,0,0,.08);border-radius:14px;background:#fff;padding:14px;min-height:92px}
    .alert-amber{background:linear-gradient(135deg, rgba(255,193,7,.16), rgba(255,255,255,0))}
    .alert-blue{background:linear-gradient(135deg, rgba(13,110,253,.14), rgba(255,255,255,0))}
    .alert-red{background:linear-gradient(135deg, rgba(220,53,69,.14), rgba(255,255,255,0))}
    .alert-title{font-size:12px;color:rgba(0,0,0,.60);font-weight:700}
    .alert-value{font-size:24px;font-weight:900;line-height:1.1;margin-top:6px}
    .alert-sub{font-size:12px;color:rgba(0,0,0,.55);margin-top:4px}

    /* Empty state */
    .empty-state{margin-top:12px;border:1px dashed rgba(0,0,0,.15);border-radius:14px;padding:18px;text-align:center}
    .empty-title{font-weight:800;font-size:16px}
    .empty-sub{color:rgba(0,0,0,.55);font-size:13px;margin-top:4px}

    /* Tabulator – make it modern */
    #clearances-table .tabulator{border:0;border-radius:12px;overflow:hidden}
    #clearances-table .tabulator-header{border-bottom:1px solid rgba(0,0,0,.06);background:#fff}
    #clearances-table .tabulator-col{background:#fff}
    #clearances-table .tabulator-col-title{font-weight:700;color:rgba(0,0,0,.70)}
    #clearances-table .tabulator-row{border-bottom:1px solid rgba(0,0,0,.04)}
    #clearances-table .tabulator-row:hover{background:rgba(0,0,0,.02)}
    #clearances-table .tabulator-cell{padding:12px 10px}

    .status-pill{display:inline-flex;align-items:center;padding:.18rem .55rem;border-radius:999px;font-size:12px;font-weight:800;border:1px solid rgba(0,0,0,.08)}
    .pill-draft{background:rgba(108,117,125,.12);color:#495057}
    .pill-submitted{background:rgba(255,193,7,.18);color:#7a5b00}
    .pill-tr8_issued{background:rgba(13,202,240,.18);color:#055160}
    .pill-arrived{background:rgba(13,110,253,.12);color:#084298}
    .pill-offloaded{background:rgba(25,135,84,.12);color:#0f5132}
    .pill-cancelled{background:rgba(220,53,69,.12);color:#842029}

    .mini-btn{padding:.22rem .55rem;font-size:12px;border-radius:10px;border:1px solid rgba(0,0,0,.14);background:#fff;text-decoration:none;color:inherit;font-weight:700}
    .mini-btn:hover{background:rgba(0,0,0,.03)}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableEl = document.getElementById('clearances-table');
    if (!tableEl || !window.Tabulator) return;

    function statusPill(status) {
        const s = (status || '').toString();
        const label = s ? s.replaceAll('_',' ').toUpperCase() : '—';
        const cls = 'pill-' + s;
        return `<span class="status-pill ${cls}">${label}</span>`;
    }

    const dataUrl = "{{ url('depot/compliance/clearances/data') }}";

    window.clearancesTable = new Tabulator(tableEl, {
        layout: "fitColumns",
        height: "520px",
        placeholder: "Loading…",
        pagination: true,
        paginationSize: 20,
        ajaxURL: dataUrl,
        ajaxParams: function () {
            return Object.fromEntries(new URLSearchParams(window.location.search).entries());
        },
        ajaxResponse: function(url, params, response){
            const rows = Array.isArray(response) ? response : (response.data || []);
            // show pretty empty state
            const empty = document.getElementById('empty-state');
            if (empty) empty.classList.toggle('d-none', rows.length !== 0);
            // last refreshed
            const lr = document.getElementById('last-refreshed');
            if (lr) lr.textContent = 'Last refreshed: ' + new Date().toLocaleString();
            return rows;
        },
        columns: [
            {title: "Status", field: "status", width: 150, formatter: (c) => statusPill(c.getValue())},
            {title: "Client", field: "client_name", minWidth: 200},
            {title: "Truck", field: "truck_number", minWidth: 140},
            {title: "Trailer", field: "trailer_number", minWidth: 140},
            {title: "Loaded @20°C", field: "loaded_20_l", hozAlign:"right", formatter:"money", formatterParams:{precision: 3}},
            {title: "TR8", field: "tr8_number", minWidth: 140},
            {title: "Border", field: "border_point", minWidth: 140},
            {title: "Submitted", field: "submitted_at", minWidth: 170},
            {title: "Issued", field: "tr8_issued_at", minWidth: 170},
            {title: "Updated by", field: "updated_by_name", minWidth: 170},
            {title: "Age", field: "age_human", minWidth: 120},
            {
                title: "",
                field: "id",
                hozAlign: "right",
                width: 110,
                formatter: function(cell){
                    const row = cell.getRow().getData();
                    const openUrl = `{{ url('depot/compliance/clearances') }}/${row.id}`;
                    return `<a class="mini-btn" href="${openUrl}">Open</a>`;
                }
            },
        ],
    });

    document.getElementById('export-xlsx')?.addEventListener('click', function () {
        window.clearancesTable.download("xlsx", "compliance_clearances.xlsx");
    });

    document.getElementById('export-pdf')?.addEventListener('click', function () {
        window.clearancesTable.download("pdf", "compliance_clearances.pdf", {
            orientation: "landscape",
            title: "Compliance Clearances",
        });
    });
});
</script>
@endpush