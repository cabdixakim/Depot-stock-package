@extends('depot-stock::layouts.app')

@section('content')
@php
    // Safe defaults (controller can later pass real $stats + $attention)
    $stats = $stats ?? [
        'total' => 0,
        'draft' => 0,
        'submitted' => 0,
        'tr8_issued' => 0,
        'arrived' => 0,
        'cancelled' => 0,
    ];

    $attention = $attention ?? [
        'stuck_submitted' => 0,
        'stuck_tr8_issued' => 0,
        'missing_tr8_number' => 0,
        'missing_docs' => 0,
    ];

    $canCreate = auth()->user()->hasRole('admin|compliance');
@endphp

<div class="compliance-wrap">

    {{-- TOP BAR --}}
    <div class="compliance-topbar">
        <div class="topbar-left">
            <div class="title-row">
                <div class="title">Compliance</div>
                <div class="subtitle">Clearances & TR8 tracking</div>
            </div>

            <div class="hint">
                Use the attention strip to chase what’s stuck. Use the list to update status, attach TR8, and manage documents.
            </div>
        </div>

        <div class="topbar-right">
            <div class="toolbar">
                <button type="button" class="btn btn-light btn-sm toolbtn" id="export-xlsx">
                    Export Excel
                </button>

                <button type="button" class="btn btn-light btn-sm toolbtn" id="export-pdf">
                    Export PDF
                </button>

                @if($canCreate)
                    <button type="button" class="btn btn-primary btn-sm toolbtn-primary" data-bs-toggle="modal" data-bs-target="#createClearanceModal">
                        + Create Clearance
                    </button>
                @endif
            </div>

            <div class="refresh-text text-muted small">
                <span id="last-refreshed">Last refreshed: —</span>
            </div>
        </div>
    </div>

    {{-- KPI PILLS --}}
    <div class="pill-row">
        <div class="pill pill-total">
            <div class="pill-label">Total</div>
            <div class="pill-value">{{ (int)($stats['total'] ?? 0) }}</div>
        </div>

        <div class="pill pill-draft">
            <div class="pill-label">Draft</div>
            <div class="pill-value">{{ (int)($stats['draft'] ?? 0) }}</div>
        </div>

        <div class="pill pill-submitted">
            <div class="pill-label">Submitted</div>
            <div class="pill-value">{{ (int)($stats['submitted'] ?? 0) }}</div>
        </div>

        <div class="pill pill-tr8">
            <div class="pill-label">TR8 Issued</div>
            <div class="pill-value">{{ (int)($stats['tr8_issued'] ?? 0) }}</div>
        </div>

        <div class="pill pill-arrived">
            <div class="pill-label">Arrived</div>
            <div class="pill-value">{{ (int)($stats['arrived'] ?? 0) }}</div>
        </div>

        <div class="pill pill-cancelled">
            <div class="pill-label">Cancelled</div>
            <div class="pill-value">{{ (int)($stats['cancelled'] ?? 0) }}</div>
        </div>
    </div>

    {{-- ATTENTION STRIP --}}
    <div class="attention-strip">
        <div class="attention-head">
            <div class="attention-title">Needs attention</div>
            <div class="attention-sub">Fast flags (overdue stages, missing TR8/docs). This should feel like a task inbox.</div>
        </div>

        <div class="attention-items">
            <div class="attention-card attention-amber">
                <div class="a-title">Stuck in Submitted</div>
                <div class="a-value">{{ (int)($attention['stuck_submitted'] ?? 0) }}</div>
                <div class="a-sub">Chase border/agent</div>
            </div>

            <div class="attention-card attention-blue">
                <div class="a-title">TR8 Issued, not arrived</div>
                <div class="a-value">{{ (int)($attention['stuck_tr8_issued'] ?? 0) }}</div>
                <div class="a-sub">Chase truck/dispatch</div>
            </div>

            <div class="attention-card attention-red">
                <div class="a-title">Missing TR8 number</div>
                <div class="a-value">{{ (int)($attention['missing_tr8_number'] ?? 0) }}</div>
                <div class="a-sub">Data risk</div>
            </div>

            <div class="attention-card attention-red">
                <div class="a-title">Missing documents</div>
                <div class="a-value">{{ (int)($attention['missing_docs'] ?? 0) }}</div>
                <div class="a-sub">Audit risk</div>
            </div>
        </div>
    </div>

    {{-- FILTERS --}}
    <div class="filters-card">
        <div class="filters-head">
            <div>
                <div class="filters-title">Filters</div>
                <div class="filters-sub">Filters affect the list and exports.</div>
            </div>
            <div class="filters-actions">
                <button class="btn btn-sm btn-outline-secondary" type="submit" form="clearanceFilters">Apply</button>
                <a class="btn btn-sm btn-light" href="{{ route('depot.compliance.clearances.index') }}">Reset</a>
            </div>
        </div>

        <form id="clearanceFilters" method="GET" action="{{ route('depot.compliance.clearances.index') }}" class="filters-grid">
            <div class="f-item">
                <label class="f-label">Client</label>
                <select name="client_id" class="form-select form-select-sm">
                    <option value="">All clients</option>
                    @foreach(($clients ?? []) as $c)
                        <option value="{{ $c->id }}" @selected((string)request('client_id') === (string)$c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="f-item">
                <label class="f-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    @foreach(['draft','submitted','tr8_issued','arrived','offloaded','cancelled'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ strtoupper(str_replace('_',' ', $s)) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="f-item">
                <label class="f-label">Search</label>
                <input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Truck, trailer, TR8, invoice, DN…">
            </div>

            <div class="f-item">
                <label class="f-label">From</label>
                <input type="date" class="form-control form-control-sm" name="from" value="{{ request('from') }}">
            </div>

            <div class="f-item">
                <label class="f-label">To</label>
                <input type="date" class="form-control form-control-sm" name="to" value="{{ request('to') }}">
            </div>
        </form>
    </div>

    {{-- LIST --}}
    <div class="list-card">
        <div class="list-head">
            <div>
                <div class="list-title">Clearances</div>
                <div class="list-sub">High density list, fast actions. This is where compliance lives.</div>
            </div>

            <div class="list-meta text-muted small">
                @if(isset($clearances))
                    Showing {{ $clearances->count() }} of {{ $clearances->total() }}
                @endif
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="clearancesExportTable">
                <thead class="table-head">
                    <tr>
                        <th>Status</th>
                        <th>Client</th>
                        <th>Truck</th>
                        <th>Trailer</th>
                        <th class="text-end">Loaded @20°C</th>
                        <th>TR8</th>
                        <th>Border</th>
                        <th>Submitted</th>
                        <th>Issued</th>
                        <th>Updated by</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($clearances ?? []) as $c)
                        @php
                            $status = $c->status ?? 'draft';
                            $pillClass = match($status) {
                                'draft' => 's-pill s-draft',
                                'submitted' => 's-pill s-submitted',
                                'tr8_issued' => 's-pill s-tr8',
                                'arrived' => 's-pill s-arrived',
                                'offloaded' => 's-pill s-offloaded',
                                'cancelled' => 's-pill s-cancelled',
                                default => 's-pill s-draft',
                            };

                            // These may not exist yet depending on your schema — safe fallbacks.
                            $clientName = $c->client->name ?? $c->client_name ?? '—';
                            $updatedBy = $c->updatedBy->name ?? $c->updated_by_name ?? ($c->createdBy->name ?? '—');
                        @endphp
                        <tr>
                            <td><span class="{{ $pillClass }}">{{ strtoupper(str_replace('_',' ', $status)) }}</span></td>
                            <td class="fw-semibold">{{ $clientName }}</td>
                            <td>{{ $c->truck_number ?? '—' }}</td>
                            <td>{{ $c->trailer_number ?? '—' }}</td>
                            <td class="text-end">{{ $c->loaded_20_l ?? '—' }}</td>
                            <td class="fw-semibold">{{ $c->tr8_number ?? '—' }}</td>
                            <td>{{ $c->border_point ?? '—' }}</td>
                            <td>{{ $c->submitted_at ?? '—' }}</td>
                            <td>{{ $c->tr8_issued_at ?? '—' }}</td>
                            <td>{{ $updatedBy }}</td>
                            <td class="text-end">
                                <a href="{{ route('depot.compliance.clearances.show', $c->id) }}" class="btn btn-sm btn-outline-primary">
                                    Open
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-5">
                                <div class="empty-state">
                                    <div class="empty-title">No clearances found</div>
                                    <div class="empty-sub">Adjust filters or create the first clearance.</div>
                                    @if($canCreate)
                                        <button type="button" class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#createClearanceModal">
                                            + Create Clearance
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($clearances) && method_exists($clearances, 'links'))
            <div class="p-3 border-top">
                {{ $clearances->withQueryString()->links() }}
            </div>
        @endif
    </div>

