@extends('depot-stock::layouts.app')

@section('content')
@php
    $clients = $clients ?? collect();
    $stats = $stats ?? [
        'total' => 0, 'draft' => 0, 'submitted' => 0, 'tr8_issued' => 0, 'arrived' => 0, 'cancelled' => 0,
        'stuck_submitted' => 0, 'stuck_tr8_issued' => 0, 'missing_tr8_number' => 0, 'missing_docs' => 0,
    ];

    $canCreate = auth()->user()->hasRole('owner|admin|compliance');
@endphp

<div class="compliance-wrap">

    {{-- Header --}}
    <div class="c-head">
        <div>
            <div class="c-title">Compliance</div>
            <div class="c-subtitle">Clearances & TR8 tracking — chase what’s stuck, fix missing TR8/docs, and push trucks to offload.</div>
        </div>

        <div class="c-head-actions">
            <div class="c-refresh" id="last-refreshed">Last refreshed: —</div>
            @if($canCreate)
                <button class="btn btn-primary btn-sm c-btn" type="button" data-bs-toggle="modal" data-bs-target="#createClearanceModal">
                    + New Clearance
                </button>
            @endif
        </div>
    </div>

    {{-- Status strip (clickable pills) --}}
    <div class="c-strip">
        <button class="pill pill-neutral js-status" data-status="">
            <span>Total</span><strong>{{ (int)$stats['total'] }}</strong>
        </button>
        <button class="pill pill-draft js-status" data-status="draft">
            <span>Draft</span><strong>{{ (int)$stats['draft'] }}</strong>
        </button>
        <button class="pill pill-submitted js-status" data-status="submitted">
            <span>Submitted</span><strong>{{ (int)$stats['submitted'] }}</strong>
        </button>
        <button class="pill pill-tr8 js-status" data-status="tr8_issued">
            <span>TR8 Issued</span><strong>{{ (int)$stats['tr8_issued'] }}</strong>
        </button>
        <button class="pill pill-arrived js-status" data-status="arrived">
            <span>Arrived</span><strong>{{ (int)$stats['arrived'] }}</strong>
        </button>
        <button class="pill pill-cancelled js-status" data-status="cancelled">
            <span>Cancelled</span><strong>{{ (int)$stats['cancelled'] }}</strong>
        </button>
    </div>

    {{-- Attention cards --}}
    <div class="c-attention">
        <div class="c-attention-head">
            <div class="c-attention-title">Needs attention</div>
            <div class="c-attention-sub">Fast flags (overdue stages, missing TR8/docs). Think “task inbox”.</div>
        </div>

        <div class="c-attention-grid">
            <div class="flag flag-amber">
                <div class="flag-title">Stuck in Submitted</div>
                <div class="flag-value">{{ (int)$stats['stuck_submitted'] }}</div>
                <div class="flag-sub">Chase border/agent</div>
            </div>
            <div class="flag flag-blue">
                <div class="flag-title">TR8 Issued, not arrived</div>
                <div class="flag-value">{{ (int)$stats['stuck_tr8_issued'] }}</div>
                <div class="flag-sub">Chase truck/dispatch</div>
            </div>
            <div class="flag flag-red">
                <div class="flag-title">Missing TR8 number</div>
                <div class="flag-value">{{ (int)$stats['missing_tr8_number'] }}</div>
                <div class="flag-sub">Data risk</div>
            </div>
            <div class="flag flag-red2">
                <div class="flag-title">Missing documents</div>
                <div class="flag-value">{{ (int)$stats['missing_docs'] }}</div>
                <div class="flag-sub">Audit risk</div>
            </div>
        </div>
    </div>

    {{-- Filters (overhaul) --}}
    <div class="c-filters">
        <div class="c-filters-head">
            <div>
                <div class="c-filters-title">Filters</div>
                <div class="c-filters-sub">Filters affect the list and exports.</div>
            </div>

            <div class="c-filters-actions">
                <button type="button" class="btn btn-outline-secondary btn-sm c-btn" id="resetFilters">Reset</button>
                <button type="button" class="btn btn-primary btn-sm c-btn" id="applyFilters">Apply</button>
            </div>
        </div>

        <div class="c-filters-body">
            <div class="f-row">
                <div class="f-group">
                    <label class="f-label">Client</label>
                    <select class="f-control" id="filterClient">
                        <option value="">All clients</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" @selected((string)request('client_id') === (string)$c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="f-group">
                    <label class="f-label">Status</label>
                    <select class="f-control" id="filterStatus">
                        <option value="">All</option>
                        @foreach(['draft','submitted','tr8_issued','arrived','offloaded','cancelled'] as $s)
                            <option value="{{ $s }}" @selected(request('status') === $s)>{{ strtoupper(str_replace('_',' ', $s)) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="f-group f-grow">
                    <label class="f-label">Search</label>
                    <input class="f-control" id="filterQ" value="{{ request('q') }}" placeholder="Truck, trailer, TR8, invoice, border…">
                </div>

                <div class="f-group">
                    <label class="f-label">From</label>
                    <input type="date" class="f-control" id="filterFrom" value="{{ request('from') }}">
                </div>

                <div class="f-group">
                    <label class="f-label">To</label>
                    <input type="date" class="f-control" id="filterTo" value="{{ request('to') }}">
                </div>
            </div>
        </div>
    </div>

    {{-- List header bar (exports moved here + new clearance here too) --}}
    <div class="c-list-head">
        <div>
            <div class="c-list-title">Clearances</div>
            <div class="c-list-sub">High density list, fast actions. This is where compliance lives.</div>
        </div>

        <div class="c-list-actions">
            <div class="c-list-count" id="rowsCount">Showing 0</div>

            <button id="export-xlsx" type="button" class="btn btn-outline-secondary btn-sm c-btn">
                Export Excel
            </button>
            <button id="export-pdf" type="button" class="btn btn-outline-secondary btn-sm c-btn">
                Export PDF
            </button>

            @if($canCreate)
                <button class="btn btn-primary btn-sm c-btn" type="button" data-bs-toggle="modal" data-bs-target="#createClearanceModal">
                    + New Clearance
                </button>
            @endif
        </div>
    </div>

    {{-- Tabulator --}}
    <div class="c-table">
        <div id="clearances-table"></div>
        <div id="emptyState" class="c-empty d-none">
            <div class="c-empty-title">No clearances found</div>
            <div class="c-empty-sub">Change filters or create a clearance.</div>
            @if($canCreate)
                <button class="btn btn-primary btn-sm c-btn mt-2" type="button" data-bs-toggle="modal" data-bs-target="#createClearanceModal">
                    + New Clearance
                </button>
            @endif
        </div>
    </div>

</div>

{{-- Create modal (separate blade) --}}
@include('depot-stock::compliance.clearances._create_modal')
@endsection

@push('styles')
<style>
    .compliance-wrap{max-width:1240px;margin:0 auto}

    /* Header */
    .c-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:14px}
    .c-title{font-size:22px;font-weight:900;letter-spacing:-.02em}
    .c-subtitle{color:rgba(0,0,0,.55);font-size:13px;margin-top:4px}
    .c-head-actions{display:flex;align-items:center;gap:10px}
    .c-refresh{font-size:12px;color:rgba(0,0,0,.55)}

    /* Strip pills */
    .c-strip{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}
    .pill{border:1px solid rgba(0,0,0,.08);border-radius:999px;background:#fff;padding:10px 12px;display:flex;gap:10px;align-items:center}
    .pill span{font-size:12px;color:rgba(0,0,0,.55);font-weight:700}
    .pill strong{font-weight:900}
    .pill-neutral{background:linear-gradient(135deg, rgba(0,0,0,.03), rgba(0,0,0,0))}
    .pill-draft{background:linear-gradient(135deg, rgba(108,117,125,.14), rgba(255,255,255,0))}
    .pill-submitted{background:linear-gradient(135deg, rgba(255,193,7,.18), rgba(255,255,255,0))}
    .pill-tr8{background:linear-gradient(135deg, rgba(13,202,240,.18), rgba(255,255,255,0))}
    .pill-arrived{background:linear-gradient(135deg, rgba(13,110,253,.14), rgba(255,255,255,0))}
    .pill-cancelled{background:linear-gradient(135deg, rgba(220,53,69,.14), rgba(255,255,255,0))}
    .pill.active{outline:2px solid rgba(13,110,253,.35)}

    /* Attention */
    .c-attention{border:1px solid rgba(0,0,0,.08);border-radius:16px;background:#fff;padding:14px;margin-bottom:14px}
    .c-attention-head{display:flex;justify-content:space-between;gap:12px;align-items:end;margin-bottom:12px}
    .c-attention-title{font-weight:900}
    .c-attention-sub{font-size:12px;color:rgba(0,0,0,.55)}
    .c-attention-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
    @media (max-width: 992px){ .c-attention-grid{grid-template-columns:repeat(2,1fr);} }
    @media (max-width: 520px){ .c-attention-grid{grid-template-columns:1fr;} }

    .flag{border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:12px;min-height:88px}
    .flag-title{font-size:12px;font-weight:900;color:rgba(0,0,0,.65)}
    .flag-value{font-size:22px;font-weight:950;margin-top:6px}
    .flag-sub{font-size:12px;color:rgba(0,0,0,.55)}
    .flag-amber{background:linear-gradient(135deg, rgba(255,193,7,.18), rgba(255,255,255,0))}
    .flag-blue{background:linear-gradient(135deg, rgba(13,110,253,.14), rgba(255,255,255,0))}
    .flag-red{background:linear-gradient(135deg, rgba(220,53,69,.14), rgba(255,255,255,0))}
    .flag-red2{background:linear-gradient(135deg, rgba(220,53,69,.10), rgba(255,255,255,0))}

    /* Filters */
    .c-filters{border:1px solid rgba(0,0,0,.08);border-radius:16px;background:#fff;padding:14px;margin-bottom:14px}
    .c-filters-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:12px}
    .c-filters-title{font-weight:900}
    .c-filters-sub{font-size:12px;color:rgba(0,0,0,.55)}
    .c-filters-actions{display:flex;gap:8px}

    .f-row{display:flex;gap:10px;flex-wrap:wrap}
    .f-group{min-width:160px}
    .f-grow{flex:1;min-width:260px}
    .f-label{display:block;font-size:12px;color:rgba(0,0,0,.55);font-weight:800;margin-bottom:6px}
    .f-control{width:100%;border:1px solid rgba(0,0,0,.16);border-radius:12px;padding:10px 12px;background:#fff;outline:none}
    .f-control:focus{border-color:rgba(13,110,253,.55);box-shadow:0 0 0 3px rgba(13,110,253,.12)}

    /* List head */
    .c-list-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin:16px 0 10px 0}
    .c-list-title{font-weight:950;font-size:16px}
    .c-list-sub{font-size:12px;color:rgba(0,0,0,.55)}
    .c-list-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .c-list-count{font-size:12px;color:rgba(0,0,0,.55);font-weight:800;padding:6px 10px;border:1px solid rgba(0,0,0,.10);border-radius:999px;background:#fff}

    /* Buttons */
    .c-btn{border-radius:12px;font-weight:800}

    /* Table shell */
    .c-table{border:1px solid rgba(0,0,0,.08);border-radius:16px;background:#fff;padding:10px}
    #clearances-table .tabulator{border:0;border-radius:14px;overflow:hidden}
    #clearances-table .tabulator-header{border-bottom:1px solid rgba(0,0,0,.06);background:#fff}
    #clearances-table .tabulator-col-title{font-weight:900;color:rgba(0,0,0,.65);letter-spacing:.04em;font-size:12px}
    #clearances-table .tabulator-row{border-bottom:1px solid rgba(0,0,0,.04)}
    #clearances-table .tabulator-row:hover{background:rgba(0,0,0,.02)}
    #clearances-table .tabulator-cell{padding:12px 10px}

    .status-pill{display:inline-flex;align-items:center;padding:.18rem .55rem;border-radius:999px;font-size:12px;font-weight:950;border:1px solid rgba(0,0,0,.10)}
    .sp-draft{background:rgba(108,117,125,.12);color:#495057}
    .sp-submitted{background:rgba(255,193,7,.18);color:#7a5b00}
    .sp-tr8_issued{background:rgba(13,202,240,.18);color:#055160}
    .sp-arrived{background:rgba(13,110,253,.12);color:#084298}
    .sp-offloaded{background:rgba(25,135,84,.12);color:#0f5132}
    .sp-cancelled{background:rgba(220,53,69,.12);color:#842029}

    .c-empty{border:1px dashed rgba(0,0,0,.16);border-radius:14px;padding:18px;text-align:center;margin-top:10px}
    .c-empty-title{font-weight:950}
    .c-empty-sub{font-size:12px;color:rgba(0,0,0,.55);margin-top:4px}

    .mini{display:inline-flex;gap:8px;align-items:center}
    .mini a, .mini button{border-radius:12px;font-size:12px;font-weight:900;border:1px solid rgba(0,0,0,.14);padding:.25rem .55rem;background:#fff}
    .mini a:hover, .mini button:hover{background:rgba(0,0,0,.03)}
    .mini .danger{border-color:rgba(220,53,69,.35)}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.Tabulator) return;

    const csrf = @json(csrf_token());
    const canCreate = @json($canCreate);

    const els = {
        client: document.getElementById('filterClient'),
        status: document.getElementById('filterStatus'),
        q: document.getElementById('filterQ'),
        from: document.getElementById('filterFrom'),
        to: document.getElementById('filterTo'),
        apply: document.getElementById('applyFilters'),
        reset: document.getElementById('resetFilters'),
        rowsCount: document.getElementById('rowsCount'),
        refreshed: document.getElementById('last-refreshed'),
        empty: document.getElementById('emptyState'),
    };

    function currentParams(){
        return {
            client_id: els.client?.value || '',
            status: els.status?.value || '',
            q: els.q?.value || '',
            from: els.from?.value || '',
            to: els.to?.value || '',
        };
    }

    function setStripActive(status){
        document.querySelectorAll('.js-status').forEach(b => {
            b.classList.toggle('active', (b.dataset.status || '') === (status || ''));
        });
    }
    setStripActive(els.status?.value || '');

    // Tabulator
    const tableEl = document.getElementById('clearances-table');
    const dataUrl = "{{ route('depot.compliance.clearances.data') }}";

    function statusPill(status){
        const s = (status || '').toString();
        const label = s ? s.replaceAll('_',' ').toUpperCase() : '—';
        return `<span class="status-pill sp-${s}">${label}</span>`;
    }

    function actionButtons(row){
        const id = row.id;
        const status = row.status;

        const openUrl = `{{ url('depot/compliance/clearances') }}/${id}`;

        // server routes exist (POST):
        const submitUrl = `{{ url('depot/compliance/clearances') }}/${id}/submit`;
        const arriveUrl = `{{ url('depot/compliance/clearances') }}/${id}/arrive`;
        const cancelUrl = `{{ url('depot/compliance/clearances') }}/${id}/cancel`;

        // Only show actions that make sense for the status
        let html = `<div class="mini">
            <a href="${openUrl}">Open</a>
        `;

        // submit only from draft
        if (status === 'draft') {
            html += `
                <form method="POST" action="${submitUrl}" style="display:inline;">
                    <input type="hidden" name="_token" value="${csrf}">
                    <button type="submit">Submit</button>
                </form>
            `;
        }

        // arrive only from tr8_issued
        if (status === 'tr8_issued') {
            html += `
                <form method="POST" action="${arriveUrl}" style="display:inline;">
                    <input type="hidden" name="_token" value="${csrf}">
                    <button type="submit">Arrived</button>
                </form>
            `;
        }

        // cancel from anything except cancelled/offloaded
        if (status !== 'cancelled' && status !== 'offloaded') {
            html += `
                <form method="POST" action="${cancelUrl}" style="display:inline;" onsubmit="return confirm('Cancel this clearance?');">
                    <input type="hidden" name="_token" value="${csrf}">
                    <button type="submit" class="danger">Cancel</button>
                </form>
            `;
        }

        html += `</div>`;
        return html;
    }

    window.clearancesTable = new Tabulator(tableEl, {
        layout: "fitColumns",
        height: "560px",
        movableColumns: true,
        pagination: true,
        paginationSize: 20,
        ajaxURL: dataUrl,
        ajaxParams: currentParams,
        placeholder: "Loading…",
        rowClick: function(e, row){
            const d = row.getData();
            window.location.href = `{{ url('depot/compliance/clearances') }}/${d.id}`;
        },
        ajaxResponse: function(url, params, resp){
            const rows = Array.isArray(resp) ? resp : (resp.data || []);
            if (els.empty) els.empty.classList.toggle('d-none', rows.length !== 0);
            if (els.rowsCount) els.rowsCount.textContent = `Showing ${rows.length}`;
            if (els.refreshed) els.refreshed.textContent = `Last refreshed: ${new Date().toLocaleString()}`;
            return rows;
        },
        columns: [
            {title: "STATUS", field: "status", width: 140, formatter: c => statusPill(c.getValue())},
            {title: "CLIENT", field: "client_name", minWidth: 190},
            {title: "TRUCK", field: "truck_number", minWidth: 130},
            {title: "TRAILER", field: "trailer_number", minWidth: 130},
            {title: "LOADED @20°C", field: "loaded_20_l", hozAlign:"right", minWidth: 140, formatter:"money", formatterParams:{precision: 3}},
            {title: "TR8", field: "tr8_number", minWidth: 120},
            {title: "BORDER", field: "border_point", minWidth: 120},
            {title: "SUBMITTED", field: "submitted_at", minWidth: 160},
            {title: "ISSUED", field: "tr8_issued_at", minWidth: 160},
            {title: "UPDATED BY", field: "updated_by_name", minWidth: 160},
            {title: "AGE", field: "age_human", minWidth: 110},
            {title: "ACTION", field: "id", width: 260, headerSort:false, formatter: (cell) => actionButtons(cell.getRow().getData())},
        ],
    });

    // Apply / Reset
    function reload(){
        window.clearancesTable.setData(dataUrl, currentParams());
        setStripActive(els.status?.value || '');
        // update URL (nice UX)
        const p = currentParams();
        const qs = new URLSearchParams();
        Object.keys(p).forEach(k => { if (p[k]) qs.set(k, p[k]); });
        const newUrl = `${window.location.pathname}${qs.toString() ? ('?' + qs.toString()) : ''}`;
        window.history.replaceState({}, '', newUrl);
    }

    els.apply?.addEventListener('click', reload);

    els.reset?.addEventListener('click', function(){
        if (els.client) els.client.value = '';
        if (els.status) els.status.value = '';
        if (els.q) els.q.value = '';
        if (els.from) els.from.value = '';
        if (els.to) els.to.value = '';
        reload();
    });

    // status strip -> sets status filter and reloads
    document.querySelectorAll('.js-status').forEach(btn => {
        btn.addEventListener('click', function(){
            if (els.status) els.status.value = (btn.dataset.status || '');
            reload();
        });
    });

    // live search debounce
    let t = null;
    els.q?.addEventListener('input', function(){
        clearTimeout(t);
        t = setTimeout(reload, 350);
    });

    // exports (client-side only)
    document.getElementById('export-xlsx')?.addEventListener('click', function(){
        window.clearancesTable.download("xlsx", "compliance_clearances.xlsx");
    });

    document.getElementById('export-pdf')?.addEventListener('click', function(){
        window.clearancesTable.download("pdf", "compliance_clearances.pdf", {
            orientation: "landscape",
            title: "Compliance Clearances",
        });
    });

});
</script>
@endpush