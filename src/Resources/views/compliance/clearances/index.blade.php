@extends('depot-stock::layouts.app')

@section('content')
@php
    use Optima\DepotStock\Models\Clearance;

    $clients    = $clients ?? collect();
    $clearances = $clearances ?? null;

    // Filters
    $fClient = request('client_id');
    $fStatus = request('status');
    $fSearch = request('q');
    $fFrom   = request('from');
    $fTo     = request('to');

    // Roles
    $u = auth()->user();
    $roleNames = $u?->roles?->pluck('name')->map(fn($r) => strtolower($r))->all() ?? [];
    $canCreate = in_array('admin', $roleNames) || in_array('owner', $roleNames) || in_array('compliance', $roleNames);
    $canAct    = $canCreate;

    // Base query for stats (client/search/date filters apply; status varies per-pill)
    $statsBase = Clearance::query();

    if ($fClient) $statsBase->where('client_id', $fClient);

    if ($fSearch) {
        $term = trim($fSearch);
        $statsBase->where(function ($w) use ($term) {
            $w->where('truck_number', 'like', "%{$term}%")
              ->orWhere('trailer_number', 'like', "%{$term}%")
              ->orWhere('tr8_number', 'like', "%{$term}%")
              ->orWhere('invoice_number', 'like', "%{$term}%")
              ->orWhere('delivery_note_number', 'like', "%{$term}%");
        });
    }

    if ($fFrom) $statsBase->whereDate('created_at', '>=', $fFrom);
    if ($fTo)   $statsBase->whereDate('created_at', '<=', $fTo);

    $stats = [
        'total'      => (clone $statsBase)->count(),
        'draft'      => (clone $statsBase)->where('status','draft')->count(),
        'submitted'  => (clone $statsBase)->where('status','submitted')->count(),
        'tr8_issued' => (clone $statsBase)->where('status','tr8_issued')->count(),
        'arrived'    => (clone $statsBase)->where('status','arrived')->count(),
        'cancelled'  => (clone $statsBase)->where('status','cancelled')->count(),
    ];

    // Needs-attention counts (simple v1 logic; you can refine later)
    $attStuckSubmitted = (clone $statsBase)
        ->where('status','submitted')
        ->whereNull('tr8_issued_at')
        ->count();

    $attStuckTr8 = (clone $statsBase)
        ->where('status','tr8_issued')
        ->whereNull('arrived_at')
        ->count();

    $attMissingTr8 = (clone $statsBase)
        ->where('status','tr8_issued')
        ->where(function($w){
            $w->whereNull('tr8_number')->orWhere('tr8_number','');
        })
        ->count();

    // If you track docs properly, swap this query to real missing-doc logic
    $attMissingDocs = 0;

    $attTotal = $attStuckSubmitted + $attStuckTr8 + $attMissingTr8 + $attMissingDocs;

    // Helper URL builder (preserve filters)
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

                {{-- HEADER --}}
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 sm:gap-6">
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

                    {{-- Actions area --}}
                    <div class="flex items-center justify-start sm:justify-end gap-2 sm:gap-3 flex-wrap">
                        @if($canCreate)
                            <button
                                type="button"
                                class="h-9 inline-flex items-center gap-2 rounded-xl bg-gray-900 px-3 sm:px-4 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                                id="btnOpenCreateClearance"
                            >
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-white/10 text-base leading-none">+</span>
                                <span class="hidden sm:inline">New clearance</span>
                                <span class="sm:hidden">New</span>
                            </button>
                        @endif

                        {{-- Needs attention (bell icon + badge + responsive panel) --}}
                        <div class="relative" id="attWrap">
                            <button
                                type="button"
                                id="btnAttention"
                                class="group relative h-9 inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-3 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                                aria-haspopup="true"
                                aria-expanded="false"
                                title="Needs attention"
                            >
                                {{-- Bell icon (modern) --}}
                                <svg class="h-5 w-5 text-gray-700 group-hover:text-gray-900" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 22a2.4 2.4 0 0 0 2.35-2H9.65A2.4 2.4 0 0 0 12 22Z" fill="currentColor" opacity=".9"/>
                                    <path d="M20 17H4c1.7-1.4 2.4-3.1 2.4-5.7V9.4C6.4 6.5 8.5 4.2 12 4.2s5.6 2.3 5.6 5.2v1.9c0 2.6.7 4.3 2.4 5.7Z"
                                          stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                                </svg>

                                {{-- Badge pinned like social apps --}}
                                <span class="absolute -top-2 -right-2 inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1.5 text-[11px] font-bold shadow
                                    {{ $attTotal > 0 ? 'bg-rose-600 text-white' : 'bg-gray-200 text-gray-700' }}">
                                    {{ $attTotal }}
                                </span>
                            </button>

                            {{-- ✅ PANEL FIX: mobile uses fixed + left/right clamp; desktop stays dropdown --}}
                            <div
                                id="attentionPanel"
                                class="hidden z-50 rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden
                                       fixed sm:absolute
                                       left-2 right-2 sm:left-auto sm:right-0
                                       top-20 sm:top-full
                                       sm:mt-2
                                       w-auto sm:w-[22rem]
                                       max-h-[calc(100vh-6rem)] overflow-auto"
                            >
                                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                    <div class="text-sm font-semibold text-gray-900">Needs attention</div>
                                    <button type="button" class="rounded-lg px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50" data-att-close="1">
                                        Close
                                    </button>
                                </div>

                                <div class="p-3 text-[12px] text-gray-600">
                                    <div class="grid grid-cols-1 gap-2">
                                        <a href="{{ $makeFilterUrl(['status' => 'submitted', '__att' => 'stuck_submitted']) }}#clearances"
                                           class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-2 hover:bg-gray-50">
                                            <div class="min-w-0">
                                                <div class="text-[13px] font-semibold text-gray-900">Stuck in Submitted</div>
                                                <div class="text-[11px] text-gray-500">Waiting TR8 issuance</div>
                                            </div>
                                            <div class="inline-flex items-center gap-3">
                                                <span class="rounded-full bg-amber-50 px-2 py-1 text-[11px] font-bold text-amber-900 border border-amber-200">{{ $attStuckSubmitted }}</span>
                                                <span class="text-gray-300">›</span>
                                            </div>
                                        </a>

                                        <a href="{{ $makeFilterUrl(['status' => 'tr8_issued', '__att' => 'stuck_tr8_issued']) }}#clearances"
                                           class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-2 hover:bg-gray-50">
                                            <div class="min-w-0">
                                                <div class="text-[13px] font-semibold text-gray-900">TR8 issued, not arrived</div>
                                                <div class="text-[11px] text-gray-500">Chase truck / dispatch</div>
                                            </div>
                                            <div class="inline-flex items-center gap-3">
                                                <span class="rounded-full bg-blue-50 px-2 py-1 text-[11px] font-bold text-blue-900 border border-blue-200">{{ $attStuckTr8 }}</span>
                                                <span class="text-gray-300">›</span>
                                            </div>
                                        </a>

                                        <a href="{{ $makeFilterUrl(['status' => 'tr8_issued', '__att' => 'missing_tr8_number']) }}#clearances"
                                           class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-2 hover:bg-gray-50">
                                            <div class="min-w-0">
                                                <div class="text-[13px] font-semibold text-gray-900">Missing TR8 number</div>
                                                <div class="text-[11px] text-gray-500">Data risk</div>
                                            </div>
                                            <div class="inline-flex items-center gap-3">
                                                <span class="rounded-full bg-rose-50 px-2 py-1 text-[11px] font-bold text-rose-900 border border-rose-200">{{ $attMissingTr8 }}</span>
                                                <span class="text-gray-300">›</span>
                                            </div>
                                        </a>

                                        <a href="{{ $makeFilterUrl(['__att' => 'missing_documents']) }}#clearances"
                                           class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-2 hover:bg-gray-50">
                                            <div class="min-w-0">
                                                <div class="text-[13px] font-semibold text-gray-900">Missing documents</div>
                                                <div class="text-[11px] text-gray-500">Audit risk</div>
                                            </div>
                                            <div class="inline-flex items-center gap-3">
                                                <span class="rounded-full bg-gray-50 px-2 py-1 text-[11px] font-bold text-gray-800 border border-gray-200">{{ $attMissingDocs }}</span>
                                                <span class="text-gray-300">›</span>
                                            </div>
                                        </a>
                                    </div>
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
                            $base = "inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold";
                            $toneClass = match($tone) {
                                'amber' => "border-amber-200 bg-amber-50 text-amber-900",
                                'blue' => "border-blue-200 bg-blue-50 text-blue-900",
                                'emerald' => "border-emerald-200 bg-emerald-50 text-emerald-900",
                                'rose' => "border-rose-200 bg-rose-50 text-rose-900",
                                default => "border-gray-200 bg-gray-50 text-gray-800",
                            };
                            $countPill = "<span class='inline-flex items-center justify-center rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-bold border border-black/5'>{$count}</span>";
                            return "<a href='{$href}' class='{$base} {$toneClass} hover:shadow-sm transition'><span>{$label}</span>{$countPill}</a>";
                        };
                    @endphp

                    {!! $pill('Total', (int)$stats['total'], '', 'gray') !!}
                    {!! $pill('Draft', (int)$stats['draft'], 'draft', 'gray') !!}
                    {!! $pill('Submitted', (int)$stats['submitted'], 'submitted', 'amber') !!}
                    {!! $pill('TR8 Issued', (int)$stats['tr8_issued'], 'tr8_issued', 'blue') !!}
                    {!! $pill('Arrived', (int)$stats['arrived'], 'arrived', 'emerald') !!}
                    {!! $pill('Cancelled', (int)$stats['cancelled'], 'cancelled', 'rose') !!}
                </div>

