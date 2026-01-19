@extends('depot-stock::layouts.app')

@section('content')
@php
    $clients = $clients ?? collect();

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

    // Role gating (match your existing style; hasRole expects string)
    $u = auth()->user();
    $roleNames = $u?->roles?->pluck('name')->map(fn($r) => strtolower($r))->all() ?? [];
    $canCreate = in_array('admin', $roleNames) || in_array('owner', $roleNames) || in_array('compliance', $roleNames);
    $canAct    = $canCreate;

    // Current filters (GET)
    $fClient = request('client_id');
    $fStatus = request('status');
    $fSearch = request('q');
    $fFrom   = request('from');
    $fTo     = request('to');

    // Attention totals
    $attStuckSubmitted = (int)($stats['stuck_submitted'] ?? 0);
    $attStuckTr8       = (int)($stats['stuck_tr8_issued'] ?? 0);
    $attMissingTr8     = (int)($stats['missing_tr8_number'] ?? 0);
    $attMissingDocs    = (int)($stats['missing_documents'] ?? 0);
    $attTotal          = $attStuckSubmitted + $attStuckTr8 + $attMissingTr8 + $attMissingDocs;

    // Build URLs preserving other filters
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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mt-6">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="p-5 sm:p-6">

                {{-- Header --}}
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="text-xs text-gray-500">Compliance</div>
                        <h1 class="mt-1 text-xl font-semibold tracking-tight text-gray-900">Clearances and TR8</h1>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                            <span class="inline-flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                Live
                            </span>
                            <span class="hidden sm:inline">-</span>
                            <span>Refreshed: <span class="font-medium text-gray-700">{{ $now->format('m/d/Y, g:i A') }}</span></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        @if($canCreate)
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                                id="btnOpenCreateClearance"
                            >
                                <span class="text-base leading-none">+</span>
                                <span>New</span>
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
                                {{-- modern bell icon --}}
                                <svg class="h-5 w-5 text-gray-700 group-hover:text-gray-900" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 22a2.5 2.5 0 0 0 2.45-2H9.55A2.5 2.5 0 0 0 12 22Z" fill="currentColor" opacity="0.9"/>
                                    <path d="M20 17H4c1.8-1.5 2.5-3.3 2.5-6V9.5C6.5 6.46 8.7 4 12 4s5.5 2.46 5.5 5.5V11c0 2.7.7 4.5 2.5 6Z"
                                          stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                </svg>

                                @if($attTotal > 0)
                                    <span class="absolute -top-2 -right-2 inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1.5 text-[11px] font-bold text-white shadow">
                                        {{ $attTotal }}
                                    </span>
                                @endif
                            </button>

                            {{-- Attention popover --}}
                            <div
                                id="attentionPanel"
                                class="hidden absolute right-0 mt-2 w-[44rem] sm:w-[34rem] rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden z-40"
                            >
                                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                    <div class="text-sm font-semibold text-gray-900">Needs attention</div>
                                    <button type="button" class="rounded-lg px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50" data-att-close="1">
                                        Close
                                    </button>
                                </div>

                                <div class="p-2">
                                    <a href="{{ $makeFilterUrl(['status' => 'submitted', '__att' => 'stuck_submitted']) }}#clearances"
                                       class="flex items-center justify-between rounded-xl px-3 py-2 hover:bg-gray-50">
                                        <div class="min-w-0">
                                            <div class="text-[13px] font-semibold text-gray-900">Stuck in Submitted</div>
                                            <div class="text-[11px] text-gray-500">Waiting TR8 issuance</div>
                                        </div>
                                        <div class="inline-flex items-center gap-2">
                                            <span class="rounded-full bg-amber-50 px-2 py-1 text-[11px] font-bold text-amber-900 border border-amber-200">{{ $attStuckSubmitted }}</span>
                                            <svg class="h-4 w-4 text-gray-300" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </div>
                                    </a>

                                    <a href="{{ $makeFilterUrl(['status' => 'tr8_issued', '__att' => 'stuck_tr8_issued']) }}#clearances"
                                       class="flex items-center justify-between rounded-xl px-3 py-2 hover:bg-gray-50">
                                        <div class="min-w-0">
                                            <div class="text-[13px] font-semibold text-gray-900">TR8 issued, not arrived</div>
                                            <div class="text-[11px] text-gray-500">Chase dispatch</div>
                                        </div>
                                        <div class="inline-flex items-center gap-2">
                                            <span class="rounded-full bg-blue-50 px-2 py-1 text-[11px] font-bold text-blue-900 border border-blue-200">{{ $attStuckTr8 }}</span>
                                            <svg class="h-4 w-4 text-gray-300" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </div>
                                    </a>

                                    <a href="{{ $makeFilterUrl(['__att' => 'missing_tr8_number']) }}#clearances"
                                       class="flex items-center justify-between rounded-xl px-3 py-2 hover:bg-gray-50">
                                        <div class="min-w-0">
                                            <div class="text-[13px] font-semibold text-gray-900">Missing TR8 number</div>
                                            <div class="text-[11px] text-gray-500">Data risk</div>
                                        </div>
                                        <div class="inline-flex items-center gap-2">
                                            <span class="rounded-full bg-rose-50 px-2 py-1 text-[11px] font-bold text-rose-900 border border-rose-200">{{ $attMissingTr8 }}</span>
                                            <svg class="h-4 w-4 text-gray-300" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </div>
                                    </a>

                                    <a href="{{ $makeFilterUrl(['__att' => 'missing_documents']) }}#clearances"
                                       class="flex items-center justify-between rounded-xl px-3 py-2 hover:bg-gray-50">
                                        <div class="min-w-0">
                                            <div class="text-[13px] font-semibold text-gray-900">Missing documents</div>
                                            <div class="text-[11px] text-gray-500">Audit risk</div>
                                        </div>
                                        <div class="inline-flex items-center gap-2">
                                            <span class="rounded-full bg-gray-50 px-2 py-1 text-[11px] font-bold text-gray-800 border border-gray-200">{{ $attMissingDocs }}</span>
                                            <svg class="h-4 w-4 text-gray-300" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Status pills (dashboard) --}}
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @php
                        $pill = function($label, $count, $tone) {
                            $base = "inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold";
                            $toneClass = match($tone) {
                                'amber' => "border-amber-200 bg-amber-50 text-amber-900",
                                'blue' => "border-blue-200 bg-blue-50 text-blue-900",
                                'emerald' => "border-emerald-200 bg-emerald-50 text-emerald-900",
                                'rose' => "border-rose-200 bg-rose-50 text-rose-900",
                                default => "border-gray-200 bg-gray-50 text-gray-800",
                            };
                            return <<<HTML
                                <span class="{$base} {$toneClass}">
                                    <span>{$label}</span>
                                    <span class="inline-flex items-center justify-center rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-bold border border-black/5">{$count}</span>
                                </span>
                            HTML;
                        };
                    @endphp

                    {!! $pill('Total', (int)($stats['total'] ?? 0), 'gray') !!}
                    {!! $pill('Draft', (int)($stats['draft'] ?? 0), 'gray') !!}
                    {!! $pill('Submitted', (int)($stats['submitted'] ?? 0), 'amber') !!}
                    {!! $pill('TR8 Issued', (int)($stats['tr8_issued'] ?? 0), 'blue') !!}
                    {!! $pill('Arrived', (int)($stats['arrived'] ?? 0), 'emerald') !!}
                    {!! $pill('Cancelled', (int)($stats['cancelled'] ?? 0), 'rose') !!}
                </div>

                {{-- Filters (one-line ops bar) --}}
                <form method="GET" class="mt-4 rounded-2xl border border-gray-200 bg-white px-3 py-2">
                    <div class="flex items-center gap-2 overflow-x-auto whitespace-nowrap">
                        <div class="shrink-0">
                            <label class="sr-only">Client</label>
                            <select name="client_id"
                                class="w-48 rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10">
                                <option value="">All clients</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}" @selected((string)$fClient === (string)$c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="shrink-0">
                            <label class="sr-only">Status</label>
                            <select name="status"
                                class="w-40 rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10">
                                <option value="">All status</option>
                                <option value="draft" @selected($fStatus==='draft')>Draft</option>
                                <option value="submitted" @selected($fStatus==='submitted')>Submitted</option>
                                <option value="tr8_issued" @selected($fStatus==='tr8_issued')>TR8 issued</option>
                                <option value="arrived" @selected($fStatus==='arrived')>Arrived</option>
                                <option value="cancelled" @selected($fStatus==='cancelled')>Cancelled</option>
                            </select>
                        </div>

                        <div class="shrink-0">
                            <label class="sr-only">Search</label>
                            <input
                                name="q"
                                value="{{ $fSearch }}"
                                placeholder="Search truck, trailer, TR8, invoice"
                                class="w-64 rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                            />
                        </div>

                        <div class="shrink-0">
                            <label class="sr-only">From</label>
                            <input type="date" name="from" value="{{ $fFrom }}"
                                class="w-40 rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10" />
                        </div>

                        <div class="shrink-0">
                            <label class="sr-only">To</label>
                            <input type="date" name="to" value="{{ $fTo }}"
                                class="w-40 rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10" />
                        </div>

                        <div class="shrink-0 flex items-center gap-2">
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

                {{-- List header + compact exports --}}
                <div id="clearances" class="mt-5 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">Clearances</div>
                        <div class="text-xs text-gray-500">Row click opens details. Actions use modals.</div>
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

                {{-- Tabulator surface --}}
                <div class="mt-3 rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between">
                        <div class="text-xs text-gray-500">
                            Tip: click a row to open. Use Actions for workflow.
                        </div>
                        <div class="text-xs text-gray-400">
                            Exports respect current filters.
                        </div>
                    </div>

                    <div class="p-3 bg-gray-50">
                        <div id="clearancesTable"></div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

