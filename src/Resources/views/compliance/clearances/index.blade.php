@extends('depot-stock::layouts.app')

@section('content')
@php
    // -------------------------------
    // Safety defaults (avoid undefined)
    // -------------------------------
    $clients    = $clients ?? collect();

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
    // Role gating (keep your existing role logic)
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

    // Needs-attention totals
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

    $now = now();
@endphp

<div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
    <div class="mt-5">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="p-4 sm:p-6">

                {{-- HEADER --}}
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-[11px] font-semibold text-gray-500">Compliance</div>
                        <h1 class="mt-1 text-lg sm:text-xl font-semibold tracking-tight text-gray-900">
                            Clearances &amp; TR8
                        </h1>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-gray-500">
                            <span class="inline-flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Live
                            </span>
                            <span class="hidden sm:inline">•</span>
                            <span>Refreshed: <span class="font-semibold text-gray-700">{{ $now->format('m/d/Y, g:i A') }}</span></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        @if($canCreate)
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                                id="btnOpenCreateClearance"
                                title="New clearance"
                            >
                                <span class="text-base leading-none">+</span>
                                <span class="hidden sm:inline ml-2">New</span>
                            </button>
                        @endif

                        {{-- Needs attention --}}
                        <div class="relative" id="attWrap">
                            <button
                                type="button"
                                id="btnAttention"
                                class="group relative inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-3 py-2 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                                aria-haspopup="true"
                                aria-expanded="false"
                                title="Needs attention"
                            >
                                {{-- modern bell --}}
                                <svg class="h-5 w-5 text-gray-700 group-hover:text-gray-900" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 22a2.5 2.5 0 0 0 2.45-2H9.55A2.5 2.5 0 0 0 12 22Z" fill="currentColor" opacity=".9"/>
                                    <path d="M20 17H4c1.8-1.5 2.5-3.3 2.5-6V9.5C6.5 6.46 8.7 4 12 4s5.5 2.46 5.5 5.5V11c0 2.7.7 4.5 2.5 6Z"
                                          stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                                </svg>

                                @if($attTotal > 0)
                                    <span class="absolute -top-2 -right-2 inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1.5 text-[11px] font-extrabold text-white shadow">
                                        {{ $attTotal }}
                                    </span>
                                @endif
                            </button>

                            {{-- Attention panel (WIDER: ~3x current) --}}
                            <div
                                id="attentionPanel"
                                class="hidden absolute right-0 mt-2 w-[34rem] sm:w-[42rem] max-w-[92vw] rounded-2xl border border-gray-200 bg-white shadow-2xl overflow-hidden z-40"
                            >
                                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-gray-900">Needs attention</div>
                                        <div class="text-[11px] text-gray-500">Tap an item to jump to the table and auto-filter.</div>
                                    </div>
                                    <button type="button"
                                            class="rounded-lg px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50"
                                            data-att-close="1">
                                        Close
                                    </button>
                                </div>

                                <div class="p-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <a href="{{ $makeFilterUrl(['status' => 'submitted', '__att' => 'stuck_submitted']) }}#clearances"
                                       class="group rounded-2xl border border-gray-200 bg-white p-3 hover:bg-gray-50">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="text-xs font-semibold text-gray-900">Stuck in Submitted</div>
                                                <div class="mt-1 text-[11px] text-gray-500">Waiting TR8 issuance • chase border/agent</div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="rounded-full bg-amber-50 px-2 py-1 text-[11px] font-extrabold text-amber-900 border border-amber-200">{{ $attStuckSubmitted }}</span>
                                                <span class="text-gray-300 group-hover:text-gray-400">›</span>
                                            </div>
                                        </div>
                                    </a>

                                    <a href="{{ $makeFilterUrl(['status' => 'tr8_issued', '__att' => 'stuck_tr8_issued']) }}#clearances"
                                       class="group rounded-2xl border border-gray-200 bg-white p-3 hover:bg-gray-50">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="text-xs font-semibold text-gray-900">TR8 issued, not arrived</div>
                                                <div class="mt-1 text-[11px] text-gray-500">TR8 done • chase truck/dispatch</div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="rounded-full bg-blue-50 px-2 py-1 text-[11px] font-extrabold text-blue-900 border border-blue-200">{{ $attStuckTr8 }}</span>
                                                <span class="text-gray-300 group-hover:text-gray-400">›</span>
                                            </div>
                                        </div>
                                    </a>

                                    <a href="{{ $makeFilterUrl(['__att' => 'missing_tr8_number']) }}#clearances"
                                       class="group rounded-2xl border border-gray-200 bg-white p-3 hover:bg-gray-50">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="text-xs font-semibold text-gray-900">Missing TR8 number</div>
                                                <div class="mt-1 text-[11px] text-gray-500">Data risk • fix immediately</div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="rounded-full bg-rose-50 px-2 py-1 text-[11px] font-extrabold text-rose-900 border border-rose-200">{{ $attMissingTr8 }}</span>
                                                <span class="text-gray-300 group-hover:text-gray-400">›</span>
                                            </div>
                                        </div>
                                    </a>

                                    <a href="{{ $makeFilterUrl(['__att' => 'missing_documents']) }}#clearances"
                                       class="group rounded-2xl border border-gray-200 bg-white p-3 hover:bg-gray-50">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="text-xs font-semibold text-gray-900">Missing documents</div>
                                                <div class="mt-1 text-[11px] text-gray-500">Audit risk • upload docs</div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="rounded-full bg-gray-50 px-2 py-1 text-[11px] font-extrabold text-gray-900 border border-gray-200">{{ $attMissingDocs }}</span>
                                                <span class="text-gray-300 group-hover:text-gray-400">›</span>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- STATUS PILLS --}}
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @php
                        $pill = function($label, $count, $key, $tone) use ($makeFilterUrl) {
                            $href = $makeFilterUrl(['status' => $key]);
                            $base = "inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] font-semibold";
                            $toneClass = match($tone) {
                                'amber' => "border-amber-200 bg-amber-50 text-amber-900",
                                'blue' => "border-blue-200 bg-blue-50 text-blue-900",
                                'emerald' => "border-emerald-200 bg-emerald-50 text-emerald-900",
                                'rose' => "border-rose-200 bg-rose-50 text-rose-900",
                                default => "border-gray-200 bg-gray-50 text-gray-800",
                            };
                            $k = htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8');
                            return <<<HTML
                                <a href="{$href}" class="{$base} {$toneClass} hover:shadow-sm transition">
                                    <span>{$label}</span>
                                    <span class="inline-flex items-center justify-center rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-extrabold border border-black/5">{$count}</span>
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

                {{-- FILTERS (ONE LINE on desktop, wraps nicely, mobile friendly) --}}
                <form method="GET" class="mt-4 rounded-2xl border border-gray-200 bg-white p-3 sm:p-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="w-full sm:w-[14rem]">
                            <label class="text-[11px] font-semibold text-gray-600">Client</label>
                            <select name="client_id"
                                    class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10">
                                <option value="">All clients</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}" @selected((string)$fClient === (string)$c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="w-full sm:w-[11rem]">
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

                        <div class="flex-1 min-w-[14rem]">
                            <label class="text-[11px] font-semibold text-gray-600">Search</label>
                            <input
                                name="q"
                                value="{{ $fSearch }}"
                                placeholder="Truck, trailer, TR8, invoice, border…"
                                class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                            />
                        </div>

                        <div class="w-full sm:w-[11rem]">
                            <label class="text-[11px] font-semibold text-gray-600">From</label>
                            <input
                                type="date"
                                name="from"
                                value="{{ $fFrom }}"
                                class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                            />
                        </div>

                        <div class="w-full sm:w-[11rem]">
                            <label class="text-[11px] font-semibold text-gray-600">To</label>
                            <input
                                type="date"
                                name="to"
                                value="{{ $fTo }}"
                                class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                            />
                        </div>

                        <div class="w-full sm:w-auto sm:ml-auto flex items-center justify-end gap-2 pt-1">
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
                <div id="clearances" class="mt-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">Clearances</div>
                        <div class="text-[11px] text-gray-500">Click a row to open. Use actions for workflow.</div>
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

                {{-- Tabulator --}}
                <div class="mt-3 rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between">
                        <div class="text-[11px] text-gray-500">
                            Remote list • respects filters + pagination
                        </div>
                        <div class="text-[11px] text-gray-400">
                            Tip: click row to open
                        </div>
                    </div>

                    {{-- Slight tint background so table pops (not same as page gray) --}}
                    <div class="bg-gray-50/60 p-2 sm:p-3">
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