<form method="GET" class="mt-4 rounded-2xl border border-gray-200 bg-white p-3">
    <div class="clr-filters">

        {{-- Client --}}
        <div>
            <select name="client_id"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10">
                <option value="">All clients</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" @selected((string)$fClient === (string)$c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Status --}}
        <div>
            <select name="status"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10">
                <option value="">All</option>
                <option value="draft" @selected($fStatus==='draft')>Draft</option>
                <option value="submitted" @selected($fStatus==='submitted')>Submitted</option>
                <option value="tr8_issued" @selected($fStatus==='tr8_issued')>TR8 issued</option>
                <option value="arrived" @selected($fStatus==='arrived')>Arrived</option>
                <option value="cancelled" @selected($fStatus==='cancelled')>Cancelled</option>
            </select>
        </div>

        {{-- Search --}}
        <div>
            <input name="q" value="{{ $fSearch }}" placeholder="Truck, trailer, TR8, invoice…"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10" />
        </div>

        {{-- From --}}
        <div>
            <input type="date" name="from" value="{{ $fFrom }}"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10" />
        </div>

        {{-- To --}}
        <div>
            <input type="date" name="to" value="{{ $fTo }}"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10" />
        </div>

        {{-- Actions --}}
        <div class="clr-filters-actions">
            <a href="{{ url()->current() }}"
               class="h-9 inline-flex items-center rounded-xl border border-gray-200 bg-white px-3 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                Reset
            </a>
            <button type="submit"
                class="h-9 inline-flex items-center rounded-xl bg-gray-900 px-4 text-sm font-semibold text-white hover:bg-gray-800">
                Apply
            </button>
        </div>

    </div>