{{-- Modals --}}
@if($canCreate)
    @include('depot-stock::compliance.clearances._create_modal', ['clients' => $clients])
@endif

@include('depot-stock::compliance.clearances._confirm_modal')
@include('depot-stock::compliance.clearances._issue_tr8_modal')

@endsection

@push('styles')
<style>
    /* Premium Tabulator skin (light surface + clean header) */
    #clearancesTable .tabulator {
        border: 0;
        border-radius: 16px;
        background: #ffffff;
        overflow: hidden;
    }
    #clearancesTable .tabulator-header {
        background: rgba(255,255,255,0.9);
        border-bottom: 1px solid rgba(0,0,0,0.06);
    }
    #clearancesTable .tabulator-col,
    #clearancesTable .tabulator-col-content {
        padding-top: 10px;
        padding-bottom: 10px;
    }
    #clearancesTable .tabulator-col-title {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.02em;
        color: rgba(17,24,39,0.8);
    }
    #clearancesTable .tabulator-row {
        border-bottom: 1px solid rgba(0,0,0,0.04);
    }
    #clearancesTable .tabulator-row:hover {
        background: rgba(17,24,39,0.03);
    }

    /* Left status accent (recommended vs full-row tint) */
    #clearancesTable .row-accent {
        position: relative;
    }
    #clearancesTable .row-accent:before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: rgba(107,114,128,0.4); /* default gray */
    }
    #clearancesTable .accent-draft:before { background: rgba(107,114,128,0.45); }
    #clearancesTable .accent-submitted:before { background: rgba(245,158,11,0.65); }
    #clearancesTable .accent-tr8_issued:before { background: rgba(59,130,246,0.65); }
    #clearancesTable .accent-arrived:before { background: rgba(16,185,129,0.65); }
    #clearancesTable .accent-cancelled:before { background: rgba(244,63,94,0.65); }

    /* Actions buttons inside table */
    .tbl-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 10px;
        border-radius: 12px;
        border: 1px solid rgba(0,0,0,0.10);
        font-size: 12px;
        font-weight: 700;
        background: #fff;
        color: rgba(17,24,39,0.9);
        cursor: pointer;
    }
    .tbl-btn:hover { background: rgba(17,24,39,0.03); }
    .tbl-btn-dark { background: rgba(17,24,39,1); color: #fff; border-color: rgba(17,24,39,1); }
    .tbl-btn-dark:hover { background: rgba(17,24,39,0.9); }
    .tbl-btn-amber { border-color: rgba(245,158,11,0.35); background: rgba(245,158,11,0.10); color: rgba(120,53,15,1); }
    .tbl-btn-blue { border-color: rgba(59,130,246,0.35); background: rgba(59,130,246,0.10); color: rgba(30,64,175,1); }
    .tbl-btn-emerald { border-color: rgba(16,185,129,0.35); background: rgba(16,185,129,0.10); color: rgba(6,95,70,1); }
    .tbl-btn-rose { border-color: rgba(244,63,94,0.35); background: rgba(244,63,94,0.10); color: rgba(159,18,57,1); }
</style>
@endpush

@@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function(){
    if (!window.Tabulator) {
        console.error("Tabulator missing on window.");
        return;
    }

    const canAct  = @json($canAct);
    const dataUrl = @json(route('depot.compliance.clearances.data'));
    const csrf    = @json(csrf_token());

    // ---------- helpers ----------
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

    // Create modal
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

    if (window.location.hash === "#clearances") {
        document.getElementById("clearances")?.scrollIntoView({behavior:"smooth", block:"start"});
    }

    // Confirm modal plumbing (if present)
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

    // Issue TR8 modal plumbing (if present)
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

    // ---------- table ----------
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

    const showBase = @json(url('depot/compliance/clearances'));

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
            const params = Object.fromEntries(new URLSearchParams(window.location.search).entries());
            return params;
        },

        // ✅ map your controller meta -> Tabulator remote pagination expectations
        paginationDataReceived: {
            last_page: "meta.last_page",
            data: "data",
            current_page: "meta.current_page",
            per_page: "meta.per_page",
            total: "meta.total",
        },

        ajaxResponse: function(url, params, response){
            console.log("Tabulator response:", response);
            // Ensure we always return array for rows
            if (Array.isArray(response)) return response;
            return response?.data || [];
        },

        rowFormatter: function(row){
            const data = row.getData();
            const el = row.getElement();
            el.classList.add("row-accent");
            el.classList.remove("accent-draft","accent-submitted","accent-tr8_issued","accent-arrived","accent-cancelled");
            const s = (data.status || "draft").toString();
            el.classList.add("accent-" + s);
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

        rowClick: function(e, row){
            const t = e.target;
            if (t.closest("button") || t.closest("a")) return;
            const data = row.getData();
            if (data?.id) window.location.href = showBase + "/" + data.id;
        },
    });

    // Exports (current loaded view)
    document.getElementById("btnExportXlsx")?.addEventListener("click", function(){
        table.download("xlsx", "clearances.xlsx", {sheetName:"Clearances"});
    });
    document.getElementById("btnExportPdf")?.addEventListener("click", function(){
        table.download("pdf", "clearances.pdf", { orientation: "landscape", title: "Clearances" });
    });

    // Action handling with modals
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
});
</script>
@endpush