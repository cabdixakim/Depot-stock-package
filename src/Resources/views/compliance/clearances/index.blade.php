@extends('depot-stock::layouts.app')

@section('content')
@php
    use Carbon\Carbon;

    // Safe defaults so the view never explodes if controller hasn't wired stats yet.
    $stats = $stats ?? [
        'total' => null,
        'draft' => null,
        'submitted' => null,
        'tr8_issued' => null,
        'arrived' => null,
        'offloaded' => null,
        'cancelled' => null,
        'stuck_submitted' => null,
        'stuck_tr8_issued' => null,
        'missing_tr8_number' => null,
        'missing_docs' => null,
    ];

    $submittedStaleHours = $submittedStaleHours ?? 24;
    $tr8IssuedStaleHours = $tr8IssuedStaleHours ?? 24;

    $attention = $attention ?? collect();
    $clients = $clients ?? collect();
@endphp

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h3 class="mb-0">Compliance — Clearances</h3>
        <small class="text-muted">Focus board: what needs attention, not just a list.</small>
    </div>

    <div class="d-flex gap-2">
        {{-- ✅ Client-side export buttons --}}
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

{{-- SUMMARY STRIP --}}
<div class="row g-2 mb-3">
    <div class="col-12 col-md">
        <div class="p-3 border rounded bg-white">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="text-muted small">Total</div>
                    <div class="h5 mb-0">{{ $stats['total'] ?? '—' }}</div>
                </div>
                <div class="text-muted small text-end">
                    <div>Active</div>
                    <div class="fw-semibold">
                        {{ (($stats['draft'] ?? 0) + ($stats['submitted'] ?? 0) + ($stats['tr8_issued'] ?? 0) + ($stats['arrived'] ?? 0)) ?: '—' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md">
        <div class="p-3 border rounded bg-white">
            <div class="text-muted small">Draft</div>
            <div class="h5 mb-0">{{ $stats['draft'] ?? '—' }}</div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="p-3 border rounded bg-white">
            <div class="text-muted small">Submitted</div>
            <div class="h5 mb-0">{{ $stats['submitted'] ?? '—' }}</div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="p-3 border rounded bg-white">
            <div class="text-muted small">TR8 Issued</div>
            <div class="h5 mb-0">{{ $stats['tr8_issued'] ?? '—' }}</div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="p-3 border rounded bg-white">
            <div class="text-muted small">Arrived</div>
            <div class="h5 mb-0">{{ $stats['arrived'] ?? '—' }}</div>
        </div>
    </div>
</div>

{{-- ATTENTION / ALERTS --}}
<div class="mb-3">
    <div class="d-flex align-items-center justify-content-between">
        <h5 class="mb-2">Needs attention</h5>
        <small class="text-muted">
            Thresholds: Submitted &gt; {{ $submittedStaleHours }}h, TR8 Issued &gt; {{ $tr8IssuedStaleHours }}h (not arrived).
        </small>
    </div>

    <div class="row g-2">
        <div class="col-12 col-md-6">
            <div class="border rounded bg-white p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="fw-semibold">Stuck in Submitted</div>
                    <span class="badge bg-warning text-dark">{{ $stats['stuck_submitted'] ?? '—' }}</span>
                </div>
                <div class="text-muted small mt-1">
                    Waiting for TR8 issuance. Prioritise chasing agents/border.
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="border rounded bg-white p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="fw-semibold">TR8 Issued but not Arrived</div>
                    <span class="badge bg-info text-dark">{{ $stats['stuck_tr8_issued'] ?? '—' }}</span>
                </div>
                <div class="text-muted small mt-1">
                    TR8 done; trucks should be moving. Track delays / dispatch follow-ups.
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="border rounded bg-white p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="fw-semibold">Missing TR8 Number</div>
                    <span class="badge bg-danger">{{ $stats['missing_tr8_number'] ?? '—' }}</span>
                </div>
                <div class="text-muted small mt-1">
                    TR8 issued status without number is a data risk. Fix immediately.
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="border rounded bg-white p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="fw-semibold">Missing Documents</div>
                    <span class="badge bg-danger">{{ $stats['missing_docs'] ?? '—' }}</span>
                </div>
                <div class="text-muted small mt-1">
                    Missing invoice / delivery note / TR8 uploads. Upload for audit readiness.
                </div>
            </div>
        </div>
    </div>

    @if($attention->count())
        <div class="border rounded bg-white mt-2">
            <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
                <div class="fw-semibold">Attention queue</div>
                <small class="text-muted">{{ $attention->count() }} item(s)</small>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Status</th>
                            <th>Client</th>
                            <th>Truck</th>
                            <th>TR8</th>
                            <th>Age</th>
                            <th class="text-end">Open</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attention as $c)
                            @php
                                $age = $c->updated_at ? Carbon::parse($c->updated_at)->diffForHumans(null, true) : '—';
                            @endphp
                            <tr>
                                <td><span class="badge bg-secondary">{{ strtoupper(str_replace('_',' ', $c->status)) }}</span></td>
                                <td>{{ $c->client->name ?? '—' }}</td>
                                <td class="fw-semibold">{{ $c->truck_number }}</td>
                                <td>{{ $c->tr8_number ?? '—' }}</td>
                                <td>{{ $age }}</td>
                                <td class="text-end">
                                    <a class="btn btn-outline-secondary btn-sm"
                                       href="{{ route('depot.compliance.clearances.show', $c) }}">
                                        Open
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

{{-- FILTERS --}}
<div class="border rounded bg-white p-3 mb-3">
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
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ strtoupper(str_replace('_',' ', $s)) }}</option>
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

