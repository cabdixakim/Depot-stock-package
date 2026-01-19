@extends('depot-stock::layouts.app')

@section('content')
@php
    use Carbon\Carbon;

    // Safe defaults so the view never explodes if controller hasn't wired stats yet.
    $qs = request()->query();

    $stats = $stats ?? [
        'total' => $stats['total'] ?? null,
        'draft' => $stats['draft'] ?? null,
        'submitted' => $stats['submitted'] ?? null,
        'tr8_issued' => $stats['tr8_issued'] ?? null,
        'arrived' => $stats['arrived'] ?? null,
        'offloaded' => $stats['offloaded'] ?? null,
        'cancelled' => $stats['cancelled'] ?? null,
        // attention
        'stuck_submitted' => $stats['stuck_submitted'] ?? null,
        'stuck_tr8_issued' => $stats['stuck_tr8_issued'] ?? null,
        'missing_tr8_number' => $stats['missing_tr8_number'] ?? null,
        'missing_docs' => $stats['missing_docs'] ?? null,
    ];

    // Thresholds (tweak later)
    $submittedStaleHours = $submittedStaleHours ?? 24; // > 24h in "submitted" = attention
    $tr8IssuedStaleHours = $tr8IssuedStaleHours ?? 24; // > 24h in "tr8_issued" and not arrived = attention

    // If controller passes attention rows, great. If not, keep empty.
    $attention = $attention ?? collect();

    // Helper
    $badgeClass = function ($status) {
        return match ($status) {
            'draft' => 'bg-secondary',
            'submitted' => 'bg-warning text-dark',
            'tr8_issued' => 'bg-info text-dark',
            'arrived' => 'bg-primary',
            'offloaded' => 'bg-success',
            'cancelled' => 'bg-danger',
            default => 'bg-light text-dark'
        };
    };

    $statusLabel = function ($status) {
        return strtoupper(str_replace('_', ' ', (string)$status));
    };

    // Preserve filters for export links
    $exportXlsxUrl = route('depot.compliance.clearances.export_xlsx', $qs) ?? null;
    $exportPdfUrl  = route('depot.compliance.clearances.export_pdf', $qs) ?? null;
@endphp

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h3 class="mb-0">Compliance — Clearances</h3>
        <small class="text-muted">Focus board: what needs attention, not just a list.</small>
    </div>

    <div class="d-flex gap-2">
        {{-- Export buttons (server-side endpoints; wire later if not yet created) --}}
        <a class="btn btn-outline-secondary btn-sm"
           href="{{ route('depot.compliance.clearances.export_xlsx', request()->query()) }}">
            Export Excel
        </a>
        <a class="btn btn-outline-secondary btn-sm"
           href="{{ route('depot.compliance.clearances.export_pdf', request()->query()) }}">
            Export PDF
        </a>

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
                    <div>Active = Draft + Submitted + TR8 Issued + Arrived</div>
                    <div class="fw-semibold">
                        {{ ($stats['draft'] ?? 0) + ($stats['submitted'] ?? 0) + ($stats['tr8_issued'] ?? 0) + ($stats['arrived'] ?? 0) ?: '—' }}
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
                    These are waiting for TR8 issuance. Prioritise chasing agents/border.
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
                    Invoice / Delivery note / TR8 missing. Upload for audit readiness.
                </div>
            </div>
        </div>
    </div>

    {{-- Optional: Attention list --}}
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
                                <td><span class="badge {{ $badgeClass($c->status) }}">{{ $statusLabel($c->status) }}</span></td>
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
                @foreach(($clients ?? []) as $c)
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

