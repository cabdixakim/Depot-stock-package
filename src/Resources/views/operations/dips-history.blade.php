{{-- resources/views/vendor/depot-stock/operations/dips-history.blade.php --}}
@extends('depot-stock::operations.layout')

@section('ops-content')
@php
    $totalRows = $rows->count();
@endphp

<div class="space-y-6">
    {{-- HEADER + FILTERS --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">
                Dip history
            </h1>
            <p class="mt-1 text-xs text-gray-500 max-w-md">
                Each row is a tank-day: opening, closing, movements, variance and who recorded it.
            </p>

            <div class="mt-3 inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-[11px] text-gray-600">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 mr-2"></span>
                Tank-day records
                <span class="ml-1 font-semibold text-gray-900">{{ $totalRows }}</span>
                <span class="ml-1 text-gray-400 text-[10px]">
                    showing for selected filters
                </span>
            </div>
        </div>

        {{-- FILTER PANEL --}}
        <form
            method="GET"
            action="{{ route('depot.operations.dips-history') }}"
            class="w-full max-w-md rounded-2xl border border-gray-100 bg-white/90 shadow-sm px-4 py-3 space-y-3 text-xs"
        >
            <div class="flex items-center justify-between gap-2">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        Filters
                    </p>
                    <p class="text-[11px] text-gray-400">
                        Refine by period, depot &amp; tank.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-full bg-gray-900 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-black active:scale-[0.97]">
                        Apply
                    </button>
                    <button
                        type="button"
                        id="dipsHistoryReset"
                        class="inline-flex items-center rounded-full border border-gray-200 px-3 py-1.5 text-[11px] font-medium text-gray-600 hover:bg-gray-50">
                        Reset
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                {{-- From --}}
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        From
                    </label>
                    <input
                        type="date"
                        name="from"
                        value="{{ $dateFrom }}"
                        class="mt-1 w-full rounded-lg border border-gray-200 bg-white/90 px-2 py-1.5 text-xs text-gray-800 focus:ring-2 focus:ring-indigo-500/60 focus:border-indigo-500"
                    >
                </div>

                {{-- To --}}
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        To
                    </label>
                    <input
                        type="date"
                        name="to"
                        value="{{ $dateTo }}"
                        class="mt-1 w-full rounded-lg border border-gray-200 bg-white/90 px-2 py-1.5 text-xs text-gray-800 focus:ring-2 focus:ring-indigo-500/60 focus:border-indigo-500"
                    >
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                {{-- Depot --}}
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        Depot
                    </label>
                    <div class="mt-1 relative">
                        <select
                            name="depot"
                            class="w-full appearance-none rounded-lg border border-gray-200 bg-white/90 px-2 pr-6 py-1.5 text-xs text-gray-800 focus:ring-2 focus:ring-indigo-500/60 focus:border-indigo-500"
                        >
                            <option value="all" {{ $selectedDepotId === 'all' ? 'selected' : '' }}>
                                All depots
                            </option>
                            @foreach($depots as $depot)
                                <option value="{{ $depot->id }}"
                                    {{ (string)$selectedDepotId === (string)$depot->id ? 'selected' : '' }}>
                                    {{ $depot->name }}
                                </option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-1.5 flex items-center text-gray-400">
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M5.25 7.5L10 12.25 14.75 7.5H5.25z"/>
                            </svg>
                        </span>
                    </div>
                </div>

                {{-- Tank --}}
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        Tank
                    </label>
                    <div class="mt-1 relative">
                        <select
                            name="tank"
                            class="w-full appearance-none rounded-lg border border-gray-200 bg-white/90 px-2 pr-6 py-1.5 text-xs text-gray-800 focus:ring-2 focus:ring-indigo-500/60 focus:border-indigo-500"
                        >
                            <option value="all" {{ $selectedTankId === 'all' ? 'selected' : '' }}>
                                All tanks
                            </option>
                            @foreach($tanks as $tank)
                                <option value="{{ $tank->id }}"
                                    {{ (string)$selectedTankId === (string)$tank->id ? 'selected' : '' }}>
                                    {{ $tank->depot->name }} — {{ $tank->product->name }} (T{{ $tank->id }})
                                </option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-1.5 flex items-center text-gray-400">
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M5.25 7.5L10 12.25 14.75 7.5H5.25z"/>
                            </svg>
                        </span>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- EXPORT + TABLE WRAPPER --}}