{{-- MAIN LIST (Tabulator container) --}}
<div class="border rounded bg-white">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
        <div class="fw-semibold">Clearances</div>
        <small class="text-muted">Tip: filter above, then export.</small>
    </div>

    <div class="p-2">
        <div id="clearances-table"></div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Make Tabulator feel less “tabulator-y” */
    #clearances-table .tabulator {
        border: 0;
        border-radius: 10px;
    }
    #clearances-table .tabulator-header {
        border-bottom: 1px solid rgba(0,0,0,.06);
    }
    #clearances-table .tabulator-row {
        border-bottom: 1px solid rgba(0,0,0,.04);
    }
    #clearances-table .tabulator-row:hover {
        background: rgba(0,0,0,.02);
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .15rem .55rem;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
        border: 1px solid rgba(0,0,0,.08);
    }
    .pill-draft { background: rgba(108,117,125,.12); color: #495057; }
    .pill-submitted { background: rgba(255,193,7,.18); color: #7a5b00; }
    .pill-tr8_issued { background: rgba(13,202,240,.18); color: #055160; }
    .pill-arrived { background: rgba(13,110,253,.12); color: #084298; }
    .pill-offloaded { background: rgba(25,135,84,.12); color: #0f5132; }
    .pill-cancelled { background: rgba(220,53,69,.12); color: #842029; }

    .mini-btn {
        padding: .15rem .45rem;
        font-size: 12px;
        border-radius: 8px;
        border: 1px solid rgba(0,0,0,.12);
        background: white;
        text-decoration: none;
        color: inherit;
    }
    .mini-btn:hover { background: rgba(0,0,0,.03); }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableEl = document.getElementById('clearances-table');
    if (!tableEl || !window.Tabulator) return;

    function statusPill(status) {
        const s = (status || '').toString();
        const cls = 'pill-' + s;
        const label = s ? s.replaceAll('_', ' ').toUpperCase() : '—';
        return `<span class="status-pill ${cls}">${label}</span>`;
    }

    // ✅ IMPORTANT: DO NOT use route() here (it will 500 if route not defined).
    // We call the URL directly. You WILL define this route next.
    const dataUrl = "{{ url('depot/compliance/clearances/data') }}";

    window.clearancesTable = new Tabulator(tableEl, {
        layout: "fitColumns",
        placeholder: "No clearances found for the current filters.",
        pagination: true,
        paginationSize: 20,
        ajaxURL: dataUrl,
        ajaxParams: function () {
            return Object.fromEntries(new URLSearchParams(window.location.search).entries());
        },
        ajaxResponse: function(url, params, response){
            if (Array.isArray(response)) return response;
            return response.data || [];
        },
        columns: [
            {title: "Status", field: "status", width: 150, formatter: (cell) => statusPill(cell.getValue())},
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
                title: "Open",
                field: "id",
                hozAlign: "right",
                width: 120,
                formatter: function(cell){
                    const row = cell.getRow().getData();
                    const openUrl = `{{ url('depot/compliance/clearances') }}/${row.id}`;
                    return `<a class="mini-btn" href="${openUrl}">Open</a>`;
                }
            },
        ],
    });

    // ✅ Client-side exports (filters affect export)
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