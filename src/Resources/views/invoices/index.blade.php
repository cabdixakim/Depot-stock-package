@extends('depot-stock::layouts.app')

@section('title', 'Invoices')

@section('content')
@php
    // ---------- Helpers ----------
    $fmtMoney = function ($v, $ccy = 'USD') {
        return $ccy . ' ' . number_format((float) $v, 2, '.', ',');
    };

    $statusPill = function ($s) {
        return match ($s) {
            'draft'   => ['bg-gray-100',  'text-gray-700'],
            'issued'  => ['bg-blue-100',  'text-blue-700'],
            'partial' => ['bg-amber-100', 'text-amber-700'],
            'paid'    => ['bg-emerald-100','text-emerald-700'],
            'void'    => ['bg-rose-100',  'text-rose-700'],
            default   => ['bg-gray-100',  'text-gray-700'],
        };
    };

    /** @var \Illuminate\Support\Collection $invoices */
    $collection     = $invoices ?? collect();
    $count          = $collection->count();
    $total          = (float) $collection->sum('total');
    $ccyForKpis     = $collection->first()->currency ?? 'USD';
    $contextClient  = $contextClient ?? null;

    // Which clients are on this screen?
    $clientIdsOnPage = $collection->pluck('client_id')->filter()->unique();

    // ---------- AVAILABLE CREDITS ----------
    try {
        $creditsQuery = \Optima\DepotStock\Models\ClientCredit::query()
            ->selectRaw('client_id, SUM(remaining) as remaining');

        if ($contextClient) {
            $creditsQuery->where('client_id', $contextClient->id);
        } elseif ($clientIdsOnPage->isNotEmpty()) {
            $creditsQuery->whereIn('client_id', $clientIdsOnPage);
        }

        $creditsByClient = $creditsQuery
            ->groupBy('client_id')
            ->pluck('remaining', 'client_id');   // [client_id => remaining]
    } catch (\Throwable $e) {
        $creditsByClient = collect();
    }

    if ($contextClient) {
        $totalCredits = (float) ($creditsByClient[$contextClient->id] ?? 0);
    } else {
        $totalCredits = (float) $creditsByClient->sum();
    }

    // ---------- PAYMENTS (EXCLUDING CREDITS) ----------
    $paymentsQuery = \Optima\DepotStock\Models\Payment::query()
        ->whereNotIn('mode', ['credit', 'credit apply']);

    if ($contextClient) {
        $paymentsQuery->where('client_id', $contextClient->id);
    } elseif ($clientIdsOnPage->isNotEmpty()) {
        $paymentsQuery->whereIn('client_id', $clientIdsOnPage);
    }

    $paid = (float) $paymentsQuery->sum('amount');

    // ---------- OUTSTANDING ----------
    $bal = max(0, $total - $paid);
@endphp

