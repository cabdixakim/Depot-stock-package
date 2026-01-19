@extends('depot-stock::layouts.app')

@section('content')
@php
    // Safe defaults
    $clients = $clients ?? collect();
    $clearances = $clearances ?? null;

    // Page-only counts (fast + always available)
    $pageCounts = $clearances
        ? $clearances->getCollection()->groupBy('status')->map(fn($g) => $g->count())
        : collect();

    $count = function(string $status) use ($pageCounts) {
        return (int) ($pageCounts[$status] ?? 0);
    };

    // "Attention" (page-scoped). Later we can switch to true global stats.
    $attention = [
        'stuck_submitted' => 0,
        'missing_tr8_number' => 0,
        'tr8_issued_not_arrived' => 0,
        'missing_docs' => 0,
    ];

    if ($clearances) {
        $rows = $clearances->getCollection();

        $attention['missing_tr8_number'] = $rows->where('status', 'tr8_issued')->whereNull('tr8_number')->count();
        $attention['tr8_issued_not_arrived'] = $rows->where('status', 'tr8_issued')->whereNull('arrived_at')->count();

        // "stuck_submitted" = submitted and older than 24h (page only)
        $attention['stuck_submitted'] = $rows->where('status', 'submitted')
            ->filter(fn($c) => $c->submitted_at && \Carbon\Carbon::parse($c->submitted_at)->lt(now()->subHours(24)))
            ->count();

        // missing docs (page only) - requires documents relation loaded or count column
        // We'll keep it 0 until we wire it properly in controller.
        $attention['missing_docs'] = 0;
    }

    $statusMeta = [
        'draft'      => ['label' => 'Draft',      'cls' => 'pill-neutral', 'hint' => 'Not submitted'],
        'submitted'  => ['label' => 'Submitted',  'cls' => 'pill-amber',   'hint' => 'Waiting TR8'],
        'tr8_issued' => ['label' => 'TR8 Issued',  'cls' => 'pill-blue',    'hint' => 'In transit'],
        'arrived'    => ['label' => 'Arrived',    'cls' => 'pill-green',   'hint' => 'Ready to offload'],
        'offloaded'  => ['label' => 'Offloaded',  'cls' => 'pill-green2',  'hint' => 'Completed'],
        'cancelled'  => ['label' => 'Cancelled',  'cls' => 'pill-red',     'hint' => 'Stopped'],
    ];

    $statusPill = function($status) use ($statusMeta) {
        $status = (string) $status;
        $m = $statusMeta[$status] ?? ['label' => strtoupper(str_replace('_',' ', $status)), 'cls' => 'pill-neutral', 'hint' => ''];
        return '<span class="status-pill '.$m['cls'].'">'.$m['label'].'</span>';
    };
@endphp

