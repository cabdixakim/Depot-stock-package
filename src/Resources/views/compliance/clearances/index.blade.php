@extends('depot-stock::layouts.app')

@section('content')
@php
    // ------------------------------------------------------------
    // SAFE DEFAULTS (avoid ugly "undefined" states)
    // ------------------------------------------------------------
    $clients = $clients ?? collect();
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

    // ------------------------------------------------------------
    // ROLE GATING (YOUR User::hasRole expects STRING)
    // ------------------------------------------------------------
    $u = auth()->user();
    $canCreate =
        $u && (
            $u->hasRole('admin') ||
            $u->hasRole('owner') ||
            $u->hasRole('compliance')
        );

    // ------------------------------------------------------------
    // FILTERS (kept server-side for now, no JS route dependencies)
    // ------------------------------------------------------------
    $selectedClient = request('client_id', '');
    $selectedStatus = request('status', '');
    $search = request('q', '');

    // if you haven't built date filtering yet, keep fields UI-only
    $from = request('from', '');
    $to = request('to', '');

    $lastRefreshed = now()->format('n/j/Y, g:i:s A');

    // nice label map
    $statusLabels = [
        '' => 'All statuses',
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'tr8_issued' => 'TR8 Issued',
        'arrived' => 'Arrived',
        'cancelled' => 'Cancelled',
    ];

    $pillStatuses = [
        ['key' => '', 'label' => 'All', 'count' => $stats['total'] ?? 0, 'tone' => 'neutral'],
        ['key' => 'draft', 'label' => 'Draft', 'count' => $stats['draft'] ?? 0, 'tone' => 'slate'],
        ['key' => 'submitted', 'label' => 'Submitted', 'count' => $stats['submitted'] ?? 0, 'tone' => 'amber'],
        ['key' => 'tr8_issued', 'label' => 'TR8 Issued', 'count' => $stats['tr8_issued'] ?? 0, 'tone' => 'blue'],
        ['key' => 'arrived', 'label' => 'Arrived', 'count' => $stats['arrived'] ?? 0, 'tone' => 'green'],
        ['key' => 'cancelled', 'label' => 'Cancelled', 'count' => $stats['cancelled'] ?? 0, 'tone' => 'red'],
    ];
@endphp