{{-- MAIN LIST --}}
<div class="border rounded bg-white">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
        <div class="fw-semibold">Clearances</div>
        <small class="text-muted">
            Showing {{ method_exists($clearances, 'firstItem') ? ($clearances->firstItem() ?? 0) : 0 }}
            –
            {{ method_exists($clearances, 'lastItem') ? ($clearances->lastItem() ?? 0) : 0 }}
            of {{ method_exists($clearances, 'total') ? $clearances->total() : '—' }}
        </small>
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Status</th>
                    <th>Client</th>
                    <th>Truck / Trailer</th>
                    <th class="text-end">Loaded @20°C</th>
                    <th>TR8</th>
                    <th>Border</th>
                    <th>Submitted</th>
                    <th>Issued</th>
                    <th>Updated by</th>
                    <th>Age</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clearances as $c)
                    @php
                        $updatedAt = $c->updated_at ? Carbon::parse($c->updated_at) : null;
                        $ageHuman = $updatedAt ? $updatedAt->diffForHumans(null, true) : '—';

                        // Attention flags (purely visual)
                        $isSubmittedStale = ($c->status === 'submitted' && $c->submitted_at && Carbon::parse($c->submitted_at)->diffInHours(now()) > $submittedStaleHours);
                        $isTr8IssuedStale = ($c->status === 'tr8_issued' && $c->tr8_issued_at && Carbon::parse($c->tr8_issued_at)->diffInHours(now()) > $tr8IssuedStaleHours && empty($c->arrived_at));
                        $rowWarn = $isSubmittedStale || $isTr8IssuedStale;
                        $updatedBy = $c->updated_by_name ?? $c->last_action_by_name ?? '—';
                    @endphp

                    <tr @class(['table-warning' => $rowWarn])>
                        <td>
                            <span class="badge {{ $badgeClass($c->status) }}">
                                {{ $statusLabel($c->status) }}
                            </span>
                            @if($isSubmittedStale)
                                <div class="small text-danger mt-1">Late issuance</div>
                            @endif
                            @if($isTr8IssuedStale)
                                <div class="small text-danger mt-1">Late arrival</div>
                            @endif
                        </td>

                        <td>{{ $c->client->name ?? '—' }}</td>

                        <td>
                            <div class="fw-semibold">{{ $c->truck_number }}</div>
                            <div class="text-muted small">{{ $c->trailer_number ?: '—' }}</div>
                        </td>

                        <td class="text-end">
                            {{ is_null($c->loaded_20_l) ? '—' : number_format((float)$c->loaded_20_l, 3) }}
                        </td>

                        <td>
                            <div class="fw-semibold">{{ $c->tr8_number ?: '—' }}</div>
                            <div class="text-muted small">{{ $c->tr8_issued_at ? Carbon::parse($c->tr8_issued_at)->format('Y-m-d H:i') : '' }}</div>
                        </td>

                        <td>{{ $c->border_point ?: '—' }}</td>

                        <td class="small">
                            {{ $c->submitted_at ? Carbon::parse($c->submitted_at)->format('Y-m-d H:i') : '—' }}
                        </td>

                        <td class="small">
                            {{ $c->tr8_issued_at ? Carbon::parse($c->tr8_issued_at)->format('Y-m-d H:i') : '—' }}
                        </td>

                        <td>{{ $updatedBy }}</td>

                        <td class="small">{{ $ageHuman }}</td>

                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a class="btn btn-outline-secondary btn-sm"
                                   href="{{ route('depot.compliance.clearances.show', $c) }}">
                                    Open
                                </a>

                                @if(auth()->user()->hasRole('admin|compliance'))
                                    @if($c->status === 'draft')
                                        <form method="POST" action="{{ route('depot.compliance.clearances.submit', $c) }}">
                                            @csrf
                                            <button class="btn btn-warning btn-sm">Submit</button>
                                        </form>
                                    @endif

                                    @if($c->status === 'submitted')
                                        <a class="btn btn-success btn-sm"
                                           href="{{ route('depot.compliance.clearances.show', $c) }}#issue-tr8">
                                            Issue TR8
                                        </a>
                                    @endif

                                    @if($c->status === 'tr8_issued')
                                        <form method="POST" action="{{ route('depot.compliance.clearances.arrive', $c) }}">
                                            @csrf
                                            <button class="btn btn-primary btn-sm">Arrived</button>
                                        </form>
                                    @endif

                                    @if(in_array($c->status, ['draft','submitted','tr8_issued','arrived'], true))
                                        <form method="POST" action="{{ route('depot.compliance.clearances.cancel', $c) }}"
                                              onsubmit="return confirm('Cancel this clearance?');">
                                            @csrf
                                            <button class="btn btn-outline-danger btn-sm">Cancel</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">
                            No clearances found for the current filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-3">
        @if(method_exists($clearances, 'links'))
            {{ $clearances->withQueryString()->links() }}
        @endif
    </div>
</div>
@endsection