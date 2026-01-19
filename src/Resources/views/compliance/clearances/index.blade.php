{{-- depot-stock::compliance/clearances/index.blade.php --}}
@extends('depot-stock::layouts.app')

@section('content')
@php
    // -------------------------------
    // Safety defaults (avoid undefined)
    // -------------------------------
    $clients    = $clients ?? collect();
    $clearances = $clearances ?? null;

    // IMPORTANT: if controller doesn't pass stats, show "—" (not fake zeros)
    $stats = $stats ?? [
        'total' => null,
        'draft' => null,
        'submitted' => null,
        'tr8_issued' => null,
        'arrived' => null,
        'cancelled' => null,
        'stuck_submitted' => null,
        'stuck_tr8_issued' => null,
        'missing_tr8_number' => null,
        'missing_documents' => null,
    ];

    // -------------------------------
    // Role gating (User::hasRole expects STRING only; avoid passing arrays)
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
    // Needs-attention totals
    // -------------------------------
    $attStuckSubmitted = (int)($stats['stuck_submitted'] ?? 0);
    $attStuckTr8       = (int)($stats['stuck_tr8_issued'] ?? 0);
    $attMissingTr8     = (int)($stats['missing_tr8_number'] ?? 0);
    $attMissingDocs    = (int)($stats['missing_documents'] ?? 0);
    $attTotal          = $attStuckSubmitted + $attStuckTr8 + $attMissingTr8 + $attMissingDocs;

    // Helper: preserve query string + override
    $qsBase = array_filter([
        'client_id' => $fClient,
        'status'    => $fStatus,
        'q'         => $fSearch,
        'from'      => $fFrom,
        'to'        => $fTo,
    ], fn($v) => $v !== null && $v !== '');

    $makeUrl = function(array $override = []) use ($qsBase) {
        $qs = array_merge($qsBase, $override);
        $qs = array_filter($qs, fn($v) => $v !== null && $v !== '');
        return url()->current() . (count($qs) ? ('?' . http_build_query($qs)) : '');
    };

    $now = now();
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

                    <div class="flex items-center gap-2">
                        @if($canCreate)
                            <button
                                type="button"
                                id="btnOpenCreateClearance"
                                class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                                title="Create a new clearance"
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
                                {{-- exclamation icon (more suitable than bell) --}}
                                <svg class="h-5 w-5 text-gray-700 group-hover:text-gray-900" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 3.5 2.9 20.5a1.2 1.2 0 0 0 1.06 1.8h16.08a1.2 1.2 0 0 0 1.06-1.8L12 3.5Z" stroke="currentColor" stroke-width="1.5" />
                                    <path d="M12 9v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                    <path d="M12 17.8h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                </svg>

                                @if($attTotal > 0)
                                    <span class="absolute -top-2 -right-2 inline-flex h-5 min-w-[1.35rem] items-center justify-center rounded-full bg-rose-600 px-1.5 text-[11px] font-bold text-white shadow">
                                        {{ $attTotal }}
                                    </span>
                                @endif
                            </button>

                            {{-- Attention panel (WIDE + responsive) --}}
                            <div
                                id="attentionPanel"
                                class="hidden z-40 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl
                                       sm:absolute sm:right-0 sm:mt-2 sm:w-[44rem]
                                       fixed left-4 right-4 top-20 sm:top-auto sm:left-auto sm:right-0"
                            >
                                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="h-9 w-9 rounded-xl bg-gray-900 text-white flex items-center justify-center">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M12 3.5 2.9 20.5a1.2 1.2 0 0 0 1.06 1.8h16.08a1.2 1.2 0 0 0 1.06-1.8L12 3.5Z" stroke="currentColor" stroke-width="1.5" />
                                                <path d="M12 9v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                <path d="M12 17.8h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900 leading-tight">Needs attention</div>
                                            <div class="text-[11px] text-gray-500 leading-tight">Tap an item to jump + auto-filter.</div>
                                        </div>
                                    </div>

                                    <button type="button"
                                            class="rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                            data-att-close="1">
                                        Close
                                    </button>
                                </div>

                                <div class="p-3">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <a href="{{ $makeUrl(['status' => 'submitted', '__att' => 'stuck_submitted']) }}#clearances"
                                           class="group rounded-2xl border border-gray-200 bg-gray-50/60 p-3 hover:bg-gray-50 hover:border-gray-300 transition">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="text-[13px] font-semibold text-gray-900 leading-snug">Stuck in Submitted</div>
                                                    <div class="text-[11px] text-gray-500 mt-1">Waiting TR8 issuance · chase border/agent</div>
                                                </div>
                                                <div class="shrink-0 flex items-center gap-2">
                                                    <span class="rounded-full bg-amber-50 px-2 py-1 text-[11px] font-bold text-amber-900 border border-amber-200">{{ $attStuckSubmitted }}</span>
                                                    <span class="text-gray-300 group-hover:text-gray-400">›</span>
                                                </div>
                                            </div>
                                        </a>

                                        <a href="{{ $makeUrl(['status' => 'tr8_issued', '__att' => 'stuck_tr8_issued']) }}#clearances"
                                           class="group rounded-2xl border border-gray-200 bg-gray-50/60 p-3 hover:bg-gray-50 hover:border-gray-300 transition">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="text-[13px] font-semibold text-gray-900 leading-snug">TR8 issued, not arrived</div>
                                                    <div class="text-[11px] text-gray-500 mt-1">TR8 done · chase truck/dispatch</div>
                                                </div>
                                                <div class="shrink-0 flex items-center gap-2">
                                                    <span class="rounded-full bg-blue-50 px-2 py-1 text-[11px] font-bold text-blue-900 border border-blue-200">{{ $attStuckTr8 }}</span>
                                                    <span class="text-gray-300 group-hover:text-gray-400">›</span>
                                                </div>
                                            </div>
                                        </a>

                                        <a href="{{ $makeUrl(['__att' => 'missing_tr8_number']) }}#clearances"
                                           class="group rounded-2xl border border-gray-200 bg-gray-50/60 p-3 hover:bg-gray-50 hover:border-gray-300 transition">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="text-[13px] font-semibold text-gray-900 leading-snug">Missing TR8 number</div>
                                                    <div class="text-[11px] text-gray-500 mt-1">Data risk · fix immediately</div>
                                                </div>
                                                <div class="shrink-0 flex items-center gap-2">
                                                    <span class="rounded-full bg-rose-50 px-2 py-1 text-[11px] font-bold text-rose-900 border border-rose-200">{{ $attMissingTr8 }}</span>
                                                    <span class="text-gray-300 group-hover:text-gray-400">›</span>
                                                </div>
                                            </div>
                                        </a>

                                        <a href="{{ $makeUrl(['__att' => 'missing_documents']) }}#clearances"
                                           class="group rounded-2xl border border-gray-200 bg-gray-50/60 p-3 hover:bg-gray-50 hover:border-gray-300 transition">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="text-[13px] font-semibold text-gray-900 leading-snug">Missing documents</div>
                                                    <div class="text-[11px] text-gray-500 mt-1">Audit risk · upload supporting docs</div>
                                                </div>
                                                <div class="shrink-0 flex items-center gap-2">
                                                    <span class="rounded-full bg-gray-100 px-2 py-1 text-[11px] font-bold text-gray-800 border border-gray-200">{{ $attMissingDocs }}</span>
                                                    <span class="text-gray-300 group-hover:text-gray-400">›</span>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- STATUS PILLS --}}
                @php
                    $pillVal = function($key) use ($stats) {
                        // show — when stats missing (prevents "0 0 0" confusion)
                        return array_key_exists($key, $stats) && $stats[$key] !== null ? (int)$stats[$key] : '—';
                    };
                    $pill = function($label, $value, $href, $tone) {
                        $base = "inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition hover:shadow-sm";
                        $toneClass = match($tone) {
                            'amber' => "border-amber-200 bg-amber-50 text-amber-900",
                            'blue' => "border-blue-200 bg-blue-50 text-blue-900",
                            'emerald' => "border-emerald-200 bg-emerald-50 text-emerald-900",
                            'rose' => "border-rose-200 bg-rose-50 text-rose-900",
                            default => "border-gray-200 bg-gray-50 text-gray-800",
                        };
                        return '<a href="'.$href.'" class="'.$base.' '.$toneClass.'"><span>'.$label.'</span><span class="inline-flex items-center justify-center rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-bold border border-black/5">'.$value.'</span></a>';
                    };
                @endphp

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    {!! $pill('Total', $pillVal('total'), $makeUrl(['status' => null]), 'gray') !!}
                    {!! $pill('Draft', $pillVal('draft'), $makeUrl(['status' => 'draft']), 'gray') !!}
                    {!! $pill('Submitted', $pillVal('submitted'), $makeUrl(['status' => 'submitted']), 'amber') !!}
                    {!! $pill('TR8 Issued', $pillVal('tr8_issued'), $makeUrl(['status' => 'tr8_issued']), 'blue') !!}
                    {!! $pill('Arrived', $pillVal('arrived'), $makeUrl(['status' => 'arrived']), 'emerald') !!}
                    {!! $pill('Cancelled', $pillVal('cancelled'), $makeUrl(['status' => 'cancelled']), 'rose') !!}
                </div>

                {{-- FILTERS (compact, one-line on desktop; wraps on small screens) --}}
                <form method="GET" class="mt-4 rounded-2xl border border-gray-200 bg-white p-4">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="w-full sm:w-56">
                            <label class="text-[11px] font-semibold text-gray-600">Client</label>
                            <select name="client_id" class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10">
                                <option value="">All clients</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}" @selected((string)$fClient === (string)$c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="w-full sm:w-44">
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

                        <div class="flex-1 min-w-[16rem]">
                            <label class="text-[11px] font-semibold text-gray-600">Search</label>
                            <input name="q" value="{{ $fSearch }}"
                                   placeholder="Truck, trailer, TR8, invoice, border..."
                                   class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10" />
                        </div>

                        <div class="w-full sm:w-40">
                            <label class="text-[11px] font-semibold text-gray-600">From</label>
                            <input type="date" name="from" value="{{ $fFrom }}"
                                   class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10" />
                        </div>

                        <div class="w-full sm:w-40">
                            <label class="text-[11px] font-semibold text-gray-600">To</label>
                            <input type="date" name="to" value="{{ $fTo }}"
                                   class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10" />
                        </div>

                        <div class="ml-auto flex items-center gap-2">
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

                {{-- LIST HEADER + EXPORTS --}}
                <div id="clearances" class="mt-5 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">Clearances</div>
                        <div class="text-xs text-gray-500">Row click opens the record. Buttons handle workflow.</div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button"
                                id="btnExportXlsx"
                                class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                            Export Excel
                        </button>
                        <button type="button"
                                id="btnExportPdf"
                                class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                            Export PDF
                        </button>
                    </div>
                </div>

                {{-- Tabulator container --}}
                <div class="mt-3 rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between bg-gray-50/60">
                        <div class="text-xs text-gray-500">
                            Table view
                        </div>
                        <div class="text-xs text-gray-400">
                            Tip: click row to open
                        </div>
                    </div>

                    <div class="p-3 bg-white">
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