</form>

                {{-- LIST HEADER + EXPORTS --}}
                <div id="clearances" class="mt-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">Clearances</div>
                        <div class="text-xs text-gray-500">Row click opens record. Actions update workflow.</div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button" class="h-9 inline-flex items-center rounded-xl border border-gray-200 bg-white px-3 text-sm font-semibold text-gray-800 hover:bg-gray-50" id="btnExportXlsx">
                            Export Excel
                        </button>
                        <button type="button" class="h-9 inline-flex items-center rounded-xl border border-gray-200 bg-white px-3 text-sm font-semibold text-gray-800 hover:bg-gray-50" id="btnExportPdf">
                            Export PDF
                        </button>
                    </div>
                </div>

                {{-- TABULATOR --}}
                <div class="mt-3 rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-2 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div class="text-xs text-gray-500">Tip: use filters above + click a row</div>
                        <div class="text-xs text-gray-400">Remote pagination enabled</div>
                    </div>

                    <div class="p-3">
                        <div id="clearancesTable"></div>
                    </div>
                </div>

                @if($clearances && method_exists($clearances, 'links'))
                    <div class="mt-4">
                        {{ $clearances->withQueryString()->links() }}
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>

{{-- Create modal --}}
@if($canCreate)
    @include('depot-stock::compliance.clearances._create_modal', ['clients' => $clients])
@endif

