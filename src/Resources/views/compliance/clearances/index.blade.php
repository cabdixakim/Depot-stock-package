@extends('depot-stock::layouts.app')

@section('content')
@php
    // -------------------------------
    // Safety defaults (avoid undefined)
    // -------------------------------
    $clients    = $clients ?? collect();
    $clearances = $clearances ?? null;

    // Controller SHOULD pass real stats.
    // If it doesn't, these will show 0 (by design).
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
    // Role gating (MATCH your app)
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

    $now = now();

    // Needs-attention totals (from controller stats)
    $attStuckSubmitted = (int)($stats['stuck_submitted'] ?? 0);
    $attStuckTr8       = (int)($stats['stuck_tr8_issued'] ?? 0);
    $attMissingTr8     = (int)($stats['missing_tr8_number'] ?? 0);
    $attMissingDocs    = (int)($stats['missing_documents'] ?? 0);
    $attTotal          = $attStuckSubmitted + $attStuckTr8 + $attMissingTr8 + $attMissingDocs;

    // Helper: build URL preserving existing filters + override status
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
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mt-6">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="p-5 sm:p-6">

                {{-- HEADER --}}
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
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        @if($canCreate)
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                                id="btnOpenCreateClearance"
                            >
                                <span class="text-base leading-none">+</span>
                                <span class="hidden sm:inline">New</span>
                            </button>
                        @endif

                        {{-- Needs attention (icon button + badge + anchored wide panel) --}}
                        <div class="relative" id="attWrap">
                            <button
                                type="button"
                                id="btnAttention"
                                class="group relative inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-3 py-2 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                                aria-haspopup="true"
                                aria-expanded="false"
                                title="Needs attention"
                            >
                                <svg class="h-5 w-5 text-gray-700 group-hover:text-gray-900" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 22a2.5 2.5 0 0 0 2.45-2H9.55A2.5 2.5 0 0 0 12 22Z" fill="currentColor" opacity=".9"/>
                                    <path d="M20 17H4c1.8-1.5 2.5-3.3 2.5-6V9.5C6.5 6.46 8.7 4 12 4s5.5 2.46 5.5 5.5V11c0 2.7.7 4.5 2.5 6Z"
                                          stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                </svg>

                                @if($attTotal > 0)
                                    <span class="absolute -top-2 -right-2 inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1.5 text-[11px] font-bold text-white shadow">
                                        {{ $attTotal }}
                                    </span>
                                @endif
                            </button>

                            {{-- ✅ WIDE, MODERN PANEL (3x wider) --}}
                            <div
                                id="attentionPanel"
                                class="hidden absolute right-0 mt-2 w-[56rem] max-w-[calc(100vw-2rem)] rounded-2xl border border-gray-200 bg-white shadow-2xl overflow-hidden z-40"
                            >
                                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gray-900 text-white">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M12 22a2.5 2.5 0 0 0 2.45-2H9.55A2.5 2.5 0 0 0 12 22Z" fill="currentColor" opacity=".9"/>
                                                <path d="M20 17H4c1.8-1.5 2.5-3.3 2.5-6V9.5C6.5 6.46 8.7 4 12 4s5.5 2.46 5.5 5.5V11c0 2.7.7 4.5 2.5 6Z"
                                                      stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900 leading-tight">Needs attention</div>
                                            <div class="text-[11px] text-gray-500 leading-tight">Tap an item to jump to the table and auto-filter.</div>
                                        </div>
                                    </div>

                                    <button type="button" class="rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" data-att-close="1">
                                        Close
                                    </button>
                                </div>

                                {{-- Grid of notif items (compact text, easier to click) --}}
                                <div class="p-3">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                        <a href="{{ $makeFilterUrl(['status' => 'submitted', '__att' => 'stuck_submitted']) }}#clearances"
                                           class="group rounded-2xl border border-gray-200 bg-white p-4 hover:bg-gray-50 hover:shadow-sm transition">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="text-xs font-semibold text-gray-900">Stuck in Submitted</div>
                                                    <div class="mt-1 text-[11px] text-gray-500">Waiting TR8 issuance • chase border/agent</div>
                                                </div>
                                                <div class="inline-flex items-center gap-2">
                                                    <span class="rounded-full bg-amber-50 px-2 py-1 text-[11px] font-bold text-amber-900 border border-amber-200">{{ $attStuckSubmitted }}</span>
                                                    <span class="text-gray-300 group-hover:text-gray-400">›</span>
                                                </div>
                                            </div>
                                        </a>

                                        <a href="{{ $makeFilterUrl(['status' => 'tr8_issued', '__att' => 'stuck_tr8_issued']) }}#clearances"
                                           class="group rounded-2xl border border-gray-200 bg-white p-4 hover:bg-gray-50 hover:shadow-sm transition">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="text-xs font-semibold text-gray-900">TR8 issued, not arrived</div>
                                                    <div class="mt-1 text-[11px] text-gray-500">TR8 done • chase truck/dispatch</div>
                                                </div>
                                                <div class="inline-flex items-center gap-2">
                                                    <span class="rounded-full bg-blue-50 px-2 py-1 text-[11px] font-bold text-blue-900 border border-blue-200">{{ $attStuckTr8 }}</span>
                                                    <span class="text-gray-300 group-hover:text-gray-400">›</span>
                                                </div>
                                            </div>
                                        </a>

                                        <a href="{{ $makeFilterUrl(['__att' => 'missing_tr8_number']) }}#clearances"
                                           class="group rounded-2xl border border-gray-200 bg-white p-4 hover:bg-gray-50 hover:shadow-sm transition">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="text-xs font-semibold text-gray-900">Missing TR8 number</div>
                                                    <div class="mt-1 text-[11px] text-gray-500">Data risk • fix immediately</div>
                                                </div>
                                                <div class="inline-flex items-center gap-2">
                                                    <span class="rounded-full bg-rose-50 px-2 py-1 text-[11px] font-bold text-rose-900 border border-rose-200">{{ $attMissingTr8 }}</span>
                                                    <span class="text-gray-300 group-hover:text-gray-400">›</span>
                                                </div>
                                            </div>
                                        </a>

                                        <a href="{{ $makeFilterUrl(['__att' => 'missing_documents']) }}#clearances"
                                           class="group rounded-2xl border border-gray-200 bg-white p-4 hover:bg-gray-50 hover:shadow-sm transition">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="text-xs font-semibold text-gray-900">Missing documents</div>
                                                    <div class="mt-1 text-[11px] text-gray-500">Audit risk • upload docs</div>
                                                </div>
                                                <div class="inline-flex items-center gap-2">
                                                    <span class="rounded-full bg-gray-50 px-2 py-1 text-[11px] font-bold text-gray-800 border border-gray-200">{{ $attMissingDocs }}</span>
                                                    <span class="text-gray-300 group-hover:text-gray-400">›</span>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- /Needs attention --}}
                    </div>
                </div>

                {{-- STATUS PILLS --}}
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @php
                        $pill = function($label, $count, $key, $tone) use ($makeFilterUrl) {
                            $href = $makeFilterUrl($key === '' ? ['status' => null] : ['status' => $key]);
                            $base = "inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold";
                            $toneClass = match($tone) {
                                'amber' => "border-amber-200 bg-amber-50 text-amber-900",
                                'blue' => "border-blue-200 bg-blue-50 text-blue-900",
                                'emerald' => "border-emerald-200 bg-emerald-50 text-emerald-900",
                                'rose' => "border-rose-200 bg-rose-50 text-rose-900",
                                default => "border-gray-200 bg-gray-50 text-gray-800",
                            };
                            $count = (int)$count;
                            return <<<HTML
                                <a href="{$href}" class="{$base} {$toneClass} hover:shadow-sm transition">
                                    <span>{$label}</span>
                                    <span class="inline-flex items-center justify-center rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-bold border border-black/5">{$count}</span>
                                </a>
                            HTML;
                        };
                    @endphp

                    {!! $pill('Total', (int)($stats['total'] ?? 0), '', 'gray') !!}
                    {!! $pill('Draft', (int)($stats['draft'] ?? 0), 'draft', 'gray') !!}
                    {!! $pill('Submitted', (int)($stats['submitted'] ?? 0), 'submitted', 'amber') !!}
                    {!! $pill('TR8 Issued', (int)($stats['tr8_issued'] ?? 0), 'tr8_issued', 'blue') !!}
                    {!! $pill('Arrived', (int)($stats['arrived'] ?? 0), 'arrived', 'emerald') !!}
                    {!! $pill('Cancelled', (int)($stats['cancelled'] ?? 0), 'cancelled', 'rose') !!}
                </div>

                {{-- FILTERS (more compact; aims to stay one line on desktop) --}}
                <form method="GET" class="mt-4 rounded-2xl border border-gray-200 bg-white p-3">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-2 items-end">
                        <div class="lg:col-span-3">
                            <label class="text-[11px] font-semibold text-gray-600">Client</label>
                            <select name="client_id" class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10">
                                <option value="">All clients</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}" @selected((string)$fClient === (string)$c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="text-[11px] font-semibold text-gray-600">Status</label>
                            <select name="status" class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10">
                                <option value="">All</option>
                                <option value="draft" @selected($fStatus==='draft')>Draft</option>
                                <option value="submitted" @selected($fStatus==='submitted')>Submitted</option>
                                <option value="tr8_issued" @selected($fStatus==='tr8_issued')>TR8 issued</option>
                                <option value="arrived" @selected($fStatus==='arrived')>Arrived</option>
                                <option value="cancelled" @selected($fStatus==='cancelled')>Cancelled</option>
                            </select>
                        </div>

                        <div class="lg:col-span-3">
                            <label class="text-[11px] font-semibold text-gray-600">Search</label>
                            <input name="q" value="{{ $fSearch }}" placeholder="Truck, trailer, TR8, invoice, border…"
                                   class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"/>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="text-[11px] font-semibold text-gray-600">From</label>
                            <input type="date" name="from" value="{{ $fFrom }}"
                                   class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"/>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="text-[11px] font-semibold text-gray-600">To</label>
                            <input type="date" name="to" value="{{ $fTo }}"
                                   class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"/>
                        </div>

                        <div class="lg:col-span-12 flex items-center justify-end gap-2 pt-1">
                            <a href="{{ url()->current() }}" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                                Reset
                            </a>
                            <button type="submit" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">
                                Apply
                            </button>
                        </div>
                    </div>
                </form>

                {{-- LIST HEADER + EXPORTS --}}
                <div id="clearances" class="mt-5 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">Clearances</div>
                        <div class="text-xs text-gray-500">Click a row to open. Use actions for workflow.</div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50" id="btnExportXlsx">
                            Export Excel
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50" id="btnExportPdf">
                            Export PDF
                        </button>
                    </div>
                </div>

                {{-- Tabulator container --}}
                <div class="mt-3 rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between">
                        <div class="text-xs text-gray-500">
                            Tip: remote pagination (server) — filters apply to exports too.
                        </div>
                        <div class="text-xs text-gray-400">
                            Showing results by page
                        </div>
                    </div>

                    <div class="p-3">
                        <div id="clearancesTable"></div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

