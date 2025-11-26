{{-- resources/views/depot-stock/portal/invoice_show.blade.php --}}
@extends('depot-stock::layouts.portal')

@section('title', 'Invoice '.$invoice->number)

@section('content')
@php
    $currency   = $currency ?? config('depot-stock.currency','USD');
    $total      = (float) ($invoice->total ?? 0);
    $paidTotal  = (float) ($invoice->paid_total ?? 0);
    $balance    = max(0, round($total - $paidTotal, 2));
    $exportName = 'invoice_'.($invoice->number ?? $invoice->id).'_offloads.csv';
@endphp

<div class="max-w-6xl mx-auto px-4 md:px-6 py-6 space-y-6 text-slate-100">

    {{-- Header / breadcrumb --}}
    <div class="flex items-center justify-between gap-3">
        <div>
            <div class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Client Portal · Invoice</div>
            <h1 class="mt-1 text-xl md:text-2xl font-semibold text-slate-50">
                Invoice <span class="text-amber-300">{{ $invoice->number }}</span>
            </h1>
            <p class="mt-1 text-xs text-slate-400">
                {{ $invoice->date ? \Illuminate\Support\Carbon::parse($invoice->date)->format('d M Y') : 'No date' }}
                · {{ strtoupper($invoice->status ?? 'draft') }}
            </p>
        </div>

        <a href="{{ route('portal.invoices') }}"
           class="inline-flex items-center gap-1 rounded-2xl border border-slate-700 bg-slate-900/80 px-3 py-1.5 text-xs font-medium text-slate-100 hover:border-sky-400 hover:bg-slate-900 transition">
            <span class="text-lg leading-none">←</span>
            <span>Back to invoices</span>
        </a>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="rounded-2xl bg-slate-900/80 border border-slate-800 px-3 py-3 shadow-sm">
            <div class="text-[10px] uppercase tracking-wide text-slate-400">Total</div>
            <div class="mt-1 text-lg font-semibold text-slate-50">
                {{ $currency }} {{ number_format($total, 2) }}
            </div>
        </div>

        <div class="rounded-2xl bg-slate-900/80 border border-slate-800 px-3 py-3 shadow-sm">
            <div class="text-[10px] uppercase tracking-wide text-slate-400">Paid</div>
            <div class="mt-1 text-lg font-semibold text-emerald-300">
                {{ $currency }} {{ number_format($paidTotal, 2) }}
            </div>
        </div>

        <div class="rounded-2xl bg-slate-900/80 border border-slate-800 px-3 py-3 shadow-sm">
            <div class="text-[10px] uppercase tracking-wide text-slate-400">Balance</div>
            <div class="mt-1 text-lg font-semibold {{ $balance>0 ? 'text-rose-300' : 'text-emerald-300' }}">
                {{ $currency }} {{ number_format($balance, 2) }}
            </div>
        </div>

        <div class="rounded-2xl bg-slate-900/80 border border-slate-800 px-3 py-3 shadow-sm">
            <div class="text-[10px] uppercase tracking-wide text-slate-400">Client</div>
            <div class="mt-1 text-sm font-semibold text-slate-50 truncate">
                {{ optional($invoice->client)->name ?? 'Client' }}
            </div>
        </div>
    </div>

    {{-- Line items --}}
    <div class="rounded-2xl bg-slate-900/80 border border-slate-800 overflow-hidden shadow-sm">
        <div class="px-4 py-3 border-b border-slate-800 bg-slate-900/90 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-100">Invoice items</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-900 border-b border-slate-800 text-xs uppercase text-slate-400">
                    <tr>
                        <th class="px-3 py-2 text-left">Date</th>
                        <th class="px-3 py-2 text-left">Description</th>
                        <th class="px-3 py-2 text-right">Litres</th>
                        <th class="px-3 py-2 text-right">Rate / L</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($invoice->items as $it)
                        <tr class="hover:bg-slate-800/60">
                            <td class="px-3 py-2 text-slate-200">
                                {{ $it->date ? \Illuminate\Support\Carbon::parse($it->date)->format('d M Y') : '—' }}
                            </td>
                            <td class="px-3 py-2 text-slate-100">{{ $it->description }}</td>
                            <td class="px-3 py-2 text-right text-slate-200">
                                {{ number_format((float)($it->litres ?? 0), 1) }}
                            </td>
                            <td class="px-3 py-2 text-right text-slate-200">
                                {{ $currency }} {{ number_format((float)($it->rate_per_litre ?? 0), 4) }}
                            </td>
                            <td class="px-3 py-2 text-right font-semibold text-slate-50">
                                {{ $currency }} {{ number_format((float)($it->amount ?? 0), 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-4 text-center text-slate-400">
                                No items on this invoice.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Offloads “tabulator” (collapsible table + export) --}}
    <div class="rounded-2xl bg-slate-900/80 border border-slate-800 overflow-hidden shadow-sm">
        <button type="button"
                id="offloadsToggle"
                class="w-full px-4 py-3 border-b border-slate-800 bg-gradient-to-r from-slate-900 via-slate-900 to-slate-950 flex items-center justify-between text-sm font-semibold text-slate-100">
            <span class="flex items-center gap-2">
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-400/20 text-amber-300 text-[11px] border border-amber-400/40">
                    Δ
                </span>
                Offloads billed on this invoice
            </span>
            <span class="flex items-center gap-1 text-[11px] text-slate-400">
                <span id="offloadsToggleLabel">See details</span>
                <svg id="offloadsToggleIcon" class="h-3 w-3 transition-transform" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </span>
        </button>

        <div id="offloadsPanel" class="hidden">
            <div class="px-4 pt-3 pb-2 flex items-center justify-between text-[12px] text-slate-300">
                <div>
                    Linked offloads:
                    <span class="font-semibold text-sky-300">{{ $offloads->count() }}</span>
                </div>

                <button id="btnExportOffloads"
                        data-filename="{{ $exportName }}"
                        class="inline-flex items-center gap-1 rounded-xl border border-slate-700 bg-slate-900 px-3 py-1.5 text-[11px] font-medium text-slate-100 hover:border-sky-400 hover:bg-slate-900/90">
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" stroke-width="1.6" stroke-linecap="round"/>
                        <path d="M7 11l5 5 5-5" stroke-width="1.6" stroke-linecap="round"/>
                        <path d="M12 4v12" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                    <span>Export CSV</span>
                </button>
            </div>

            <div class="overflow-x-auto pb-3">
                <table id="offloadsTable" class="min-w-full text-[13px]">
                    <thead class="bg-slate-900 border-y border-slate-800 text-xs uppercase text-slate-400">
                        <tr>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Depot</th>
                            <th class="px-3 py-2 text-left">Tank</th>
                            <th class="px-3 py-2 text-left">Product</th>
                            <th class="px-3 py-2 text-left">Truck</th>
                            <th class="px-3 py-2 text-left">Trailer</th>
                            <th class="px-3 py-2 text-right">Loaded (L)</th>
                            <th class="px-3 py-2 text-right">Delivered @20 (L)</th>
                            <th class="px-3 py-2 text-right">Shortfall (L)</th>
                            <th class="px-3 py-2 text-right">Allowance (L)</th>
                            <th class="px-3 py-2 text-right">Total Loss (L)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($offloads as $o)
                            @php
                                $delivered = (float)($o->delivered_20_l ?? 0);
                                $loadedDoc = (float)($o->loaded_observed_l ?? 0);
                                $short     = max($loadedDoc - $delivered, 0);
                                $allow     = (float)($o->depot_allowance_20_l ?? ($delivered * 0.003));
                                $totalLoss = $short + $allow;
                            @endphp
                            <tr class="hover:bg-slate-800/60">
                                <td class="px-3 py-2 text-slate-200">
                                    {{ $o->date ? \Illuminate\Support\Carbon::parse($o->date)->format('d M Y') : '—' }}
                                </td>
                                <td class="px-3 py-2 text-slate-200">
                                    {{ optional($o->tank->depot)->name ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-slate-200">
                                    {{ $o->tank_id ? 'T#'.$o->tank_id : '—' }}
                                </td>
                                <td class="px-3 py-2 text-slate-200">
                                    {{ optional($o->tank->product)->name ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-slate-200">
                                    {{ $o->truck_plate ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-slate-200">
                                    {{ $o->trailer_plate ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-right text-slate-200">
                                    {{ number_format($loadedDoc, 1) }}
                                </td>
                                <td class="px-3 py-2 text-right text-slate-200">
                                    {{ number_format($delivered, 1) }}
                                </td>
                                <td class="px-3 py-2 text-right text-rose-300">
                                    {{ number_format($short, 1) }}
                                </td>
                                <td class="px-3 py-2 text-right text-amber-300">
                                    {{ number_format($allow, 1) }}
                                </td>
                                <td class="px-3 py-2 text-right font-semibold text-slate-50">
                                    {{ number_format($totalLoss, 1) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-3 py-4 text-center text-slate-400">
                                    No offloads are linked to this invoice.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Payments --}}
    <div class="rounded-2xl bg-slate-900/80 border border-slate-800 overflow-hidden shadow-sm">
        <div class="px-4 py-3 border-b border-slate-800 bg-slate-900/90">
            <h2 class="text-sm font-semibold text-slate-100">Payments</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-900 border-b border-slate-800 text-xs uppercase text-slate-400">
                    <tr>
                        <th class="px-3 py-2 text-left">Date</th>
                        <th class="px-3 py-2 text-left">Method</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                        <th class="px-3 py-2 text-left">Reference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($invoice->payments as $p)
                        <tr class="hover:bg-slate-800/60">
                            <td class="px-3 py-2 text-slate-200">
                                {{ $p->date ? \Illuminate\Support\Carbon::parse($p->date)->format('d M Y') : '—' }}
                            </td>
                            <td class="px-3 py-2 text-slate-200">{{ $p->mode ?? $p->method ?? '—' }}</td>
                            <td class="px-3 py-2 text-right text-emerald-300">
                                {{ $currency }} {{ number_format((float)($p->amount ?? 0), 2) }}
                            </td>
                            <td class="px-3 py-2 text-slate-200">{{ $p->reference ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-4 text-center text-slate-400">
                                No payments recorded for this invoice.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('offloadsToggle');
    const panel     = document.getElementById('offloadsPanel');
    const label     = document.getElementById('offloadsToggleLabel');
    const icon      = document.getElementById('offloadsToggleIcon');

    if (toggleBtn && panel && label && icon) {
        toggleBtn.addEventListener('click', () => {
            const hidden = panel.classList.contains('hidden');
            panel.classList.toggle('hidden');
            label.textContent = hidden ? 'Hide details' : 'See details';
            icon.style.transform = hidden ? 'rotate(180deg)' : 'rotate(0deg)';
        });
    }

    // CSV export
    const exportBtn = document.getElementById('btnExportOffloads');
    if (exportBtn) {
        exportBtn.addEventListener('click', () => {
            const table = document.getElementById('offloadsTable');
            if (!table) return;

            const filename    = exportBtn.dataset.filename || 'offloads.csv';
            const headerCells = Array.from(table.querySelectorAll('thead th'));
            const bodyRows    = Array.from(table.querySelectorAll('tbody tr'));
            if (!bodyRows.length) return;

            const lines = [];
            lines.push(
                headerCells.map(th => `"${th.innerText.trim().replace(/"/g, '""')}"`).join(',')
            );

            bodyRows.forEach(tr => {
                const tds = Array.from(tr.querySelectorAll('td'));
                if (!tds.length) return;
                const cols = tds.map(td => `"${td.innerText.trim().replace(/"/g, '""')}"`);
                lines.push(cols.join(','));
            });

            const csv  = lines.join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url  = URL.createObjectURL(blob);

            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });
    }
});
</script>
@endpush
@endsection