</div>

{{-- Create clearance modal (separate blade) --}}
@if($canCreate)
    @include('depot-stock::compliance.clearances.partials.create-modal')
@endif
@endsection

@push('styles')
<style>
    .compliance-wrap { padding-bottom: 24px; }

    /* Topbar */
    .compliance-topbar{
        display:flex; gap:16px; align-items:flex-start; justify-content:space-between;
        padding:14px 16px; border:1px solid rgba(0,0,0,.08); border-radius:16px; background:#fff;
        margin-bottom:12px;
    }
    .title{ font-weight:900; font-size:18px; letter-spacing:.2px; }
    .subtitle{ color:rgba(0,0,0,.55); font-size:12px; margin-top:1px; }
    .hint{ color:rgba(0,0,0,.55); font-size:12px; margin-top:6px; max-width:720px; }
    .toolbar{ display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap; }
    .toolbtn{ border:1px solid rgba(0,0,0,.12); border-radius:12px; padding:8px 10px; }
    .toolbtn-primary{ border-radius:12px; padding:8px 12px; font-weight:800; }
    .refresh-text{ text-align:right; margin-top:6px; }

    /* KPI pills */
    .pill-row{ display:flex; flex-wrap:wrap; gap:10px; margin-bottom:12px; }
    .pill{
        display:flex; align-items:center; justify-content:space-between;
        min-width: 160px; flex:1 1 160px;
        padding:10px 12px; border-radius:999px; border:1px solid rgba(0,0,0,.08); background:#fff;
    }
    .pill-label{ font-size:12px; color:rgba(0,0,0,.6); font-weight:800; }
    .pill-value{ font-size:18px; font-weight:900; }
    .pill-total{ background:linear-gradient(135deg, rgba(0,0,0,.03), rgba(0,0,0,0)); }
    .pill-draft{ background:linear-gradient(135deg, rgba(108,117,125,.18), rgba(255,255,255,0)); }
    .pill-submitted{ background:linear-gradient(135deg, rgba(255,193,7,.20), rgba(255,255,255,0)); }
    .pill-tr8{ background:linear-gradient(135deg, rgba(13,202,240,.20), rgba(255,255,255,0)); }
    .pill-arrived{ background:linear-gradient(135deg, rgba(25,135,84,.18), rgba(255,255,255,0)); }
    .pill-cancelled{ background:linear-gradient(135deg, rgba(220,53,69,.16), rgba(255,255,255,0)); }

    /* Attention */
    .attention-strip{
        border:1px solid rgba(0,0,0,.08); border-radius:16px; background:#fff;
        padding:12px 12px 10px; margin-bottom:12px;
    }
    .attention-head{ display:flex; gap:10px; align-items:baseline; justify-content:space-between; margin-bottom:10px; }
    .attention-title{ font-weight:900; }
    .attention-sub{ color:rgba(0,0,0,.55); font-size:12px; }
    .attention-items{ display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:10px; }
    @media (max-width: 992px){ .attention-items{ grid-template-columns:repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 520px){ .attention-items{ grid-template-columns:1fr; } }
    .attention-card{
        border-radius:14px; border:1px solid rgba(0,0,0,.08); padding:12px; background:#fff;
        min-height:82px;
    }
    .a-title{ font-size:12px; font-weight:900; color:rgba(0,0,0,.65); }
    .a-value{ font-size:20px; font-weight:900; margin-top:4px; }
    .a-sub{ font-size:12px; color:rgba(0,0,0,.55); margin-top:2px; }
    .attention-amber{ background:linear-gradient(135deg, rgba(255,193,7,.18), rgba(255,255,255,0)); }
    .attention-blue{ background:linear-gradient(135deg, rgba(13,110,253,.14), rgba(255,255,255,0)); }
    .attention-red{ background:linear-gradient(135deg, rgba(220,53,69,.14), rgba(255,255,255,0)); }

    /* Filters */
    .filters-card{
        border:1px solid rgba(0,0,0,.08); border-radius:16px; background:#fff;
        padding:12px; margin-bottom:12px;
    }
    .filters-head{ display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px; }
    .filters-title{ font-weight:900; }
    .filters-sub{ font-size:12px; color:rgba(0,0,0,.55); }
    .filters-grid{
        display:grid; grid-template-columns:2fr 1.2fr 2fr 1fr 1fr; gap:10px;
    }
    @media (max-width: 992px){ .filters-grid{ grid-template-columns:1fr 1fr; } }
    .f-label{ font-size:12px; font-weight:800; color:rgba(0,0,0,.6); margin-bottom:6px; }
    .f-item .form-control, .f-item .form-select{ border-radius:12px; }

    /* List */
    .list-card{
        border:1px solid rgba(0,0,0,.08); border-radius:16px; background:#fff; overflow:hidden;
    }
    .list-head{
        padding:12px 12px 10px; border-bottom:1px solid rgba(0,0,0,.06);
        display:flex; align-items:flex-end; justify-content:space-between; gap:12px;
    }
    .list-title{ font-weight:900; }
    .list-sub{ font-size:12px; color:rgba(0,0,0,.55); margin-top:2px; }

    .table-head th{
        font-size:12px; text-transform:uppercase; letter-spacing:.08em;
        color:rgba(0,0,0,.55);
        border-bottom:1px solid rgba(0,0,0,.06) !important;
        padding:12px 12px;
        background:linear-gradient(180deg, rgba(0,0,0,.02), rgba(0,0,0,0));
    }
    table td{ padding:12px 12px; }

    /* Status pill */
    .s-pill{
        display:inline-flex; align-items:center;
        padding:.25rem .6rem; border-radius:999px;
        font-size:12px; font-weight:900;
        border:1px solid rgba(0,0,0,.10);
    }
    .s-draft{ background:rgba(108,117,125,.12); color:#495057; }
    .s-submitted{ background:rgba(255,193,7,.18); color:#7a5b00; }
    .s-tr8{ background:rgba(13,202,240,.18); color:#055160; }
    .s-arrived{ background:rgba(13,110,253,.12); color:#084298; }
    .s-offloaded{ background:rgba(25,135,84,.12); color:#0f5132; }
    .s-cancelled{ background:rgba(220,53,69,.12); color:#842029; }

    .empty-state{ text-align:center; padding:10px; }
    .empty-title{ font-weight:900; font-size:16px; }
    .empty-sub{ font-size:12px; color:rgba(0,0,0,.55); margin-top:4px; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const lr = document.getElementById('last-refreshed');
    if (lr) lr.textContent = 'Last refreshed: ' + new Date().toLocaleString();

    // Client-side exports from the rendered HTML table (NO extra routes)
    const table = document.getElementById('clearancesExportTable');

    document.getElementById('export-xlsx')?.addEventListener('click', function () {
        if (!window.XLSX || !table) return alert('XLSX export not available.');
        const wb = XLSX.utils.table_to_book(table, { sheet: "Clearances" });
        XLSX.writeFile(wb, "compliance_clearances.xlsx");
    });

    document.getElementById('export-pdf')?.addEventListener('click', function () {
        if (!window.jspdf || !window.jsPDF || !table) return alert('PDF export not available.');
        const doc = new jsPDF({ orientation: "landscape" });

        // Extract table headings + rows
        const head = [];
        table.querySelectorAll('thead th').forEach(th => head.push(th.innerText.trim()));

        const body = [];
        table.querySelectorAll('tbody tr').forEach(tr => {
            const row = [];
            tr.querySelectorAll('td').forEach(td => row.push(td.innerText.trim()));
            // Skip the empty-state row which has colspan
            if (row.length > 2) body.push(row);
        });

        doc.text("Compliance — Clearances", 14, 12);
        doc.autoTable({
            head: [head],
            body: body,
            startY: 16,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [245,245,245] },
        });

        doc.save("compliance_clearances.pdf");
    });
});
</script>
@endpush