{{-- Create modal --}}
@if($canCreate)
    @include('depot-stock::compliance.clearances._create_modal', ['clients' => $clients])
@endif

{{-- CONFIRM MODAL (pretty, replaces browser confirm) --}}
<div id="confirmActionModal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 px-4">
    <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-start justify-between gap-4">
            <div>
                <div id="confirmActionTitle" class="text-sm font-semibold text-gray-900">Confirm</div>
                <div id="confirmActionText" class="mt-1 text-xs text-gray-500">Are you sure?</div>
            </div>
            <button type="button" class="rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" data-close-modal="confirm">
                Close
            </button>
        </div>
        <div class="p-5 flex items-center justify-end gap-2">
            <button type="button" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50" data-close-modal="confirm">
                Cancel
            </button>
            <button type="button" id="confirmActionBtn" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                Confirm
            </button>
        </div>
    </div>
</div>

{{-- ISSUE TR8 MODAL (wide, clean) --}}
<div id="issueTr8Modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 px-4">
    <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-start justify-between gap-4">
            <div>
                <div class="text-sm font-semibold text-gray-900">Issue TR8</div>
                <div class="mt-1 text-xs text-gray-500">Enter TR8 details and optionally attach the document.</div>
            </div>
            <button type="button" class="rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" data-close-modal="issue_tr8">
                Close
            </button>
        </div>

        <div class="p-5">
            <form id="issueTr8Form" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" id="issueTr8Action" value="">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-[11px] font-semibold text-gray-600">TR8 number</label>
                        <input id="issueTr8Number" name="tr8_number" class="mt-1 w-full rounded-xl border-gray-200 text-sm focus:border-gray-900 focus:ring-gray-900/10" required />
                    </div>

                    <div>
                        <label class="text-[11px] font-semibold text-gray-600">Reference (optional)</label>
                        <input id="issueTr8Reference" name="tr8_reference" class="mt-1 w-full rounded-xl border-gray-200 text-sm focus:border-gray-900 focus:ring-gray-900/10" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-[11px] font-semibold text-gray-600">Attach TR8 document (optional)</label>
                        <input id="issueTr8Document" name="tr8_document" type="file" class="mt-1 block w-full text-sm" />
                    </div>
                </div>
            </form>
        </div>

        <div class="p-5 border-t border-gray-100 flex items-center justify-end gap-2">
            <button type="button" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50" data-close-modal="issue_tr8">
                Cancel
            </button>
            <button type="button" id="issueTr8Submit" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                Save TR8
            </button>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Make Tabulator feel native + give it a proper surface */
    #clearancesTable .tabulator {
        border: 0;
        border-radius: 16px;
        background: #ffffff;
    }
    #clearancesTable .tabulator-header {
        border-bottom: 1px solid rgba(0,0,0,.06);
        background: #fbfbfc;
    }
    #clearancesTable .tabulator-row {
        border-bottom: 1px solid rgba(0,0,0,.04);
        background: #ffffff;
    }
    #clearancesTable .tabulator-row:hover { background: rgba(0,0,0,.02); }

    /* Subtle status tint (safe, not complex) */
    #clearancesTable .tabulator-row.row-accent.accent-draft { background: #ffffff; }
    #clearancesTable .tabulator-row.row-accent.accent-submitted { background: rgba(245, 158, 11, 0.06); }
    #clearancesTable .tabulator-row.row-accent.accent-tr8_issued { background: rgba(59, 130, 246, 0.06); }
    #clearancesTable .tabulator-row.row-accent.accent-arrived { background: rgba(16, 185, 129, 0.06); }
    #clearancesTable .tabulator-row.row-accent.accent-cancelled { background: rgba(244, 63, 94, 0.05); }

    /* Action buttons inside table */
    .tbl-btn{
        padding: .35rem .6rem;
        border-radius: .75rem;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid rgba(0,0,0,.10);
        background: white;
        line-height: 1;
    }
    .tbl-btn:hover{ background: rgba(0,0,0,.03); }
    .tbl-btn-dark{ background:#111827; color:#fff; border-color:#111827; }
    .tbl-btn-dark:hover{ background:#0b1220; }
    .tbl-btn-amber{ background: rgba(245,158,11,.12); border-color: rgba(245,158,11,.25); color:#7a4a00; }
    .tbl-btn-amber:hover{ background: rgba(245,158,11,.18); }
    .tbl-btn-emerald{ background: rgba(16,185,129,.12); border-color: rgba(16,185,129,.25); color:#065f46; }
    .tbl-btn-emerald:hover{ background: rgba(16,185,129,.18); }
    .tbl-btn-rose{ background: rgba(244,63,94,.10); border-color: rgba(244,63,94,.20); color:#9f1239; }
    .tbl-btn-rose:hover{ background: rgba(244,63,94,.16); }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function(){
    if (!window.Tabulator) {
        console.error("Tabulator missing on window.");
        return;
    }

    const canAct  = @json($canAct);
    const dataUrl = @json(route('depot.compliance.clearances.data'));
    const csrf    = @json(csrf_token());
    const showBase = @json(url('depot/compliance/clearances'));

    // ---- modal helpers ----
    const openModal = (id) => {
        const m = document.getElementById(id);
        if (!m) return;
        m.classList.remove("hidden");
        m.classList.add("flex");
    };
    const closeModal = (id) => {
        const m = document.getElementById(id);
        if (!m) return;
        m.classList.add("hidden");
        m.classList.remove("flex");
    };

    // Create modal open
    document.getElementById("btnOpenCreateClearance")?.addEventListener("click", function(){
        openModal("createClearanceModal");
    });

    // Attention panel toggle
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

    attBtn?.addEventListener("click", function(){
        if (!attPanel) return;
        const isOpen = !attPanel.classList.contains("hidden");
        isOpen ? closeAttention() : openAttention();
    });

    document.addEventListener("click", function(e){
        if (!attWrap || !attPanel) return;
        if (e.target.closest("[data-att-close]")) { closeAttention(); return; }
        if (!attWrap.contains(e.target)) closeAttention();
    });

    // Confirm modal plumbing
    const confirmModalId = "confirmActionModal";
    const confirmTitle = document.getElementById("confirmActionTitle");
    const confirmText = document.getElementById("confirmActionText");
    const confirmBtn = document.getElementById("confirmActionBtn");
    let pendingAction = null;

    const askConfirm = (opts) => {
        pendingAction = opts;
        if (confirmTitle) confirmTitle.textContent = opts.title || "Confirm";
        if (confirmText) confirmText.textContent = opts.text || "Are you sure?";
        if (confirmBtn) confirmBtn.textContent = opts.confirmLabel || "Confirm";
        openModal(confirmModalId);
    };

    confirmBtn?.addEventListener("click", function(){
        if (!pendingAction) return;
        const fn = pendingAction.onConfirm;
        closeModal(confirmModalId);
        pendingAction = null;
        if (typeof fn === "function") fn();
    });

    // Issue TR8 modal plumbing
    const tr8ModalId = "issueTr8Modal";
    const tr8Form = document.getElementById("issueTr8Form");
    const tr8Number = document.getElementById("issueTr8Number");
    const tr8Ref = document.getElementById("issueTr8Reference");
    const tr8File = document.getElementById("issueTr8Document");
    const tr8Action = document.getElementById("issueTr8Action");

    const openIssueTr8 = (row) => {
        if (!tr8Form || !tr8Action) return;
        tr8Form.reset();
        tr8Action.value = row?.urls?.issue_tr8 || "";
        if (tr8Number) tr8Number.value = row.tr8_number || "";
        if (tr8Ref) tr8Ref.value = row.tr8_reference || "";
        if (tr8File) tr8File.value = "";
        openModal(tr8ModalId);
    };

    document.getElementById("issueTr8Submit")?.addEventListener("click", function(){
        if (!tr8Form || !tr8Action || !tr8Action.value) return;
        tr8Form.action = tr8Action.value;
        tr8Form.submit();
    });

    document.addEventListener("click", function(e){
        if (e.target.closest("[data-close-modal='confirm']")) closeModal(confirmModalId);
        if (e.target.closest("[data-close-modal='issue_tr8']")) closeModal(tr8ModalId);
    });

    // ---- table helpers ----
    const statusPillClass = (status) => {
        switch(status){
            case "submitted":  return "border-amber-200 bg-amber-50 text-amber-900";
            case "tr8_issued": return "border-blue-200 bg-blue-50 text-blue-900";
            case "arrived":    return "border-emerald-200 bg-emerald-50 text-emerald-900";
            case "cancelled":  return "border-rose-200 bg-rose-50 text-rose-900";
            default:           return "border-gray-200 bg-gray-50 text-gray-900";
        }
    };

    const actionHtml = (row) => {
        if (!canAct) return "";
        const s = row.status;

        const btn = (label, action, cls) =>
            `<button type="button" class="tbl-btn ${cls}" data-action="${action}">${label}</button>`;

        let html = `<div class="flex flex-wrap items-center gap-2">`;

        if (s === "draft") {
            html += btn("Submit", "submit", "tbl-btn-dark");
        }
        if (s === "submitted") {
            html += btn("Issue TR8", "issue_tr8", "tbl-btn-amber");
            html += btn("Cancel", "cancel", "tbl-btn-rose");
        }
        if (s === "tr8_issued") {
            html += btn("Mark arrived", "arrive", "tbl-btn-emerald");
            html += btn("Cancel", "cancel", "tbl-btn-rose");
        }

        html += `</div>`;
        return html;
    };

    // ✅ IMPORTANT FIX:
    // Your server returns: { data: [...], meta: {...} }
    // Tabulator remote pagination wants: { data: [...], last_page: N, ... }
    // So we reshape the response inside ajaxResponse.
    const reshapeResponse = (response) => {
        const data = response?.data ?? [];
        const meta = response?.meta ?? {};
        return {
            data: Array.isArray(data) ? data : [],
            current_page: meta.current_page ?? 1,
            last_page: meta.last_page ?? 1,
            per_page: meta.per_page ?? 20,
            total: meta.total ?? (Array.isArray(data) ? data.length : 0),
        };
    };

    const table = new Tabulator("#clearancesTable", {
        layout: "fitColumns",
        height: "520px",
        rowHeight: 52,
        placeholder: "No clearances found for the current filters.",

        pagination: true,
        paginationMode: "remote",
        paginationSize: 20,

        ajaxURL: dataUrl,
        ajaxConfig: { method: "GET" },
        ajaxParams: function () {
            return Object.fromEntries(new URLSearchParams(window.location.search).entries());
        },

        ajaxResponse: function(url, params, response){
            const shaped = reshapeResponse(response);
            console.log("Tabulator shaped response:", shaped);
            // Tabulator expects the returned object when using remote pagination
            return shaped;
        },

        rowFormatter: function(row){
            const data = row.getData();
            const el = row.getElement();
            el.classList.add("row-accent");
            el.classList.remove("accent-draft","accent-submitted","accent-tr8_issued","accent-arrived","accent-cancelled");
            el.classList.add("accent-" + ((data.status || "draft").toString()));
        },

        columns: [
            {
                title: "STATUS",
                field: "status",
                width: 150,
                formatter: (cell) => {
                    const s = cell.getValue();
                    const label = (s || "").toString().replaceAll("_", " ").toUpperCase();
                    return `<span class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-semibold ${statusPillClass(s)}">${label || "-"}</span>`;
                }
            },
            { title: "CLIENT", field: "client_name", minWidth: 180 },
            { title: "TRUCK", field: "truck_number", width: 140 },
            { title: "TRAILER", field: "trailer_number", width: 150 },
            {
                title: "LOADED @20C",
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
            { title: "BORDER", field: "border_point", width: 160 },
            { title: "SUBMITTED", field: "submitted_at", width: 170 },
            { title: "ISSUED", field: "tr8_issued_at", width: 170 },
            { title: "UPDATED BY", field: "updated_by_name", width: 170 },
            { title: "AGE", field: "age_human", width: 120 },
            {
                title: "ACTIONS",
                field: "actions",
                minWidth: 240,
                headerSort: false,
                formatter: (cell) => actionHtml(cell.getRow().getData())
            },
        ],

        // Entire row clickable -> show page (no "Open" button)
        rowClick: function(e, row){
            const t = e.target;
            if (t.closest("button") || t.closest("a")) return;
            const data = row.getData();
            if (data?.id) window.location.href = showBase + "/" + data.id;
        },
    });

    // Exports (client-side from loaded table view)
    document.getElementById("btnExportXlsx")?.addEventListener("click", function(){
        table.download("xlsx", "clearances.xlsx", {sheetName:"Clearances"});
    });
    document.getElementById("btnExportPdf")?.addEventListener("click", function(){
        table.download("pdf", "clearances.pdf", { orientation: "landscape", title: "Clearances" });
    });

    // Action handling with modals (no browser confirm)
    const postForm = (url) => {
        const f = document.createElement("form");
        f.method = "POST";
        f.action = url;

        const csrfInput = document.createElement("input");
        csrfInput.type = "hidden";
        csrfInput.name = "_token";
        csrfInput.value = csrf;
        f.appendChild(csrfInput);

        document.body.appendChild(f);
        f.submit();
    };

    document.addEventListener("click", function(e){
        const btn = e.target.closest("button[data-action]");
        if (!btn) return;

        const rowEl = btn.closest(".tabulator-row");
        if (!rowEl) return;

        const row = table.getRow(rowEl);
        const data = row ? row.getData() : null;
        if (!data) return;

        const action = btn.getAttribute("data-action");

        if (action === "issue_tr8") {
            openIssueTr8(data);
            return;
        }

        if (action === "submit") {
            askConfirm({
                title: "Submit clearance",
                text: "This will move the clearance to Submitted.",
                confirmLabel: "Submit",
                onConfirm: () => postForm(data.urls.submit),
            });
        }

        if (action === "arrive") {
            askConfirm({
                title: "Mark arrived",
                text: "This will mark the truck as Arrived.",
                confirmLabel: "Mark arrived",
                onConfirm: () => postForm(data.urls.arrive),
            });
        }

        if (action === "cancel") {
            askConfirm({
                title: "Cancel clearance",
                text: "This will cancel the clearance. Continue?",
                confirmLabel: "Cancel",
                onConfirm: () => postForm(data.urls.cancel),
            });
        }
    });

    // Attention deep-link smooth scroll
    if (window.location.hash === "#clearances") {
        document.getElementById("clearances")?.scrollIntoView({behavior:"smooth", block:"start"});
    }
});
</script>
@endpush