<div class="compliance-shell">

    {{-- TOP BAR --}}
    <div class="topbar">
        <div class="topbar-left">
            <div class="title-wrap">
                <div class="title">Compliance</div>
                <div class="subtitle">Clearances Command Centre</div>
            </div>

            <div class="quick-meta">
                <span class="dot"></span>
                <span class="muted small">Guides you to what needs attention first.</span>
            </div>
        </div>

        <div class="topbar-right">
            <div class="btn-group">
                <button type="button" class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><button class="dropdown-item" id="export-xlsx" type="button">Export Excel (XLSX)</button></li>
                    <li><button class="dropdown-item" id="export-pdf" type="button">Export PDF</button></li>
                </ul>
            </div>

            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filtersPanel">
                Filters
            </button>

            @if(auth()->user()->hasRole('admin|compliance'))
                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createClearanceModal">
                    + New Clearance
                </button>
            @endif
        </div>
    </div>

    {{-- STATUS PILL STRIP --}}
    <div class="pill-strip">
        @foreach(['draft','submitted','tr8_issued','arrived','offloaded','cancelled'] as $s)
            @php $m = $statusMeta[$s]; @endphp
            <a class="pill-chip {{ request('status') === $s ? 'active' : '' }}"
               href="{{ route('depot.compliance.clearances.index', array_merge(request()->all(), ['status' => $s])) }}">
                <span class="chip-label">{{ $m['label'] }}</span>
                <span class="chip-count">{{ $count($s) }}</span>
                <span class="chip-hint d-none d-md-inline">{{ $m['hint'] }}</span>
            </a>
        @endforeach

        <a class="pill-chip ghost {{ empty(request('status')) ? 'active' : '' }}"
           href="{{ route('depot.compliance.clearances.index', array_merge(request()->all(), ['status' => null])) }}">
            <span class="chip-label">All</span>
            <span class="chip-count">{{ $clearances ? $clearances->count() : 0 }}</span>
            <span class="chip-hint d-none d-md-inline">This page</span>
        </a>
    </div>

    {{-- ATTENTION --}}
    @php
        $hasAttention = collect($attention)->sum() > 0;
    @endphp

    <div class="attention-row">
        <div class="attention-panel">
            <div class="panel-head">
                <div>
                    <div class="panel-title">Priority Inbox</div>
                    <div class="panel-sub">These need chasing now (page-scoped for now).</div>
                </div>
                <div class="panel-badge {{ $hasAttention ? 'danger' : 'ok' }}">
                    {{ $hasAttention ? 'Action needed' : 'All calm' }}
                </div>
            </div>

            <div class="attention-grid">
                <div class="a-card a-amber">
                    <div class="a-top">
                        <div class="a-title">Stuck Submitted</div>
                        <div class="a-num">{{ (int)$attention['stuck_submitted'] }}</div>
                    </div>
                    <div class="a-sub">Submitted &gt; 24h (no TR8 yet)</div>
                </div>

                <div class="a-card a-red">
                    <div class="a-top">
                        <div class="a-title">Missing TR8 #</div>
                        <div class="a-num">{{ (int)$attention['missing_tr8_number'] }}</div>
                    </div>
                    <div class="a-sub">TR8 issued but number empty</div>
                </div>

                <div class="a-card a-blue">
                    <div class="a-top">
                        <div class="a-title">Issued, Not Arrived</div>
                        <div class="a-num">{{ (int)$attention['tr8_issued_not_arrived'] }}</div>
                    </div>
                    <div class="a-sub">TR8 issued but truck not marked arrived</div>
                </div>

                <div class="a-card a-neutral">
                    <div class="a-top">
                        <div class="a-title">Missing Docs</div>
                        <div class="a-num">{{ (int)$attention['missing_docs'] }}</div>
                    </div>
                    <div class="a-sub">Invoice / DN / TR8 uploads missing</div>
                </div>
            </div>
        </div>
    </div>

    {{-- FILTERS (COLLAPSIBLE) --}}
    <div class="collapse @if(request()->query()) show @endif" id="filtersPanel">
        <div class="filters-card">
            <form method="GET" action="{{ route('depot.compliance.clearances.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small muted mb-1">Client</label>
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
                    <label class="form-label small muted mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach(array_keys($statusMeta) as $s)
                            <option value="{{ $s }}" @selected(request('status') === $s)>
                                {{ $statusMeta[$s]['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label small muted mb-1">Search</label>
                    <input class="form-control form-control-sm" name="q" value="{{ request('q') }}"
                           placeholder="Truck, trailer, TR8, invoice, delivery note...">
                </div>

                <div class="col-6 col-md-1">
                    <label class="form-label small muted mb-1">From</label>
                    <input type="date" class="form-control form-control-sm" name="from" value="{{ request('from') }}">
                </div>

                <div class="col-6 col-md-1">
                    <label class="form-label small muted mb-1">To</label>
                    <input type="date" class="form-control form-control-sm" name="to" value="{{ request('to') }}">
                </div>

                <div class="col-12 col-md-1 d-grid">
                    <button class="btn btn-primary btn-sm">Apply</button>
                </div>

                <div class="col-12">
                    <a class="btn btn-link btn-sm p-0" href="{{ route('depot.compliance.clearances.index') }}">Reset filters</a>
                </div>
            </form>
        </div>
    </div>

    {{-- LIST --}}
    <div class="list-card">
        <div class="list-head">
            <div>
                <div class="list-title">Clearances</div>
                <div class="list-sub">Clean list view. Actions live in “Open”.</div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="clearances-html-table">
                <thead class="table-light">
                    <tr>
                        <th style="width:130px;">Status</th>
                        <th>Client</th>
                        <th style="width:140px;">Truck</th>
                        <th style="width:140px;">Trailer</th>
                        <th style="width:140px;" class="text-end">Loaded @20°C</th>
                        <th style="width:140px;">TR8</th>
                        <th style="width:140px;">Border</th>
                        <th style="width:170px;">Updated</th>
                        <th style="width:110px;" class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($clearances as $c)
                    <tr>
                        <td>{!! $statusPill($c->status) !!}</td>
                        <td class="fw-semibold">{{ $c->client->name ?? '—' }}</td>
                        <td>{{ $c->truck_number }}</td>
                        <td>{{ $c->trailer_number ?? '—' }}</td>
                        <td class="text-end">{{ $c->loaded_20_l !== null ? number_format((float)$c->loaded_20_l, 3) : '—' }}</td>
                        <td>{{ $c->tr8_number ?? '—' }}</td>
                        <td>{{ $c->border_point ?? '—' }}</td>
                        <td class="text-muted small">
                            {{ $c->updated_at ? \Carbon\Carbon::parse($c->updated_at)->format('Y-m-d H:i') : '—' }}
                        </td>
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm"
                               href="{{ route('depot.compliance.clearances.show', $c) }}">
                                Open
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <div class="empty-title">No clearances found</div>
                                <div class="empty-sub">Try filters, or create a new clearance.</div>
                                @if(auth()->user()->hasRole('admin|compliance'))
                                    <button class="btn btn-primary btn-sm mt-2" type="button" data-bs-toggle="modal" data-bs-target="#createClearanceModal">
                                        + New Clearance
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($clearances)
            <div class="p-3">
                {{ $clearances->withQueryString()->links() }}
            </div>
        @endif
    </div>

</div>

{{-- Create Clearance Modal (separate blade) --}}
@include('depot-stock::compliance.clearances.partials.create-modal', ['clients' => $clients])

@endsection

@push('styles')
<style>
    .compliance-shell{max-width:1200px;margin:0 auto}

    .topbar{display:flex;gap:16px;align-items:flex-start;justify-content:space-between;margin-bottom:14px}
    .title-wrap .title{font-weight:900;font-size:18px;line-height:1}
    .title-wrap .subtitle{color:rgba(0,0,0,.55);font-size:13px;margin-top:4px}
    .quick-meta{display:flex;align-items:center;gap:8px;margin-top:10px}
    .dot{width:8px;height:8px;border-radius:999px;background:rgba(25,135,84,.65)}
    .muted{color:rgba(0,0,0,.55)}
    .small{font-size:12px}

    .pill-strip{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 14px}
    .pill-chip{display:inline-flex;align-items:center;gap:10px;padding:10px 12px;border-radius:999px;
        border:1px solid rgba(0,0,0,.10);background:#fff;text-decoration:none;color:inherit}
    .pill-chip:hover{background:rgba(0,0,0,.02)}
    .pill-chip.active{border-color:rgba(13,110,253,.40);box-shadow:0 0 0 3px rgba(13,110,253,.10)}
    .pill-chip.ghost{background:rgba(0,0,0,.02)}
    .chip-label{font-weight:800}
    .chip-count{font-weight:900;background:rgba(0,0,0,.06);padding:4px 8px;border-radius:999px;min-width:34px;text-align:center}
    .chip-hint{color:rgba(0,0,0,.50);font-size:12px}

    .attention-row{margin-bottom:14px}
    .attention-panel{border:1px solid rgba(0,0,0,.10);background:#fff;border-radius:16px;padding:14px}
    .panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px}
    .panel-title{font-weight:900}
    .panel-sub{color:rgba(0,0,0,.55);font-size:12px;margin-top:2px}
    .panel-badge{font-size:12px;font-weight:900;border-radius:999px;padding:6px 10px;border:1px solid rgba(0,0,0,.10)}
    .panel-badge.ok{background:rgba(25,135,84,.10);color:#0f5132;border-color:rgba(25,135,84,.20)}
    .panel-badge.danger{background:rgba(220,53,69,.10);color:#842029;border-color:rgba(220,53,69,.20)}

    .attention-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
    @media (max-width: 992px){ .attention-grid{grid-template-columns:repeat(2,minmax(0,1fr));} }
    @media (max-width: 520px){ .attention-grid{grid-template-columns:1fr;} }

    .a-card{border-radius:14px;border:1px solid rgba(0,0,0,.08);padding:12px;background:#fff}
    .a-top{display:flex;align-items:center;justify-content:space-between}
    .a-title{font-weight:900;font-size:12px;color:rgba(0,0,0,.65)}
    .a-num{font-weight:1000;font-size:22px}
    .a-sub{margin-top:6px;font-size:12px;color:rgba(0,0,0,.55)}
    .a-amber{background:linear-gradient(135deg, rgba(255,193,7,.14), rgba(255,255,255,0))}
    .a-red{background:linear-gradient(135deg, rgba(220,53,69,.14), rgba(255,255,255,0))}
    .a-blue{background:linear-gradient(135deg, rgba(13,110,253,.12), rgba(255,255,255,0))}
    .a-neutral{background:linear-gradient(135deg, rgba(0,0,0,.05), rgba(255,255,255,0))}

    .filters-card{border:1px solid rgba(0,0,0,.10);background:#fff;border-radius:16px;padding:14px;margin-bottom:14px}

    .list-card{border:1px solid rgba(0,0,0,.10);background:#fff;border-radius:16px;overflow:hidden}
    .list-head{padding:14px;border-bottom:1px solid rgba(0,0,0,.06)}
    .list-title{font-weight:900}
    .list-sub{color:rgba(0,0,0,.55);font-size:12px;margin-top:2px}

    .status-pill{display:inline-flex;align-items:center;padding:.18rem .6rem;border-radius:999px;font-size:12px;font-weight:900;border:1px solid rgba(0,0,0,.10)}
    .pill-neutral{background:rgba(0,0,0,.05);color:rgba(0,0,0,.70)}
    .pill-amber{background:rgba(255,193,7,.18);color:#7a5b00}
    .pill-blue{background:rgba(13,110,253,.14);color:#084298}
    .pill-green{background:rgba(25,135,84,.14);color:#0f5132}
    .pill-green2{background:rgba(25,135,84,.22);color:#0f5132}
    .pill-red{background:rgba(220,53,69,.14);color:#842029}

    .empty-state{padding:18px;text-align:center;border:1px dashed rgba(0,0,0,.16);border-radius:14px;background:rgba(0,0,0,.02)}
    .empty-title{font-weight:1000}
    .empty-sub{color:rgba(0,0,0,.55);font-size:12px;margin-top:4px}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Client-side export of the CURRENT visible table rows (current filtered page).
    // Uses globals from your app.js: window.XLSX and window.jsPDF
    function tableToAoA(table) {
        const rows = [];
        table.querySelectorAll('tr').forEach(tr => {
            const cols = [];
            tr.querySelectorAll('th,td').forEach(td => {
                // strip extra whitespace
                cols.push((td.innerText || '').replace(/\s+/g,' ').trim());
            });
            rows.push(cols);
        });
        return rows;
    }

    document.getElementById('export-xlsx')?.addEventListener('click', function () {
        const table = document.getElementById('clearances-html-table');
        if (!table || !window.XLSX) return;

        const aoa = tableToAoA(table);
        const ws = XLSX.utils.aoa_to_sheet(aoa);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Clearances');
        XLSX.writeFile(wb, 'compliance_clearances.xlsx');
    });

    document.getElementById('export-pdf')?.addEventListener('click', function () {
        const table = document.getElementById('clearances-html-table');
        if (!table || !window.jsPDF) return;

        const doc = new window.jsPDF({orientation:'landscape', unit:'pt', format:'a4'});
        doc.text('Compliance Clearances', 40, 35);

        // AutoTable should exist due to jspdf-autotable import in app.js
        if (doc.autoTable) {
            const head = [];
            const body = [];

            const ths = table.querySelectorAll('thead th');
            head.push(Array.from(ths).map(th => (th.innerText || '').trim()));

            table.querySelectorAll('tbody tr').forEach(tr => {
                const tds = tr.querySelectorAll('td');
                if (!tds.length) return;
                body.push(Array.from(tds).map(td => (td.innerText || '').replace(/\s+/g,' ').trim()));
            });

            doc.autoTable({
                head, body,
                startY: 55,
                styles: {fontSize: 8, cellPadding: 4},
                headStyles: {fillColor: [245,245,245], textColor: 20},
            });
        }

        doc.save('compliance_clearances.pdf');
    });

});
</script>
@endpush