{{-- Confirm modal (generic) --}}
<div id="confirmModal" class="hidden fixed inset-0 z-50 items-center justify-center">
    <div class="absolute inset-0 bg-black/30" data-modal-backdrop="confirm"></div>
    <div class="relative w-[min(32rem,calc(100vw-1rem))] rounded-2xl bg-white shadow-2xl border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <div id="confirmTitle" class="text-sm font-semibold text-gray-900">Confirm</div>
            <button class="rounded-lg px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50" data-modal-close="confirm">Close</button>
        </div>
        <div class="p-4">
            <div id="confirmText" class="text-sm text-gray-700">Are you sure?</div>
            <div class="mt-4 flex items-center justify-end gap-2">
                <button class="h-9 rounded-xl border border-gray-200 bg-white px-3 text-sm font-semibold text-gray-800 hover:bg-gray-50" data-modal-close="confirm">
                    Cancel
                </button>
                <button id="confirmOk" class="h-9 rounded-xl bg-gray-900 px-4 text-sm font-semibold text-white hover:bg-gray-800">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
/**
 * Clearances Index – single scripts source of truth
 * - NO modal scripts in partials (prevents JS leaking into DOM / invalid tokens)
 * - Handles: confirm modal, create modal close-on-outside, issue TR8 modal open/close + submit,
 *            Tabulator remote data + workflow actions + refresh, attention panel close-on-outside.
 */