{{-- Confirm Modal (pretty, replaces browser confirm) --}}
<div id="confirmModal" class="hidden fixed inset-0 z-50 items-center justify-center">
    <div class="absolute inset-0 bg-black/40"></div>
    <div class="relative w-[28rem] max-w-[92vw] rounded-2xl bg-white shadow-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <div class="text-sm font-semibold text-gray-900" id="confirmTitle">Confirm</div>
            <div class="mt-1 text-[12px] text-gray-600" id="confirmText">Are you sure?</div>
        </div>
        <div class="px-5 py-4 flex items-center justify-end gap-2">
            <button type="button" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50" data-confirm-cancel>
                No, go back
            </button>
            <button type="button" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800" data-confirm-ok>
                Yes, continue
            </button>
        </div>
    </div>
</div>

{{-- Issue TR8 Modal (number + optional reference + document upload) --}}
<div id="issueTr8Modal" class="hidden fixed inset-0 z-50 items-center justify-center">
    <div class="absolute inset-0 bg-black/40"></div>
    <div class="relative w-[34rem] max-w-[92vw] rounded-2xl bg-white shadow-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <div class="text-sm font-semibold text-gray-900">Issue TR8</div>
            <div class="mt-1 text-[12px] text-gray-600">Enter TR8 details. You can also attach the TR8 document.</div>
        </div>
        <form id="issueTr8Form" class="px-5 py-4 space-y-3" enctype="multipart/form-data">
            <input type="hidden" name="__id" id="issueTr8Id" value="">
            <div>
                <label class="text-[11px] font-semibold text-gray-600">TR8 number</label>
                <input name="tr8_number" id="issueTr8Number" required
                       class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                       placeholder="e.g. TR8-2026-00123">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-[11px] font-semibold text-gray-600">Reference (optional)</label>
                    <input name="reference" id="issueTr8Ref"
                           class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                           placeholder="Agent ref / border ref">
                </div>
                <div>
                    <label class="text-[11px] font-semibold text-gray-600">TR8 document (optional)</label>
                    <input type="file" name="document" id="issueTr8Doc"
                           class="mt-1 w-full rounded-xl border border-gray-200 bg-white text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-gray-800">
                </div>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <button type="button" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50" data-issue-close>
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
    /* Tabulator polish */
    #clearancesTable .tabulator{
        border: 0 !important;
        border-radius: 16px;
        overflow: hidden;
        background: white;
    }
    #clearancesTable .tabulator-header{
        border-bottom: 1px solid rgba(0,0,0,.06);
        background: rgba(249,250,251,.9);
    }
    #clearancesTable .tabulator-col,
    #clearancesTable .tabulator-col-title{
        font-size: 12px;
        font-weight: 700;
        color: rgba(17,24,39,.75);
    }
    #clearancesTable .tabulator-row{
        border-bottom: 1px solid rgba(0,0,0,.04);
        font-size: 13px;
    }
    #clearancesTable .tabulator-row:hover{
        background: rgba(0,0,0,.025);
    }
    /* Status row tint (subtle, doesn’t scream) */
    #clearancesTable .row-status-submitted { background: rgba(255,193,7,.07); }
    #clearancesTable .row-status-tr8_issued { background: rgba(59,130,246,.06); }
    #clearancesTable .row-status-arrived { background: rgba(16,185,129,.06); }
    #clearancesTable .row-status-cancelled { background: rgba(244,63,94,.05); }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function(){

    // ---- Sanity
    if (!window.Tabulator) {
        console.error("Tabulator not found on window. Ensure Vite app.js loads Tabulator and this blade uses @push('scripts').");
        return;
    }

    const csrf = @json(csrf_token());
    const canAct = @json($canAct);

    // Base URLs (Fix A: build URLs in JS from ID, so API does NOT need `urls`)
    const baseShow   = @json(url('depot/compliance/clearances')); // + /{id}
    const baseSubmit = @json(url('depot/compliance/clearances')); // + /{id}/submit
    const baseIssue  = @json(url('depot/compliance/clearances')); // + /{id}/issue-tr8
    const baseArrive = @json(url('depot/compliance/clearances')); // + /{id}/arrive
    const baseCancel = @json(url('depot/compliance/clearances')); // + /{id}/cancel

    const showUrl   = (id) => `${baseShow}/${id}`;
    const submitUrl = (id) => `${baseSubmit}/${id}/submit`;
    const issueUrl  = (id) => `${baseIssue}/${id}/issue-tr8`;
    const arriveUrl = (id) => `${baseArrive}/${id}/arrive`;
    const cancelUrl = (id) => `${baseCancel}/${id}/cancel`;

    // Data endpoint (remote)
    const dataUrl = @json(route('depot.compliance.clearances.data'));

    // Helpers
    const statusLabel = (s) => (s || '').toString().replaceAll('_',' ').toUpperCase();

    const statusPill = (s) => {
        const st = (s || '').toString();
        const base = "inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] font-extrabold";
        const cls = ({
            'submitted':  "border-amber-200 bg-amber-50 text-amber-900",
            'tr8_issued': "border-blue-200 bg-blue-50 text-blue-900",
            'arrived':    "border-emerald-200 bg-emerald-50 text-emerald-900",
            'cancelled':  "border-rose-200 bg-rose-50 text-rose-900",
            'draft':      "border-gray-200 bg-gray-50 text-gray-900",
        })[st] || "border-gray-200 bg-gray-50 text-gray-900";
        return `<span class="${base} ${cls}">${statusLabel(st) || '—'}</span>`;
    };

    // Pretty confirm modal
    const confirmModal = document.getElementById("confirmModal");
    const confirmTitle = document.getElementById("confirmTitle");
    const confirmText  = document.getElementById("confirmText");
    let confirmResolver = null;

    const openConfirm = (title, text) => new Promise((resolve) => {
        confirmTitle.textContent = title || "Confirm";
        confirmText.textContent  = text  || "Are you sure?";
        confirmResolver = resolve;
        confirmModal.classList.remove("hidden");
        confirmModal.classList.add("flex");
    });

    const closeConfirm = (val) => {
        confirmModal.classList.add("hidden");
        confirmModal.classList.remove("flex");
        if (confirmResolver) confirmResolver(val);
        confirmResolver = null;
    };

    document.querySelector("[data-confirm-cancel]")?.addEventListener("click", () => closeConfirm(false));
    document.querySelector("[data-confirm-ok]")?.addEventListener("click", () => closeConfirm(true));
    confirmModal?.addEventListener("click", (e) => { if (e.target === confirmModal) closeConfirm(false); });

    // Issue TR8 modal
    const issueModal = document.getElementById("issueTr8Modal");
    const issueForm  = document.getElementById("issueTr8Form");
    const issueIdEl  = document.getElementById("issueTr8Id");
    const issueNoEl  = document.getElementById("issueTr8Number");
    const issueRefEl = document.getElementById("issueTr8Ref");
    const issueDocEl = document.getElementById("issueTr8Doc");

    const openIssueModal = (id) => {
        issueIdEl.value = id;
        issueNoEl.value = "";
        issueRefEl.value = "";
        if (issueDocEl) issueDocEl.value = "";
        issueModal.classList.remove("hidden");
        issueModal.classList.add("flex");
        setTimeout(() => issueNoEl?.focus(), 50);
    };

    const closeIssueModal = () => {
        issueModal.classList.add("hidden");
        issueModal.classList.remove("flex");
    };

    document.querySelector("[data-issue-close]")?.addEventListener("click", closeIssueModal);
    issueModal?.addEventListener("click", (e) => { if (e.target === issueModal) closeIssueModal(); });

    // POST helper (JSON)
    const postJson = async (url, payload={}) => {
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
            throw new Error(text || "Request failed");
        }
        return true;
    };

    // POST helper (multipart for file upload)
    const postForm = async (url, formData) => {
        const res = await fetch(url, {
            method: "POST",
            headers: {
                "Accept": "application/json",
                "X-CSRF-TOKEN": csrf
            },
            body: formData
        });
        if (!res.ok) {
            const text = await res.text();
            throw new Error(text || "Request failed");
        }
        return true;
    };

    // Tabulator
    const table = new Tabulator("#clearancesTable", {
        layout: "fitColumns",
        height: "560px",
        responsiveLayout: "collapse",
        placeholder: "No clearances found for this filter.",
        ajaxURL: dataUrl,
        ajaxConfig: "GET",
        pagination: true,
        paginationMode: "remote",
        paginationSize: 20,
        paginationSizeSelector: [10,20,50,100],
        ajaxParams: function(){
            // Always send current URL filters to server
            return Object.fromEntries(new URLSearchParams(window.location.search).entries());
        },
        ajaxResponse: function(url, params, response){
            // Your controller returns {data: [...], meta: {...}}
            // Tabulator remote pagination expects an array OR an object with 'data' and pagination fields.
            // We'll "shape" it into what Tabulator wants:
            // return { data: [...], last_page: X, current_page: Y, total: Z }
            try {
                const data = response?.data ?? [];
                const meta = response?.meta ?? {};
                const shaped = {
                    data: data,
                    current_page: meta.current_page ?? 1,
                    last_page: meta.last_page ?? 1,
                    total: meta.total ?? data.length,
                };
                console.log("Tabulator shaped response:", shaped);
                return shaped;
            } catch(e){
                console.error("Tabulator response parse failed:", e, response);
                return { data: [], current_page: 1, last_page: 1, total: 0 };
            }
        },
        rowFormatter: function(row){
            const d = row.getData() || {};
            const st = (d.status || '').toString();
            const el = row.getElement();
            el.classList.remove(
                "row-status-submitted",
                "row-status-tr8_issued",
                "row-status-arrived",
                "row-status-cancelled"
            );
            if (st === "submitted") el.classList.add("row-status-submitted");
            if (st === "tr8_issued") el.classList.add("row-status-tr8_issued");
            if (st === "arrived") el.classList.add("row-status-arrived");
            if (st === "cancelled") el.classList.add("row-status-cancelled");
        },
        columns: [
            {title: "STATUS", field: "status", width: 150, formatter: (cell) => statusPill(cell.getValue())},
            {title: "CLIENT", field: "client_name", minWidth: 200},
            {title: "TRUCK", field: "truck_number", width: 140},
            {title: "TRAILER", field: "trailer_number", width: 160},
            {
                title: "LOADED @20°C",
                field: "loaded_20_l",
                width: 150,
                hozAlign: "right",
                formatter: (cell) => {
                    const v = cell.getValue();
                    if (v === null || v === undefined || v === "") return "—";
                    const n = Number(v);
                    return Number.isFinite(n) ? n.toLocaleString() : v;
                }
            },
            {title: "TR8", field: "tr8_number", width: 140},
            {title: "BORDER", field: "border_point", width: 160},
            {title: "SUBMITTED", field: "submitted_at", width: 170},
            {title: "ISSUED", field: "tr8_issued_at", width: 170},
            {title: "UPDATED BY", field: "updated_by_name", width: 170},
            {title: "AGE", field: "age_human", width: 120},
            {
                title: "ACTIONS",
                field: "id",
                minWidth: 340,
                headerSort: false,
                formatter: (cell) => {
                    const d = cell.getRow().getData() || {};
                    const id = d.id;
                    const st = d.status;

                    // Compact action buttons (no Open; row itself is clickable)
                    const btn = (label, action, tone="gray") => {
                        const cls = ({
                            gray: "border-gray-200 bg-white text-gray-800 hover:bg-gray-50",
                            dark: "border-gray-900 bg-gray-900 text-white hover:bg-gray-800",
                            amber:"border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100",
                            blue: "border-blue-200 bg-blue-50 text-blue-900 hover:bg-blue-100",
                            rose: "border-rose-200 bg-rose-50 text-rose-900 hover:bg-rose-100",
                            emerald:"border-emerald-200 bg-emerald-50 text-emerald-900 hover:bg-emerald-100",
                        })[tone] || "border-gray-200 bg-white text-gray-800 hover:bg-gray-50";

                        return `<button type="button"
                                    class="px-3 py-1.5 rounded-xl border text-[11px] font-extrabold ${cls}"
                                    data-action="${action}"
                                    data-id="${id}"
                                >${label}</button>`;
                    };

                    if (!canAct) return `<span class="text-[11px] text-gray-400">—</span>`;

                    let html = `<div class="flex flex-wrap items-center gap-2">`;

                    if (st === "draft") {
                        html += btn("Submit", "submit", "dark");
                        html += btn("Cancel", "cancel", "rose");
                    } else if (st === "submitted") {
                        html += btn("Issue TR8", "issue_tr8", "amber");
                        html += btn("Cancel", "cancel", "rose");
                    } else if (st === "tr8_issued") {
                        html += btn("Mark arrived", "arrive", "emerald");
                        html += btn("Cancel", "cancel", "rose");
                    } else {
                        // arrived/cancelled -> no workflow actions
                        html += `<span class="text-[11px] text-gray-400">—</span>`;
                    }

                    html += `</div>`;
                    return html;
                }
            },
        ],
        rowClick: function(e, row){
            // whole row clickable EXCEPT when clicking action buttons
            const t = e.target;
            if (t.closest("button[data-action]")) return;
            const d = row.getData() || {};
            if (d.id) window.location.href = showUrl(d.id);
        }
    });

    // Export respects current filter because Tabulator is remote-filtered already.
    document.getElementById("btnExportXlsx")?.addEventListener("click", function(){
        table.download("xlsx", "clearances.xlsx", {sheetName:"Clearances"});
    });
    document.getElementById("btnExportPdf")?.addEventListener("click", function(){
        table.download("pdf", "clearances.pdf", { orientation: "landscape", title: "Clearances" });
    });

    // ACTION HANDLER
    document.addEventListener("click", async function(e){
        const btn = e.target.closest("button[data-action]");
        if (!btn) return;

        const action = btn.getAttribute("data-action");
        const id = btn.getAttribute("data-id");
        if (!action || !id) return;

        try {
            if (action === "submit") {
                const ok = await openConfirm("Submit clearance", "This will move the clearance to SUBMITTED. Continue?");
                if (!ok) return;
                await postJson(submitUrl(id));
                table.setPage(1); // refresh
                return;
            }

            if (action === "arrive") {
                const ok = await openConfirm("Mark arrived", "Mark this clearance as ARRIVED?");
                if (!ok) return;
                await postJson(arriveUrl(id));
                table.setPage(1);
                return;
            }

            if (action === "cancel") {
                const ok = await openConfirm("Cancel clearance", "Cancel this clearance? This is recorded in the audit trail.");
                if (!ok) return;
                await postJson(cancelUrl(id));
                table.setPage(1);
                return;
            }

            if (action === "issue_tr8") {
                // open modal for TR8 + upload
                openIssueModal(id);
                return;
            }
        } catch(err){
            console.error(err);
            alert("Action failed.\n\n" + (err?.message ? err.message.slice(0, 400) : "Unknown error"));
        }
    });

    // Issue TR8 submit (multipart so we can upload doc)
    issueForm?.addEventListener("submit", async function(e){
        e.preventDefault();
        const id = issueIdEl.value;
        if (!id) return;

        const fd = new FormData(issueForm);

        // Controller currently validates only tr8_number.
        // We'll send `reference` and `document` anyway; controller can ignore until you wire it in.
        // Remove __id from payload
        fd.delete("__id");

        try {
            await postForm(issueUrl(id), fd);
            closeIssueModal();
            table.setPage(1);
        } catch(err){
            console.error(err);
            alert("Issue TR8 failed.\n\n" + (err?.message ? err.message.slice(0, 400) : "Unknown error"));
        }
    });

    // Create modal open
    document.getElementById("btnOpenCreateClearance")?.addEventListener("click", function(){
        const modal = document.getElementById("createClearanceModal");
        if (!modal) return;
        modal.classList.remove("hidden");
        modal.classList.add("flex");
    });

    // Attention panel toggle + outside click close
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

        if (e.target.closest("[data-att-close]")) {
            closeAttention();
            return;
        }

        if (!attWrap.contains(e.target)) closeAttention();
    });

    // Smooth scroll to table if anchor present
    if (window.location.hash === "#clearances") {
        const el = document.getElementById("clearances");
        if (el) el.scrollIntoView({behavior:"smooth", block:"start"});
    }
});
</script>
@endpush