<style>
    /* ===== Premium, minimal, app-matching (no Tailwind dependency) ===== */
    .c-wrap{max-width:1200px;margin:0 auto;padding:16px 18px 28px;}
    .c-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:14px;}
    .c-title{font-size:22px;font-weight:700;letter-spacing:-0.02em;margin:0;}
    .c-sub{color:#6b7280;font-size:13px;margin-top:4px;max-width:720px;line-height:1.35;}
    .c-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end;}
    .c-meta{color:#6b7280;font-size:12px;white-space:nowrap;}

    .c-btn{border:1px solid rgba(0,0,0,.08);background:#fff;border-radius:10px;padding:8px 12px;font-weight:600;font-size:13px;display:inline-flex;align-items:center;gap:8px;cursor:pointer;}
    .c-btn:hover{border-color:rgba(0,0,0,.16);}
    .c-btn-primary{background:#111827;color:#fff;border-color:#111827;}
    .c-btn-primary:hover{filter:brightness(.96);}
    .c-btn-ghost{background:rgba(17,24,39,.04);}
    .c-btn-icon{width:30px;height:30px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(0,0,0,.08);background:#fff;}
    .c-btn-icon:hover{border-color:rgba(0,0,0,.16);}

    .c-card{background:#fff;border:1px solid rgba(0,0,0,.07);border-radius:14px;box-shadow:0 1px 0 rgba(0,0,0,.02);}

    .c-strip{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:10px 12px;margin:10px 0 14px;}
    .pill{border-radius:999px;border:1px solid rgba(0,0,0,.08);padding:7px 10px;display:flex;gap:8px;align-items:center;background:#fff;cursor:pointer;user-select:none;}
    .pill:hover{border-color:rgba(0,0,0,.16);}
    .pill.active{border-color:rgba(17,24,39,.65);box-shadow:0 0 0 3px rgba(17,24,39,.08);}
    .pill .k{font-size:12px;font-weight:700;color:#111827;}
    .pill .v{font-size:12px;color:#6b7280;font-weight:700;background:rgba(0,0,0,.05);padding:2px 8px;border-radius:999px;}

    .tone-neutral{background:rgba(17,24,39,.03);}
    .tone-slate{background:rgba(100,116,139,.10);}
    .tone-amber{background:rgba(245,158,11,.12);}
    .tone-blue{background:rgba(59,130,246,.12);}
    .tone-green{background:rgba(34,197,94,.12);}
    .tone-red{background:rgba(239,68,68,.10);}

    .c-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:12px;margin-bottom:14px;}
    @media (max-width: 980px){.c-grid{grid-template-columns:1fr;}}

    .c-attn{padding:14px;}
    .c-attn h3{margin:0 0 8px;font-size:14px;font-weight:800;}
    .attn-row{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;}
    @media (max-width: 980px){.attn-row{grid-template-columns:repeat(2,1fr);} }
    @media (max-width: 520px){.attn-row{grid-template-columns:1fr;} }
    .attn{border-radius:12px;border:1px solid rgba(0,0,0,.06);padding:10px 12px;background:rgba(17,24,39,.02);}
    .attn .t{font-size:12px;color:#111827;font-weight:800;}
    .attn .n{font-size:22px;font-weight:900;margin-top:4px;line-height:1;}
    .attn .h{font-size:12px;color:#6b7280;margin-top:6px;line-height:1.25;}

    .c-filters{padding:14px;}
    .c-filters h3{margin:0 0 10px;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:space-between;}
    .f-row{display:grid;grid-template-columns:1fr 1fr 1.2fr .9fr .9fr;gap:10px;align-items:end;}
    @media (max-width: 980px){.f-row{grid-template-columns:1fr 1fr;}}
    .f{display:flex;flex-direction:column;gap:6px;}
    .f label{font-size:12px;color:#6b7280;font-weight:700;}
    .f input,.f select{border:1px solid rgba(0,0,0,.10);border-radius:10px;padding:9px 10px;font-size:13px;background:#fff;outline:none;}
    .f input:focus,.f select:focus{border-color:rgba(17,24,39,.55);box-shadow:0 0 0 3px rgba(17,24,39,.08);}
    .f-actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin-top:10px;}

    .c-list{padding:14px;margin-top:12px;}
    .c-list-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:10px;}
    .c-list-head h3{margin:0;font-size:14px;font-weight:900;}
    .c-list-head p{margin:3px 0 0;color:#6b7280;font-size:12px;}
    .c-list-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end;}
    .c-small{font-size:12px;color:#6b7280;}
    .table{width:100%;border-collapse:separate;border-spacing:0;}
    .table thead th{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#6b7280;font-weight:800;text-align:left;padding:10px 10px;border-bottom:1px solid rgba(0,0,0,.07);background:rgba(17,24,39,.02);}
    .table tbody td{padding:12px 10px;border-bottom:1px solid rgba(0,0,0,.06);font-size:13px;vertical-align:middle;}
    .table tbody tr:hover td{background:rgba(17,24,39,.02);}
    .badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:5px 10px;font-size:12px;font-weight:800;border:1px solid rgba(0,0,0,.08);background:#fff;}
    .dot{width:8px;height:8px;border-radius:999px;background:#9ca3af;}
    .dot.draft{background:#64748b;}
    .dot.submitted{background:#f59e0b;}
    .dot.tr8_issued{background:#3b82f6;}
    .dot.arrived{background:#22c55e;}
    .dot.cancelled{background:#ef4444;}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;}
    .muted{color:#6b7280;font-size:12px;}
    .row-actions{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;}
    .link{font-weight:800;color:#111827;text-decoration:none;}
    .link:hover{text-decoration:underline;}
    .empty{padding:18px;border:1px dashed rgba(0,0,0,.15);border-radius:12px;background:rgba(17,24,39,.02);text-align:center;color:#6b7280;}
</style>

<div class="c-wrap">

    {{-- Header --}}
    <div class="c-head">
        <div>
            <h1 class="c-title">Compliance</h1>
            <div class="c-sub">Clearances &amp; TR8 tracking. Chase what’s stuck, fix missing TR8/docs, and keep trucks moving.</div>
        </div>

        <div class="c-actions">
            <div class="c-meta">Last refreshed: {{ $lastRefreshed }}</div>

            {{-- Export buttons (client-side later; for now do nothing but exist) --}}
            <button type="button" class="c-btn c-btn-ghost" id="export-xlsx">Export Excel</button>
            <button type="button" class="c-btn c-btn-ghost" id="export-pdf">Export PDF</button>

            @if($canCreate)
                <button type="button" class="c-btn c-btn-primary" data-bs-toggle="modal" data-bs-target="#createClearanceModal">
                    + New Clearance
                </button>
            @endif
        </div>
    </div>

    {{-- Status pills --}}
    <div class="c-card c-strip">
        @foreach($pillStatuses as $p)
            @php
                $isActive = (string)$selectedStatus === (string)$p['key'];
                $toneClass = 'tone-' . ($p['tone'] ?? 'neutral');
            @endphp
            <div class="pill {{ $toneClass }} {{ $isActive ? 'active' : '' }}"
                 onclick="document.getElementById('status').value='{{ $p['key'] }}'; document.getElementById('filtersForm').submit();">
                <span class="k">{{ $p['label'] }}</span>
                <span class="v">{{ $p['count'] ?? 0 }}</span>
            </div>
        @endforeach
    </div>

    {{-- Attention + Filters --}}
    <div class="c-grid">

        {{-- Needs attention --}}
        <div class="c-card c-attn">
            <h3>Needs attention</h3>
            <div class="muted" style="margin-bottom:10px;">
                Fast flags: overdue stages, missing TR8/docs. Treat this like a task inbox.
            </div>

            <div class="attn-row">
                <div class="attn">
                    <div class="t">Stuck in Submitted</div>
                    <div class="n">{{ $stats['stuck_submitted'] ?? 0 }}</div>
                    <div class="h">Chase border/agent. Submitted &gt; 24h.</div>
                </div>
                <div class="attn">
                    <div class="t">TR8 issued, not arrived</div>
                    <div class="n">{{ $stats['stuck_tr8_issued'] ?? 0 }}</div>
                    <div class="h">Chase truck/dispatch. Issued &gt; 24h.</div>
                </div>
                <div class="attn">
                    <div class="t">Missing TR8 number</div>
                    <div class="n">{{ $stats['missing_tr8_number'] ?? 0 }}</div>
                    <div class="h">Data risk (can’t reconcile clearance).</div>
                </div>
                <div class="attn">
                    <div class="t">Missing documents</div>
                    <div class="n">{{ $stats['missing_documents'] ?? 0 }}</div>
                    <div class="h">Audit risk (attach invoice/delivery/TR8).</div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="c-card c-filters">
            <h3>
                <span>Filters</span>
                <span class="c-small">Affects list &amp; exports</span>
            </h3>

            <form id="filtersForm" method="GET" action="{{ route('depot.compliance.clearances.index') }}">
                <div class="f-row">
                    <div class="f">
                        <label for="client_id">Client</label>
                        <select id="client_id" name="client_id">
                            <option value="">All clients</option>
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}" @selected((string)$selectedClient === (string)$c->id)>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="f">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            @foreach($statusLabels as $k => $label)
                                <option value="{{ $k }}" @selected((string)$selectedStatus === (string)$k)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="f">
                        <label for="q">Search</label>
                        <input id="q" name="q" value="{{ $search }}" placeholder="Truck, trailer, TR8, invoice, border…" />
                    </div>

                    <div class="f">
                        <label for="from">From</label>
                        <input id="from" name="from" value="{{ $from }}" placeholder="mm/dd/yyyy" />
                    </div>

                    <div class="f">
                        <label for="to">To</label>
                        <input id="to" name="to" value="{{ $to }}" placeholder="mm/dd/yyyy" />
                    </div>
                </div>

                <div class="f-actions">
                    <button class="c-btn c-btn-primary" type="submit">Apply</button>
                    <a class="c-btn" href="{{ route('depot.compliance.clearances.index') }}">Reset</a>
                </div>
            </form>
        </div>

    </div>

    {{-- List --}}
    <div class="c-card c-list">
        <div class="c-list-head">
            <div>
                <h3>Clearances</h3>
                <p>High density list, fast actions. This is where compliance lives.</p>
            </div>

            <div class="c-list-actions">
                <div class="c-small">
                    @if($clearances && method_exists($clearances, 'total'))
                        Showing {{ $clearances->count() }} of {{ $clearances->total() }}
                    @else
                        —
                    @endif
                </div>
            </div>
        </div>

        <div style="overflow:auto;border-radius:12px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Client</th>
                        <th>Truck</th>
                        <th>Trailer</th>
                        <th>Loaded @20°C</th>
                        <th>TR8</th>
                        <th>Border</th>
                        <th>Submitted</th>
                        <th>Issued</th>
                        <th>Updated by</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $rows = $clearances ? $clearances : collect();
                    @endphp

                    @forelse($rows as $cl)
                        @php
                            $st = $cl->status ?? 'draft';
                            $dotClass = in_array($st, ['draft','submitted','tr8_issued','arrived','cancelled']) ? $st : 'draft';

                            // These may not exist yet on your model; keep null-safe.
                            $submittedAt = $cl->submitted_at ?? null;
                            $issuedAt = $cl->tr8_issued_at ?? null;

                            // Updated by - if you later add relation, swap here.
                            $updatedBy = $cl->updated_by_name ?? ($cl->updated_by ?? $cl->updated_by_id ?? null);
                        @endphp

                        <tr>
                            <td>
                                <span class="badge">
                                    <span class="dot {{ $dotClass }}"></span>
                                    {{ $statusLabels[$st] ?? ucfirst(str_replace('_',' ', $st)) }}
                                </span>
                            </td>
                            <td>{{ $cl->client->name ?? '—' }}</td>
                            <td class="mono">{{ $cl->truck_number ?? '—' }}</td>
                            <td class="mono">{{ $cl->trailer_number ?? '—' }}</td>
                            <td class="mono">
                                @if(!is_null($cl->loaded_20_l))
                                    {{ number_format((float)$cl->loaded_20_l, 0) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="mono">{{ $cl->tr8_number ?? '—' }}</td>
                            <td>{{ $cl->border_point ?? '—' }}</td>
                            <td class="muted">{{ $submittedAt ? \Carbon\Carbon::parse($submittedAt)->format('n/j/Y') : '—' }}</td>
                            <td class="muted">{{ $issuedAt ? \Carbon\Carbon::parse($issuedAt)->format('n/j/Y') : '—' }}</td>
                            <td class="muted">{{ $updatedBy ?? '—' }}</td>

                            <td>
                                <div class="row-actions">
                                    <a class="c-btn" href="{{ route('depot.compliance.clearances.show', $cl) }}">Open</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">
                                <div class="empty">
                                    No clearances found for the selected filters.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($clearances && method_exists($clearances, 'links'))
            <div style="margin-top:12px;">
                {{ $clearances->withQueryString()->links() }}
            </div>
        @endif
    </div>

</div>

{{-- Create Clearance Modal --}}
@if($canCreate)
    @include('depot-stock::compliance.clearances._create_modal')
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Placeholder exports (client-side export wiring will be done when we move list to Tabulator properly)
    const x = document.getElementById('export-xlsx');
    const p = document.getElementById('export-pdf');

    if (x) x.addEventListener('click', () => alert('Export Excel: wired when list is Tabulator (client-side).'));
    if (p) p.addEventListener('click', () => alert('Export PDF: wired when list is Tabulator (client-side).'));
});
</script>
@endpush

@endsection