{{-- Confirm modal (reusable) --}}
<div id="confirmModal" class="hidden fixed inset-0 z-50 items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/35" data-confirm-cancel="1"></div>

    <div class="relative w-full max-w-lg rounded-2xl border border-gray-200 bg-white shadow-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <div class="text-sm font-semibold text-gray-900" id="confirmTitle">Confirm</div>
            <div class="text-xs text-gray-500 mt-1" id="confirmText">Are you sure?</div>
        </div>

        <div class="px-5 py-4 flex items-center justify-end gap-2">
            <button type="button" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50" data-confirm-cancel="1">
                Cancel
            </button>
            <button type="button" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800" id="confirmOk">
                Confirm
            </button>
        </div>
    </div>
</div>

{{-- Issue TR8 modal --}}
<div id="issueTr8Modal" class="hidden fixed inset-0 z-50 items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/35" data-issue-close="1"></div>

    <div class="relative w-full max-w-2xl rounded-2xl border border-gray-200 bg-white shadow-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <div class="text-sm font-semibold text-gray-900">Issue TR8</div>
                <div class="text-xs text-gray-500 mt-1">Add TR8 number (and optionally attach a document).</div>
            </div>
            <button type="button" class="rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" data-issue-close="1">
                Close
            </button>
        </div>

        <form id="issueTr8Form" class="p-5">
            <input type="hidden" id="issueTr8ClearanceId" value="" />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-[11px] font-semibold text-gray-600">TR8 number</label>
                    <input id="issueTr8Number" required
                           class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                           placeholder="e.g. TR8-000123" />
                </div>
                <div>
                    <label class="text-[11px] font-semibold text-gray-600">Reference (optional)</label>
                    <input id="issueTr8Ref"
                           class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                           placeholder="Agent / border ref" />
                </div>
                <div class="sm:col-span-2">
                    <label class="text-[11px] font-semibold text-gray-600">Attach document (optional)</label>
                    <input id="issueTr8Doc" type="file"
                           class="mt-1 w-full rounded-xl border border-gray-200 bg-white text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-900 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-gray-800" />
                    <div class="text-[11px] text-gray-500 mt-1">PDF/JPG/PNG recommended.</div>
                </div>
            </div>

            <div class="mt-5 flex items-center justify-end gap-2">
                <button type="button" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50" data-issue-close="1">
                    Cancel
                </button>
                <button type="submit" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                    Save TR8
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Tabulator polish (header bg different from light grey + status styling) */
    #clearancesTable .tabulator{
        border: 0;
        border-radius: 16px;
        overflow: hidden;
    }
    #clearancesTable .tabulator-header{
        background: rgba(17,24,39,.04); /* richer than default grey */
        border-bottom: 1px solid rgba(0,0,0,.06);
    }
    #clearancesTable .tabulator-col{
        background: transparent;
    }
    #clearancesTable .tabulator-row{
        border-bottom: 1px solid rgba(0,0,0,.04);
    }
    #clearancesTable .tabulator-row:hover{
        background: rgba(17,24,39,.03);
    }
    #clearancesTable .tabulator-cell{
        padding-top: 12px;
        padding-bottom: 12px;
    }
    .status-pill{
        display:inline-flex;
        align-items:center;
        gap:.4rem;
        padding:.2rem .6rem;
        border-radius:999px;
        border:1px solid rgba(0,0,0,.08);
        font-size:12px;
        font-weight:700;
        white-space:nowrap;
    }
    .row-draft      { background: rgba(107,114,128,.06); }
    .row-submitted  { background: rgba(245,158,11,.06); }
    .row-tr8_issued { background: rgba(59,130,246,.06); }
    .row-arrived    { background: rgba(16,185,129,.06); }
    .row-cancelled  { background: rgba(244,63,94,.06); }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function () {
    // 1) Ensure Tabulator exists
    if (!window.Tabulator) {
        console.error("Tabulator not found on window.");
        return;
    }

    var csrf = @json(csrf_token());
    var canAct = @json((bool)$canAct);

    // 2) Routes as BASE strings (avoid Blade inside template literals)
    var baseShow   = @json(url('depot/compliance/clearances'));
    var dataUrl    = @json(route('depot.compliance.clearances.data'));

    // 3) Confirm modal (reusable)
    var confirmModal = document.getElementById("confirmModal");
    var confirmTitle = document.getElementById("confirmTitle");
    var confirmText  = document.getElementById("confirmText");
    var confirmOk    = document.getElementById("confirmOk");
    var confirmResolver = null;

    function openConfirm(title, text) {
        confirmTitle.textContent = title || "Confirm";
        confirmText.textContent  = text || "Are you sure?";
        confirmModal.classList.remove("hidden");
        confirmModal.classList.add("flex");
        return new Promise(function(resolve){
            confirmResolver = resolve;
        });
    }
    function closeConfirm(val){
        confirmModal.classList.add("hidden");
        confirmModal.classList.remove("flex");
        var r = confirmResolver;
        confirmResolver = null;
        if (r) r(val);
    }
    document.querySelectorAll("[data-confirm-cancel]").forEach(function(el){
        el.addEventListener("click", function(){ closeConfirm(false); });
    });
    confirmOk.addEventListener("click", function(){ closeConfirm(true); });

    // 4) Issue TR8 modal
    var issueModal = document.getElementById("issueTr8Modal");
    var issueForm  = document.getElementById("issueTr8Form");
    var issueIdEl  = document.getElementById("issueTr8ClearanceId");
    var issueNumEl = document.getElementById("issueTr8Number");
    var issueRefEl = document.getElementById("issueTr8Ref");
    var issueDocEl = document.getElementById("issueTr8Doc");

    function openIssueModal(clearanceId){
        issueIdEl.value = String(clearanceId || "");
        issueNumEl.value = "";
        issueRefEl.value = "";
        if (issueDocEl) issueDocEl.value = "";
        issueModal.classList.remove("hidden");
        issueModal.classList.add("flex");
        setTimeout(function(){ issueNumEl.focus(); }, 50);
    }
    function closeIssueModal(){
        issueModal.classList.add("hidden");
        issueModal.classList.remove("flex");
    }
    document.querySelectorAll("[data-issue-close]").forEach(function(el){
        el.addEventListener("click", function(){ closeIssueModal(); });
    });

    // 5) Attention panel toggle
    var attBtn   = document.getElementById("btnAttention");
    var attPanel = document.getElementById("attentionPanel");
    var attWrap  = document.getElementById("attWrap");

    function closeAttention(){
        if (!attPanel) return;
        attPanel.classList.add("hidden");
        if (attBtn) attBtn.setAttribute("aria-expanded", "false");
    }
    function openAttention(){
        if (!attPanel) return;
        attPanel.classList.remove("hidden");
        if (attBtn) attBtn.setAttribute("aria-expanded", "true");
    }
    if (attBtn) {
        attBtn.addEventListener("click", function(){
            if (!attPanel) return;
            var isOpen = !attPanel.classList.contains("hidden");
            if (isOpen) closeAttention(); else openAttention();
        });
    }
    document.addEventListener("click", function(e){
        if (!attWrap || !attPanel) return;
        if (e.target && e.target.closest && e.target.closest("[data-att-close]")) {
            closeAttention();
            return;
        }
        if (!attWrap.contains(e.target)) closeAttention();
    });

    // 6) POST helper (JSON)
    async function postJson(url, payload) {
        var res = await fetch(url, {
            method: "POST",
            headers: {
                "Accept": "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrf
            },
            body: JSON.stringify(payload || {})
        });
        if (!res.ok) {
            var txt = await res.text();
            throw new Error(txt || "Request failed");
        }
        return true;
    }

    // 7) POST helper (multipart) for issue TR8 + file
    async function postMultipart(url, formData) {
        var res = await fetch(url, {
            method: "POST",
            headers: {
                "Accept": "application/json",
                "X-CSRF-TOKEN": csrf
            },
            body: formData
        });
        if (!res.ok) {
            var txt = await res.text();
            throw new Error(txt || "Request failed");
        }
        return true;
    }

    // 8) Tabulator (REMOTE) – matches your controller: {data:[], meta:{current_page,last_page,per_page,total}}
    function statusRowClass(status){
        switch(String(status || "")){
            case "submitted":  return "row-submitted";
            case "tr8_issued": return "row-tr8_issued";
            case "arrived":    return "row-arrived";
            case "cancelled":  return "row-cancelled";
            default:           return "row-draft";
        }
    }
    function statusPill(status){
        var s = String(status || "");
        var label = s.replace(/_/g, " ").toUpperCase() || "-";
        var tone = "background: rgba(17,24,39,.04); color:#111827;";
        if (s === "submitted") tone = "background: rgba(245,158,11,.15); color:#7a4b00;";
        if (s === "tr8_issued") tone = "background: rgba(59,130,246,.15); color:#0b3b86;";
        if (s === "arrived") tone = "background: rgba(16,185,129,.15); color:#065f46;";
        if (s === "cancelled") tone = "background: rgba(244,63,94,.15); color:#9f1239;";
        return '<span class="status-pill" style="'+tone+'">'+label+'</span>';
    }

    var table = new Tabulator("#clearancesTable", {
        layout: "fitColumns",
        height: "560px",
        placeholder: "No clearances found for this filter.",
        pagination: true,
        paginationMode: "remote",
        paginationSize: 20,
        ajaxURL: dataUrl,
        ajaxParams: function(){
            // send current query string filters to server
            var p = Object.fromEntries(new URLSearchParams(window.location.search).entries());
            return p;
        },
        ajaxResponse: function(url, params, response){
            // response is {data:[], meta:{}}
            if (!response || !response.data) return [];
            // store meta for remote pagination mapping
            table.setMaxPage(Number(response.meta && response.meta.last_page ? response.meta.last_page : 1));
            return response.data;
        },
        rowFormatter: function(row){
            var d = row.getData() || {};
            row.getElement().classList.add(statusRowClass(d.status));
        },
        columns: [
            { title: "STATUS", field: "status", width: 140, headerSort: false, formatter: function(cell){ return statusPill(cell.getValue()); } },
            { title: "CLIENT", field: "client_name", minWidth: 180 },
            { title: "TRUCK", field: "truck_number", width: 130 },
            { title: "TRAILER", field: "trailer_number", width: 140 },
            { title: "LOADED @20C", field: "loaded_20_l", width: 140, hozAlign:"right",
              formatter: function(cell){
                var v = cell.getValue();
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
                field: "id",
                minWidth: 270,
                headerSort: false,
                formatter: function(cell){
                    var row = cell.getRow().getData();
                    if (!row || !row.id) return "";
                    if (!canAct) return "";
                    var s = String(row.status || "");
                    var html = '<div class="flex flex-wrap items-center gap-2 justify-end">';
                    if (s === "draft") {
                        html += '<button type="button" class="px-3 py-1.5 rounded-xl bg-gray-900 text-white text-xs font-semibold hover:bg-gray-800" data-action="submit" data-id="'+row.id+'">Submit</button>';
                        html += '<button type="button" class="px-3 py-1.5 rounded-xl border border-rose-200 bg-rose-50 text-rose-900 text-xs font-semibold hover:bg-rose-100" data-action="cancel" data-id="'+row.id+'">Cancel</button>';
                    } else if (s === "submitted") {
                        html += '<button type="button" class="px-3 py-1.5 rounded-xl border border-amber-200 bg-amber-50 text-amber-900 text-xs font-semibold hover:bg-amber-100" data-action="issue_tr8" data-id="'+row.id+'">Issue TR8</button>';
                        html += '<button type="button" class="px-3 py-1.5 rounded-xl border border-rose-200 bg-rose-50 text-rose-900 text-xs font-semibold hover:bg-rose-100" data-action="cancel" data-id="'+row.id+'">Cancel</button>';
                    } else if (s === "tr8_issued") {
                        html += '<button type="button" class="px-3 py-1.5 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-900 text-xs font-semibold hover:bg-emerald-100" data-action="arrive" data-id="'+row.id+'">Mark arrived</button>';
                        html += '<button type="button" class="px-3 py-1.5 rounded-xl border border-rose-200 bg-rose-50 text-rose-900 text-xs font-semibold hover:bg-rose-100" data-action="cancel" data-id="'+row.id+'">Cancel</button>';
                    }
                    html += "</div>";
                    return html;
                }
            }
        ],
        rowClick: function(e, row){
            // if user clicked a button in Actions, ignore row navigation
            var t = e.target;
            if (t && t.closest && t.closest("[data-action]")) return;
            var d = row.getData();
            if (!d || !d.id) return;
            window.location.href = baseShow + "/" + d.id;
        },
    });

    // 9) Actions click handler (with modals)
    document.addEventListener("click", async function(e){
        var btn = e.target && e.target.closest ? e.target.closest("[data-action]") : null;
        if (!btn) return;

        var action = btn.getAttribute("data-action");
        var id = btn.getAttribute("data-id");
        if (!action || !id) return;

        try {
            if (action === "submit") {
                var ok = await openConfirm("Submit clearance", "This will move the clearance to SUBMITTED.");
                if (!ok) return;
                await postJson(baseShow + "/" + id + "/submit", {});
                window.location.reload();
                return;
            }

            if (action === "arrive") {
                var ok2 = await openConfirm("Mark arrived", "This will mark the clearance as ARRIVED.");
                if (!ok2) return;
                await postJson(baseShow + "/" + id + "/arrive", {});
                window.location.reload();
                return;
            }

            if (action === "cancel") {
                var ok3 = await openConfirm("Cancel clearance", "This will move the clearance to CANCELLED.");
                if (!ok3) return;
                await postJson(baseShow + "/" + id + "/cancel", {});
                window.location.reload();
                return;
            }

            if (action === "issue_tr8") {
                openIssueModal(id);
                return;
            }
        } catch (err) {
            console.error(err);
            alert("Action failed. Check console/network.");
        }
    });

    // 10) Issue TR8 submit
    issueForm.addEventListener("submit", async function(e){
        e.preventDefault();

        var id = issueIdEl.value;
        var tr8 = issueNumEl.value.trim();
        var ref = issueRefEl.value.trim();

        if (!id || !tr8) return;

        try {
            var fd = new FormData();
            fd.append("tr8_number", tr8);
            if (ref) fd.append("tr8_reference", ref);
            if (issueDocEl && issueDocEl.files && issueDocEl.files[0]) {
                fd.append("tr8_document", issueDocEl.files[0]);
            }

            await postMultipart(baseShow + "/" + id + "/issue-tr8", fd);
            closeIssueModal();
            window.location.reload();
        } catch (err) {
            console.error(err);
            alert("Failed to issue TR8. Check console/network.");
        }
    });

    // 11) Exports (client-side) – current filtered page view
    document.getElementById("btnExportXlsx") && document.getElementById("btnExportXlsx").addEventListener("click", function(){
        table.download("xlsx", "clearances.xlsx", { sheetName: "Clearances" });
    });

    document.getElementById("btnExportPdf") && document.getElementById("btnExportPdf").addEventListener("click", function(){
        table.download("pdf", "clearances.pdf", { orientation: "landscape", title: "Clearances" });
    });

    // 12) Create modal open (keeps your existing include)
    var btnCreate = document.getElementById("btnOpenCreateClearance");
    if (btnCreate) {
        btnCreate.addEventListener("click", function(){
            var modal = document.getElementById("createClearanceModal");
            if (!modal) return;
            modal.classList.remove("hidden");
            modal.classList.add("flex");
        });
    }

    // 13) Smooth scroll to table from attention links
    if (window.location.hash === "#clearances") {
        var el = document.getElementById("clearances");
        if (el) el.scrollIntoView({ behavior: "smooth", block: "start" });
    }
});
</script>
@endpush