<div class="min-h-[100dvh] bg-[#F7FAFC]">

    {{-- Sticky header --}}
    <div class="sticky top-0 z-20 bg-white/70 backdrop-blur border-b border-gray-100">
        <div class="mx-auto max-w-7xl px-4 md:px-6 h-14 flex items-center justify-between">
            <div class="leading-tight">
                {{-- Back button --}}
                <button type="button"
                        onclick="window.history.back()"
                        class="mb-1 inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-3 py-1 text-xs text-gray-600 hover:bg-gray-50">
                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M12.293 4.293a1 1 0 010 1.414L9 9h7a1 1 0 110 2H9l3.293 3.293a1 1 0 11-1.414 1.414l-4.999-5a1 1 0 010-1.414l5-5a1 1 0 011.414 0z" />
                    </svg>
                    <span>Back</span>
                </button>

                <div class="flex items-center gap-2">
                    <div>
                        <div class="text-[11px] uppercase tracking-wide text-gray-500">Billing</div>
                        <h1 class="font-semibold text-gray-900">Invoices</h1>
                    </div>

                    {{-- Scope pill --}}
                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-[11px] text-blue-700">
                        <span class="h-1.5 w-1.5 rounded-full {{ $contextClient ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                        @if($contextClient)
                            For: {{ $contextClient->name }}
                        @else
                            For: All clients
                        @endif
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 md:px-6 py-6 space-y-6">

        {{-- KPI cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-5 gap-4">
            <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                <div class="text-[11px] uppercase tracking-wide text-gray-500">Invoices</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($count) }}</div>
                <div class="text-[11px] text-gray-400 mt-1">Total records</div>
            </div>

            <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                <div class="text-[11px] uppercase tracking-wide text-gray-500">Billed</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $fmtMoney($total, $ccyForKpis) }}</div>
                <div class="text-[11px] text-gray-400 mt-1">Total invoice value</div>
            </div>

            <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                <div class="text-[11px] uppercase tracking-wide text-gray-500">Received</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $fmtMoney($paid, $ccyForKpis) }}</div>
                <div class="text-[11px] text-gray-400 mt-1">Cash & bank payments (no credits)</div>
            </div>

            <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                <div class="text-[11px] uppercase tracking-wide text-gray-500">Outstanding</div>
                <div class="mt-1 text-2xl font-semibold {{ $bal > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                    {{ $fmtMoney($bal, $ccyForKpis) }}
                </div>
                <div class="text-[11px] text-gray-400 mt-1">Unpaid balance</div>
            </div>

            <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                <div class="text-[11px] uppercase tracking-wide text-gray-500">Available credits</div>
                <div class="mt-1 text-2xl font-semibold text-indigo-700">
                    {{ $fmtMoney($totalCredits, $ccyForKpis) }}
                </div>
                <div class="text-[11px] text-gray-400 mt-1">
                    {{ $contextClient ? 'Client credit balance' : 'Unapplied credits (shown clients)' }}
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 p-4">
            <div class="flex flex-col md:flex-row md:items-end gap-3">
                <div class="w-full md:w-80">
                    <label class="block text-[11px] uppercase tracking-wide text-gray-500">Search</label>
                    <div class="mt-1 relative">
                        <input id="q" type="text" placeholder="Search by number or client"
                               class="w-full rounded-xl border border-gray-200 pl-9 pr-3 py-2 focus:outline-none focus:border-gray-400">
                        <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="11" cy="11" r="8" />
                            <path d="M21 21l-4.3-4.3" />
                        </svg>
                    </div>
                </div>
                <div class="flex items-end gap-3 md:ml-auto">
                    <div>
                        <label class="block text-[11px] uppercase tracking-wide text-gray-500">From</label>
                        <input id="from" type="date"
                               class="mt-1 rounded-xl border border-gray-200 py-2 px-3 focus:border-gray-400">
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase tracking-wide text-gray-500">To</label>
                        <input id="to" type="date"
                               class="mt-1 rounded-xl border border-gray-200 py-2 px-3 focus:border-gray-400">
                    </div>
                    <button id="clearFilters"
                            class="mt-5 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm hover:bg-gray-50">
                        Reset
                    </button>
                </div>
            </div>
        </div>

        {{-- Status tabs --}}
        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 px-2 py-2">
            <div id="tabsWrap" class="flex overflow-x-auto no-scrollbar relative">
                <button class="tab-btn active" data-status="">All</button>
                <button class="tab-btn" data-status="draft">Draft</button>
                <button class="tab-btn" data-status="issued">Issued</button>
                <button class="tab-btn" data-status="partial">Partial</button>
                <button class="tab-btn" data-status="paid">Paid</button>
                <button class="tab-btn" data-status="void">Void</button>
                <div id="tabUnderline" class="absolute bottom-0 h-0.5 bg-gray-900 rounded-full transition-all duration-300"></div>
            </div>
        </div>

        {{-- List --}}
        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">

            {{-- Desktop table --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gradient-to-b from-gray-50 to-white border-b border-gray-200">
                    <tr>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide">Number</th>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide">Client</th>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide">Date</th>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide">Status</th>
                        <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wide">Total</th>
                        <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wide">Paid</th>
                        <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wide">Balance</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                    </thead>
                    <tbody id="tblBody" class="divide-y divide-gray-100">
                    @forelse($invoices as $inv)
                        @php
                            $ccy        = $inv->currency ?? $ccyForKpis;
                            $paidAmt    = (float) ($inv->paid_total ?? ($inv->payments_sum_amount ?? 0));
                            $balance    = max(0, ((float) $inv->total) - $paidAmt);
                            [$bg,$tx]   = $statusPill($inv->status ?? 'draft');
                            $clientCred = (float) ($creditsByClient[$inv->client_id] ?? 0);
                        @endphp
                        <tr class="invoice-row hover:bg-gray-50 transition"
                            data-number="{{ $inv->number }}"
                            data-client="{{ optional($inv->client)->name }}"
                            data-status="{{ $inv->status }}"
                            data-date="{{ optional($inv->date)->format('Y-m-d') }}">
                            <td class="px-3 py-3 font-medium text-gray-900">{{ $inv->number }}</td>

                            <td class="px-3 py-3 text-gray-800">
                                <div class="flex items-center gap-2">
                                    <span>{{ optional($inv->client)->name ?? '—' }}</span>
                                    @if($clientCred > 0.0001)
                                        <span title="Unapplied credit"
                                              class="inline-flex items-center gap-1 rounded-full bg-indigo-50 text-indigo-700 px-2 py-0.5 text-[11px]">
                                            <svg class="h-[10px] w-[10px]" viewBox="0 0 24 24" fill="currentColor">
                                                <circle cx="12" cy="12" r="4"/>
                                            </svg>
                                            {{ $fmtMoney($clientCred, $ccy) }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td class="px-3 py-3 text-gray-700">{{ optional($inv->date)->format('Y-m-d') ?? '—' }}</td>

                            <td class="px-3 py-3">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs {{ $bg }} {{ $tx }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current/60"></span>
                                    {{ ucfirst($inv->status ?? 'draft') }}
                                </span>
                            </td>

                            <td class="px-3 py-3 text-right text-gray-900 font-semibold">
                                {{ $fmtMoney($inv->total, $ccy) }}
                            </td>
                            <td class="px-3 py-3 text-right text-gray-700">
                                {{ $fmtMoney($paidAmt, $ccy) }}
                            </td>
                            <td class="px-3 py-3 text-right {{ $balance > 0 ? 'text-amber-700' : 'text-emerald-700' }} font-medium">
                                {{ $fmtMoney($balance, $ccy) }}
                            </td>

                            <td class="px-3 py-3 text-right">
                                <a href="{{ route('depot.invoices.show', $inv) }}"
                                   class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-10 text-center text-gray-500">No invoices found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div id="cardsBody" class="md:hidden divide-y divide-gray-100">
                @foreach($invoices as $inv)
                    @php
                        $ccy        = $inv->currency ?? $ccyForKpis;
                        $paidAmt    = (float) ($inv->paid_total ?? ($inv->payments_sum_amount ?? 0));
                        $balance    = max(0, ((float) $inv->total) - $paidAmt);
                        [$bg,$tx]   = $statusPill($inv->status ?? 'draft');
                        $clientCred = (float) ($creditsByClient[$inv->client_id] ?? 0);
                    @endphp
                    <a href="{{ route('depot.invoices.show', $inv) }}"
                       class="block p-4 hover:bg-gray-50 transition invoice-card"
                       data-status="{{ $inv->status }}"
                       data-number="{{ $inv->number }}"
                       data-client="{{ optional($inv->client)->name }}"
                       data-date="{{ optional($inv->date)->format('Y-m-d') }}">
                        <div class="flex items-center justify-between">
                            <div class="font-semibold text-gray-900">{{ $inv->number }}</div>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] {{ $bg }} {{ $tx }}">
                                {{ ucfirst($inv->status ?? 'draft') }}
                            </span>
                        </div>

                        <div class="mt-1 flex items-center gap-2 text-[13px]">
                            <div class="text-gray-700">{{ optional($inv->client)->name ?? '—' }}</div>
                            @if($clientCred > 0.0001)
                                <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 text-indigo-700 px-2 py-0.5 text-[10px]">
                                    Credit: {{ $fmtMoney($clientCred, $ccy) }}
                                </span>
                            @endif
                        </div>

                        <div class="mt-2 grid grid-cols-3 gap-2 text-[12px]">
                            <div>
                                <div class="text-gray-400">Total</div>
                                <div class="font-medium text-gray-900">{{ $fmtMoney($inv->total, $ccy) }}</div>
                            </div>
                            <div>
                                <div class="text-gray-400">Paid</div>
                                <div class="font-medium text-gray-800">{{ $fmtMoney($paidAmt, $ccy) }}</div>
                            </div>
                            <div>
                                <div class="text-gray-400">Balance</div>
                                <div class="font-medium {{ $balance>0?'text-amber-700':'text-emerald-700' }}">
                                    {{ $fmtMoney($balance, $ccy) }}
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

        </div>
    </div>
</div>

@push('styles')
<style>
    .no-scrollbar::-webkit-scrollbar { display:none; }
    .tab-btn {
        position: relative;
        padding: .5rem .9rem;
        font-size: .875rem;
        font-weight: 600;
        color: #6b7280;
        border-radius: .75rem;
        transition: all .15s;
        white-space: nowrap;
    }
    .tab-btn:hover { color: #111827; }
    .tab-btn.active { color: #111827; background: #F3F4F6; }
</style>
@endpush

@push('scripts')
<script>
    const q       = document.getElementById('q');
    const fromEl  = document.getElementById('from');
    const toEl    = document.getElementById('to');
    const rows    = Array.from(document.querySelectorAll('#tblBody .invoice-row'));
    const cards   = Array.from(document.querySelectorAll('#cardsBody .invoice-card'));
    const tabs    = Array.from(document.querySelectorAll('.tab-btn'));

    let state = { q:'', status:'', from:'', to:'' };

    function withinRange(dateStr){
        if(!state.from && !state.to) return true;
        if(!dateStr) return true;
        const d = dateStr;
        if(state.from && d < state.from) return false;
        if(state.to && d > state.to) return false;
        return true;
    }

    function matchRow(el){
        const s = (el.dataset.status || '').toLowerCase();
        const n = (el.dataset.number || '').toLowerCase();
        const c = (el.dataset.client || '').toLowerCase();
        const d = (el.dataset.date || '');

        const textOk   = !state.q || n.includes(state.q) || c.includes(state.q);
        const statusOk = !state.status || s === state.status;
        const dateOk   = withinRange(d);

        return textOk && statusOk && dateOk;
    }

    function applyFilters(){
        rows.forEach(r => r.style.display   = matchRow(r) ? '' : 'none');
        cards.forEach(c => c.style.display  = matchRow(c) ? '' : 'none');
    }

    tabs.forEach(btn=>{
        btn.addEventListener('click', ()=>{
            tabs.forEach(x=>x.classList.remove('active'));
            btn.classList.add('active');
            state.status = (btn.dataset.status || '').toLowerCase();
            applyFilters();
        });
    });

    q.addEventListener('input', ()=>{
        state.q = q.value.trim().toLowerCase();
        applyFilters();
    });

    fromEl.addEventListener('change', ()=>{
        state.from = fromEl.value || '';
        applyFilters();
    });

    toEl.addEventListener('change', ()=>{
        state.to = toEl.value || '';
        applyFilters();
    });

    document.getElementById('clearFilters').addEventListener('click', ()=>{
        q.value = ''; fromEl.value=''; toEl.value='';
        state = { q:'', status: state.status, from:'', to:'' };
        applyFilters();
    });

    applyFilters();
</script>
@endpush
@endsection