document.addEventListener("DOMContentLoaded", () => {
  // ----------------------------
  // Guards / Config
  // ----------------------------
  if (!window.Tabulator) {
    console.error("Tabulator missing on window. Ensure it is loaded and your layout has @stack('scripts').");
    return;
  }

  const csrf    = @json(csrf_token());
  const canAct  = @json($canAct ?? false);
  const baseUrl = @json(url('depot/compliance/clearances'));
  const dataUrl = @json(route('depot.compliance.clearances.data'));

  // ----------------------------
  // Tiny toast helper (JS only)
  // NOTE: you will add the HTML toast container later; for now, we create it if missing.
  // ----------------------------
  function ensureToastRoot(){
    let root = document.getElementById("toastRoot");
    if (!root) {
      root = document.createElement("div");
      root.id = "toastRoot";
      root.className = "fixed top-4 right-4 z-[60] space-y-2 pointer-events-none";
      document.body.appendChild(root);
    }
    return root;
  }
  function showToast(message, tone="success"){
    const root = ensureToastRoot();
    const wrap = document.createElement("div");
    const toneClass = tone === "error"
      ? "bg-rose-600 text-white"
      : "bg-emerald-600 text-white";

    wrap.className =
      "pointer-events-auto w-[min(24rem,calc(100vw-2rem))] rounded-2xl shadow-2xl border border-black/10 " +
      toneClass;

    wrap.innerHTML = `
      <div class="flex items-start justify-between gap-3 px-4 py-3">
        <div class="text-sm font-semibold">${escapeHtml(message)}</div>
        <button type="button" class="rounded-xl bg-white/15 px-2 py-1 text-xs font-bold hover:bg-white/25">✕</button>
      </div>
    `;

    const closeBtn = wrap.querySelector("button");
    closeBtn.addEventListener("click", () => wrap.remove());
    root.appendChild(wrap);

    setTimeout(() => {
      if (wrap && wrap.parentNode) wrap.remove();
    }, 3500);
  }
  function escapeHtml(str){
    return (str ?? "").toString()
      .replaceAll("&","&amp;")
      .replaceAll("<","&lt;")
      .replaceAll(">","&gt;")
      .replaceAll('"',"&quot;")
      .replaceAll("'","&#039;");
  }

  // ----------------------------
  // Generic modal helpers (works for multiple modals)
  // Conventions:
  //  - Modal wrapper has id="createClearanceModal" or id="issueTr8Modal"
  //  - Backdrop/close buttons have data-close-modal="create_clearance" or "issue_tr8"
  // ----------------------------
  function openModalById(id){
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove("hidden");
    el.classList.add("flex");
    el.setAttribute("aria-hidden", "false");
  }
  function closeModalById(id){
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add("hidden");
    el.classList.remove("flex");
    el.setAttribute("aria-hidden", "true");
  }
  function isOpen(id){
    const el = document.getElementById(id);
    return !!(el && !el.classList.contains("hidden"));
  }

  // Close any modal on clicking its backdrop/close elements
  document.addEventListener("click", (e) => {
    const closer = e.target.closest("[data-close-modal]");
    if (!closer) return;

    const key = closer.getAttribute("data-close-modal");
    if (key === "create_clearance") closeModalById("createClearanceModal");
    if (key === "issue_tr8")        closeModalById("issueTr8Modal");
    if (key === "confirm")          closeModalById("confirmModal");
  });

  // Esc closes open modals + attention panel
  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    if (isOpen("confirmModal")) closeModalById("confirmModal");
    if (isOpen("createClearanceModal")) closeModalById("createClearanceModal");
    if (isOpen("issueTr8Modal")) closeModalById("issueTr8Modal");
    closeAttention();
  });

  // Open create modal
  document.getElementById("btnOpenCreateClearance")?.addEventListener("click", () => {
    openModalById("createClearanceModal");
  });

  // ----------------------------
  // Confirm modal (generic)
  // Requires existing #confirmModal markup in index.
  // ----------------------------
  let confirmResolver = null;
  function confirmUI({ title="Confirm", text="Are you sure?" } = {}){
    const titleEl = document.getElementById("confirmTitle");
    const textEl  = document.getElementById("confirmText");
    if (titleEl) titleEl.textContent = title;
    if (textEl)  textEl.textContent  = text;
    openModalById("confirmModal");
    return new Promise((resolve) => { confirmResolver = resolve; });
  }

  document.getElementById("confirmOk")?.addEventListener("click", () => {
    closeModalById("confirmModal");
    if (confirmResolver) { confirmResolver(true); confirmResolver = null; }
  });

  // If user clicks Cancel buttons inside confirm modal
  document.querySelectorAll('[data-modal-close="confirm"], [data-close-modal="confirm"]').forEach((btn) => {
    btn.addEventListener("click", () => {
      closeModalById("confirmModal");
      if (confirmResolver) { confirmResolver(false); confirmResolver = null; }
    });
  });

  // ----------------------------
  // Attention panel (kept minimal + stable)
  // ----------------------------
  const attBtn   = document.getElementById("btnAttention");
  const attPanel = document.getElementById("attentionPanel");
  const attWrap  = document.getElementById("attWrap");

  function closeAttention(){
    if (!attPanel) return;
    attPanel.classList.add("hidden");
    attBtn?.setAttribute("aria-expanded", "false");
    // reset any pinning styles
    attPanel.style.left = "";
    attPanel.style.right = "";
  }
  function openAttention(){
    if (!attPanel) return;
    attPanel.classList.remove("hidden");
    attBtn?.setAttribute("aria-expanded", "true");

    // Keep inside viewport (pin left if overflowing)
    const rect = attPanel.getBoundingClientRect();
    const pad = 8;
    if (rect.left < pad) {
      attPanel.style.left = "0.5rem";
      attPanel.style.right = "auto";
    } else if (rect.right > (window.innerWidth - pad)) {
      attPanel.style.right = "0.5rem";
      attPanel.style.left = "auto";
    } else {
      attPanel.style.right = "0";
      attPanel.style.left = "";
    }
  }

  attBtn?.addEventListener("click", () => {
    if (!attPanel) return;
    const open = !attPanel.classList.contains("hidden");
    open ? closeAttention() : openAttention();
  });

  document.addEventListener("click", (e) => {
    if (!attWrap || !attPanel) return;
    if (e.target.closest("[data-att-close]")) { closeAttention(); return; }
    if (!attWrap.contains(e.target)) closeAttention();
  });

  // ----------------------------
  // Network helpers
  // ----------------------------
  async function postJson(url, payload={}){
    const res = await fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-CSRF-TOKEN": csrf
      },
      body: JSON.stringify(payload)
    });

    // Laravel may redirect on 302; treat as success for workflow, then refresh
    if (res.status === 302) return { ok: true, redirected: true };

    if (!res.ok) {
      const txt = await res.text().catch(() => "");
      throw new Error(txt || "Request failed");
    }
    return { ok: true };
  }

  // ----------------------------
  // Issue TR8 Modal wiring
  // - Opened from Tabulator action "issue"
  // - Sets form action to /{id}/issue-tr8
  // - Submits as normal multipart form (file upload)
  // ----------------------------
  const issueTr8Modal   = document.getElementById("issueTr8Modal");
  const issueTr8Form    = document.getElementById("issueTr8Form");
  const issueTr8Submit  = document.getElementById("issueTr8Submit");
  const issueTr8Number  = document.getElementById("issueTr8Number");
  const issueTr8Ref     = document.getElementById("issueTr8Reference");
  const issueTr8Doc     = document.getElementById("issueTr8Document");

  function openIssueTr8Modal(clearanceId){
    if (!issueTr8Modal || !issueTr8Form) return;
    // set action dynamically (controller expects POST /{clearance}/issue-tr8)
    issueTr8Form.setAttribute("action", `${baseUrl}/${clearanceId}/issue-tr8`);

    // reset fields for clean UX
    if (issueTr8Number) issueTr8Number.value = "";
    if (issueTr8Ref) issueTr8Ref.value = "";
    if (issueTr8Doc) issueTr8Doc.value = "";

    openModalById("issueTr8Modal");

    // focus after paint
    setTimeout(() => issueTr8Number?.focus(), 50);
  }

  // Submit TR8 form
  issueTr8Submit?.addEventListener("click", () => {
    if (!issueTr8Form) return;
    issueTr8Form.submit();
  });

  // ----------------------------
  // Tabulator: stable remote shape + actions
  // ----------------------------
  const urlParams = () => Object.fromEntries(new URLSearchParams(window.location.search).entries());
  const showUrl   = (id) => `${baseUrl}/${id}`;
  const submitUrl = (id) => `${baseUrl}/${id}/submit`;
  const arriveUrl = (id) => `${baseUrl}/${id}/arrive`;
  const cancelUrl = (id) => `${baseUrl}/${id}/cancel`;

  const statusMeta = (s) => ({
    draft:     { cls:"border-gray-200 bg-gray-50 text-gray-900",   row:"bg-white" },
    submitted: { cls:"border-amber-200 bg-amber-50 text-amber-900", row:"bg-amber-50/30" },
    tr8_issued:{ cls:"border-blue-200 bg-blue-50 text-blue-900",   row:"bg-blue-50/25" },
    arrived:   { cls:"border-emerald-200 bg-emerald-50 text-emerald-900", row:"bg-emerald-50/25" },
    cancelled: { cls:"border-rose-200 bg-rose-50 text-rose-900",   row:"bg-rose-50/25" },
  }[s] || { cls:"border-gray-200 bg-gray-50 text-gray-900", row:"bg-white" });

  const table = new Tabulator("#clearancesTable", {
    layout: "fitColumns",
    responsiveLayout: "collapse",
    height: "560px",
    placeholder: "No clearances found for this filter.",
    pagination: true,
    paginationMode: "remote",
    paginationSize: 20,
    ajaxURL: dataUrl,
    ajaxParams: urlParams,

    // Your API returns: { data: [...], meta: {...} }
    ajaxResponse: function(_url, _params, resp){
      const shaped = {
        data: Array.isArray(resp?.data) ? resp.data : [],
        current_page: resp?.meta?.current_page ?? 1,
        last_page:    resp?.meta?.last_page ?? 1,
        per_page:     resp?.meta?.per_page ?? 20,
        total:        resp?.meta?.total ?? (Array.isArray(resp?.data) ? resp.data.length : 0),
      };
      return shaped;
    },
    paginationDataReceived: {
      "last_page":"last_page",
      "data":"data",
      "current_page":"current_page",
      "total":"total"
    },

    rowFormatter: function(row){
      const d = row.getData();
      const meta = statusMeta(d.status);
      row.getElement().classList.add(meta.row);
    },

    rowClick: function(e, row){
      if (e.target.closest("button")) return;
      const id = row.getData().id;
      if (id) window.location.href = showUrl(id);
    },

    columns: [
      {
        title: "STATUS",
        field: "status",
        width: 150,
        formatter: (cell) => {
          const s = (cell.getValue() || "").toString();
          const label = s.replaceAll("_"," ").toUpperCase();
          const meta = statusMeta(s);
          return `<span class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-semibold ${meta.cls}">${label || "-"}</span>`;
        }
      },
      { title: "CLIENT", field: "client_name", minWidth: 180 },
      { title: "TRUCK", field: "truck_number", width: 130 },
      { title: "TRAILER", field: "trailer_number", width: 140 },
      { title: "LOADED @20°C", field: "loaded_20_l", hozAlign:"right", width: 140 },
      { title: "TR8", field: "tr8_number", width: 130 },
      { title: "BORDER", field: "border_point", width: 140 },
      { title: "SUBMITTED", field: "submitted_at", width: 160 },
      { title: "ISSUED", field: "tr8_issued_at", width: 160 },
      { title: "UPDATED BY", field: "updated_by_name", width: 150 },
      { title: "AGE", field: "age_human", width: 110 }, // stays as its own column (not stacked)
      {
        title: "ACTIONS",
        field: "id",
        headerSort: false,
        width: 290,
        formatter: (cell) => {
          if (!canAct) return `<span class="text-xs text-gray-400">No actions</span>`;
          const data = cell.getRow().getData();
          const id = data.id;
          const s  = data.status;

          const btn = (label, action, tone="gray") => {
            const toneClass = ({
              gray:   "border-gray-200 hover:bg-gray-50 text-gray-800",
              dark:   "border-gray-900 bg-gray-900 text-white hover:bg-gray-800",
              amber:  "border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100",
              blue:   "border-blue-200 bg-blue-50 text-blue-900 hover:bg-blue-100",
              rose:   "border-rose-200 bg-rose-50 text-rose-900 hover:bg-rose-100",
              emerald:"border-emerald-200 bg-emerald-50 text-emerald-900 hover:bg-emerald-100",
            })[tone] || "border-gray-200 hover:bg-gray-50 text-gray-800";

            return `<button type="button" class="px-3 py-1.5 rounded-xl border text-xs font-semibold ${toneClass}" data-action="${action}" data-id="${id}">${label}</button>`;
          };

          let html = `<div class="flex items-center gap-2">`;
          if (s === "draft") html += btn("Submit", "submit", "dark");
          if (s === "submitted") {
            html += btn("Issue TR8", "issue", "amber");
            html += btn("Cancel", "cancel", "rose");
          }
          if (s === "tr8_issued") {
            html += btn("Arrived", "arrive", "emerald");
            html += btn("Cancel", "cancel", "rose");
          }
          if (s === "arrived") {
            html += btn("Cancel", "cancel", "rose");
          }
          html += `</div>`;
          return html;
        }
      }
    ],
  });

  function refreshTable(){
    // safest: keep current pagination + filters
    table.setData(dataUrl, urlParams());
  }

  // ----------------------------
  // Action clicks (submit/arrive/cancel/issue)
  // ----------------------------
  document.addEventListener("click", async (e) => {
    const btn = e.target.closest("button[data-action][data-id]");
    if (!btn) return;

    const action = btn.getAttribute("data-action");
    const id     = btn.getAttribute("data-id");

    try {
      if (action === "issue") {
        openIssueTr8Modal(id);
        return;
      }

      if (action === "submit") {
        const ok = await confirmUI({ title:"Submit clearance", text:"Submit this clearance now?" });
        if (!ok) return;
        await postJson(submitUrl(id));
        showToast("Submitted.", "success");
        refreshTable();
        return;
      }

      if (action === "arrive") {
        const ok = await confirmUI({ title:"Mark arrived", text:"Mark this clearance as arrived?" });
        if (!ok) return;
        await postJson(arriveUrl(id));
        showToast("Marked arrived.", "success");
        refreshTable();
        return;
      }

      if (action === "cancel") {
        const ok = await confirmUI({ title:"Cancel clearance", text:"Cancel this clearance? This will update status." });
        if (!ok) return;
        await postJson(cancelUrl(id));
        showToast("Cancelled.", "success");
        refreshTable();
        return;
      }
    } catch (err) {
      console.error(err);
      showToast((err?.message || "Action failed").toString().slice(0, 180), "error");
    }
  });

  // ----------------------------
  // Exports (current loaded set)
  // ----------------------------
  document.getElementById("btnExportXlsx")?.addEventListener("click", () => {
    table.download("xlsx", "clearances.xlsx", { sheetName: "Clearances" });
  });
  document.getElementById("btnExportPdf")?.addEventListener("click", () => {
    table.download("pdf", "clearances.pdf", { orientation: "landscape", title: "Clearances" });
  });

  // ----------------------------
  // Optional: if you land on #clearances, scroll nicely
  // ----------------------------
  if (window.location.hash === "#clearances") {
    const el = document.getElementById("clearances");
    if (el) el.scrollIntoView({ behavior: "smooth", block: "start" });
  }
});
</script>
@endpush