<div class="mx-auto max-w-5xl rounded-2xl border border-gray-100 bg-white/95 shadow-sm">        <div class="flex flex-col gap-3 border-b border-gray-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-900 text-[11px] font-semibold text-white">
                    DH
                </span>
                <div>
                    <p class="font-semibold text-gray-800">
                        Tank-day grid
                    </p>
                    <p class="text-[11px] text-gray-400">
                        Sort, filter and export full dip history.
                    </p>
                </div>
            </div>

            {{-- Export buttons --}}
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <button
                    type="button"
                    id="btnDipsCopy"
                    class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M6 3a2 2 0 012-2h7a2 2 0 012 2v11a2 2 0 01-2 2h-7a2 2 0 01-2-2V3z"/>
                        <path d="M3 5a2 2 0 012-2v12a2 2 0 002 2H5a2 2 0 01-2-2V5z"/>
                    </svg>
                    Copy
                </button>

                <button
                    type="button"
                    id="btnDipsCsv"
                    class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50">
                    <span class="text-[11px]">CSV</span>
                </button>

                <button
                    type="button"
                    id="btnDipsXlsx"
                    class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50">
                    <span class="text-[11px]">Excel</span>
                </button>

                <button
                    type="button"
                    id="btnDipsPdf"
                    class="inline-flex items-center gap-1 rounded-full bg-gray-900 px-3 py-1.5 font-medium text-white hover:bg-black shadow-sm">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M4 2a2 2 0 00-2 2v12a2 2 0 002 2h8l6-6V4a2 2 0 00-2-2H4z"/>
                        <path d="M12 18v-4a2 2 0 012-2h4"/>
                    </svg>
                    PDF
                </button>
            </div>
        </div>

        <div class="px-2 pb-3 pt-2">
            <div id="dipsHistoryTable" class="h-[calc(100vh-230px)]"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ---- Reset filter button ----
    const resetBtn = document.getElementById('dipsHistoryReset');
    const form     = resetBtn ? resetBtn.closest('form') : null;

    resetBtn?.addEventListener('click', function () {
        if (!form) return;
        const from  = form.querySelector('input[name="from"]');
        const to    = form.querySelector('input[name="to"]');
        const depot = form.querySelector('select[name="depot"]');
        const tank  = form.querySelector('select[name="tank"]');

        if (from)  from.value  = '';
        if (to)    to.value    = '';
        if (depot) depot.value = 'all';
        if (tank)  tank.value  = 'all';

        form.submit();
    });

    // ---- Tabulator grid ----
    if (!window.Tabulator) {
        console.error('Tabulator is not loaded.');
        return;
    }

    const tableData = @json($rows->values());

    function formatLitres(value) {
        if (value === null || value === undefined || isNaN(value)) return '—';
        return Number(value).toLocaleString(undefined, { maximumFractionDigits: 0 }) + ' L';
    }

    function formatLitresSmall(value) {
        if (value === null || value === undefined || isNaN(value)) return '—';
        return Number(value).toLocaleString(undefined, { maximumFractionDigits: 0 });
    }

    const table = new Tabulator("#dipsHistoryTable", {
        data: tableData,
        layout: "fitDataStretch",
        height: "100%",
        reactiveData: false,
        placeholder: "No records found for this filter.",
        columnHeaderVertAlign: "bottom",
        tooltips: true,
        resizableColumns: true,
        printAsHtml: true,
        printStyled: true,
        columns: [
            { title: "Date", field: "date", sorter: "date", width: 110, headerHozAlign: "left" },
            { title: "Depot", field: "depot", width: 130 },
            { title: "Tank", field: "tank_label", width: 80 },
            { title: "Product", field: "product", width: 110 },

            { title: "Opening @20°", field: "opening_l_20", hozAlign: "right",
              formatter: cell => formatLitres(cell.getValue()), width: 120 },

            { title: "Offloads (L)", field: "offloads_l", hozAlign: "right",
              formatter: cell => formatLitresSmall(cell.getValue()), width: 110 },

            { title: "Loads (L)", field: "loads_l", hozAlign: "right",
              formatter: cell => formatLitresSmall(cell.getValue()), width: 110 },

            { title: "Net (L)", field: "net_l", hozAlign: "right",
              formatter: function (cell) {
                  const v = cell.getValue();
                  if (v === null || v === undefined || isNaN(v)) return '—';
                  const sign = v >= 0 ? '+' : '';
                  const cls  = v >= 0 ? 'text-emerald-600' : 'text-rose-600';
                  return `<span class="${cls}">${sign}${Number(v).toLocaleString()}</span>`;
              }, width: 110 },

            { title: "Expected closing @20°", field: "closing_expected_l_20", hozAlign: "right",
              formatter: cell => formatLitres(cell.getValue()), width: 140 },

            { title: "Actual closing @20°", field: "closing_actual_l_20", hozAlign: "right",
              formatter: cell => formatLitres(cell.getValue()), width: 140 },

            { title: "Variance (L)", field: "variance_l_20", hozAlign: "right",
              formatter: function (cell) {
                  const v = cell.getValue();
                  if (v === null || v === undefined || isNaN(v)) return '—';
                  const sign = v >= 0 ? '+' : '';
                  const cls  = v >= 0 ? 'text-emerald-600' : 'text-rose-600';
                  return `<span class="${cls}">${sign}${Number(v).toLocaleString()}</span>`;
              }, width: 120 },

            { title: "Variance %", field: "variance_pct", hozAlign: "right",
              formatter: function (cell) {
                  const v = cell.getValue();
                  if (v === null || v === undefined || isNaN(v)) return '—';
                  const sign = v >= 0 ? '+' : '';
                  const cls  = v >= 0 ? 'text-emerald-600' : 'text-rose-600';
                  return `<span class="${cls}">${sign}${Number(v).toFixed(2)}%</span>`;
              }, width: 110 },

            { title: "Status", field: "status_label", width: 110,
              formatter: function (cell) {
                  const v = (cell.getValue() || '').toString().toLowerCase();
                  let label = v || 'n/a';
                  let cls   = 'bg-gray-100 text-gray-700';

                  if (v === 'locked') {
                      cls = 'bg-emerald-50 text-emerald-700 border border-emerald-100';
                  } else if (v === 'open') {
                      cls = 'bg-indigo-50 text-indigo-700 border border-indigo-100';
                  }

                  return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] ${cls}">
                              ${label}
                          </span>`;
              }
            },

            { title: "Recorded by", field: "recorded_by", width: 140 },
            { title: "Locked by", field: "locked_by", width: 140 },
        ],
    });

    // ---- Export buttons ----
    document.getElementById('btnDipsCopy')?.addEventListener('click', function () {
        table.copyToClipboard();
        if (window.toast) toast('Copied to clipboard');
    });

    document.getElementById('btnDipsCsv')?.addEventListener('click', function () {
        table.download("csv", "dip-history.csv");
    });

    document.getElementById('btnDipsXlsx')?.addEventListener('click', function () {
        table.download("xlsx", "dip-history.xlsx", { sheetName: "Dip history" });
    });

    document.getElementById('btnDipsPdf')?.addEventListener('click', function () {
        table.download("pdf", "dip-history.pdf", {
            orientation: "landscape",
            title: "Dip history"
        });
    });
});
</script>
@endpush