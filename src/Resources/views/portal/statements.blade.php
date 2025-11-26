{{-- resources/views/depot-stock/portal/statements.blade.php --}}
@extends('depot-stock::layouts.portal')

@section('title', 'Statement — ' . ($client->name ?? 'Client'))

@section('content')
@php
  $currency = $currency ?? config('depot-stock.currency','USD');
  $fmtM = fn($v)=>$currency.' '.number_format((float)$v,2,'.',',');
  $fromVal = $from ?? '';
  $toVal   = $to   ?? '';
@endphp

<div class="min-h-[100dvh] bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-slate-100">
  <div class="mx-auto max-w-6xl px-4 py-6 md:py-8 space-y-6">

    {{-- TOP BAR: Title + Filters + Actions --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <div class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">
          Account Statement
        </div>
        <h1 class="mt-1 text-xl md:text-2xl font-semibold text-slate-50">
          {{ $client->name ?? 'Client' }}
        </h1>
        <p class="mt-1 text-xs text-slate-400">
          Period:
          <span class="font-medium text-slate-200">
            {{ $meta['from'] ?: 'Beginning' }} → {{ $meta['to'] ?: 'Today' }}
          </span>
        </p>
      </div>

      <div class="flex flex-col items-end gap-3">

        {{-- Filters --}}
        <form method="GET" class="flex flex-wrap items-center justify-end gap-2 text-xs">
          <div class="flex items-center gap-1">
            <span class="text-slate-400">From</span>
            <input type="date" name="from" value="{{ $fromVal }}"
                   class="rounded-lg border border-slate-700 bg-slate-900/70 px-2 py-1 text-xs text-slate-100 focus:border-sky-400 focus:ring-0">
          </div>
          <div class="flex items-center gap-1">
            <span class="text-slate-400">To</span>
            <input type="date" name="to" value="{{ $toVal }}"
                   class="rounded-lg border border-slate-700 bg-slate-900/70 px-2 py-1 text-xs text-slate-100 focus:border-sky-400 focus:ring-0">
          </div>
          <button class="rounded-lg bg-sky-600 px-3 py-1 text-xs font-medium text-white hover:bg-sky-500">
            Apply
          </button>
          <a href="{{ route('portal.statements') }}"
             class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-1 text-xs text-slate-200 hover:bg-slate-800/80">
            Reset
          </a>
        </form>

        {{-- Actions --}}
        <div class="flex items-center gap-2">
          <a href="{{ route('portal.statements.export', request()->only('from','to')) }}"
             class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-900/80 px-3 py-1.5 text-[11px] text-slate-200 hover:bg-slate-800">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path d="M4 20h16M12 4v11m0 0 4-4m-4 4-4-4" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Export
          </a>

          <a href="{{ route('portal.statements.print', request()->only('from','to')) }}"
             target="_blank"
             class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-[11px] font-medium text-white hover:bg-emerald-500">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6v-7z"
                    stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Print / PDF
          </a>
        </div>
      </div>
    </div>

    {{-- SUMMARY PILLS --}}
    <section class="grid grid-cols-1 sm:grid-cols-4 gap-3">
      <div class="rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-3">
        <div class="text-[11px] uppercase tracking-wide text-slate-400">Opening</div>
        <div class="mt-1 text-sm font-semibold text-slate-50">{{ $fmtM($meta['opening'] ?? 0) }}</div>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-3">
        <div class="text-[11px] uppercase tracking-wide text-slate-400">Charges</div>
        <div class="mt-1 text-sm font-semibold text-rose-200">{{ $fmtM($meta['charges'] ?? 0) }}</div>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-3">
        <div class="text-[11px] uppercase tracking-wide text-slate-400">Credits</div>
        <div class="mt-1 text-sm font-semibold text-emerald-200">{{ $fmtM($meta['credits'] ?? 0) }}</div>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-3">
        <div class="text-[11px] uppercase tracking-wide text-slate-400">Closing</div>
        <div class="mt-1 text-sm font-semibold text-sky-200">{{ $fmtM($meta['closing'] ?? 0) }}</div>
      </div>
    </section>

    {{-- TABLE --}}
    <section class="rounded-2xl border border-slate-800 bg-slate-900/80 shadow-md overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-800/80 flex items-center justify-between">
        <div>
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-300">Detailed Statement</div>
          <p class="text-[11px] text-slate-400">Chronological activity with running balance.</p>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-xs text-slate-100">
          <thead class="bg-slate-900/90 border-b border-slate-800/80 text-[11px] uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-4 py-2 text-left">Date</th>
              <th class="px-3 py-2 text-left">Type</th>
              <th class="px-3 py-2 text-left">Document</th>
              <th class="px-3 py-2 text-left">Description</th>
              <th class="px-3 py-2 text-right">Debit</th>
              <th class="px-3 py-2 text-right">Credit</th>
              <th class="px-4 py-2 text-right">Balance</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/80">
            @forelse($rows as $r)
              <tr class="hover:bg-slate-800/70">
                <td class="px-4 py-2 whitespace-nowrap text-slate-200">
                  {{ $r['date'] ?? '' }}
                </td>
                <td class="px-3 py-2 whitespace-nowrap text-slate-200">
                  {{ $r['type'] ?? '' }}
                </td>
                <td class="px-3 py-2 whitespace-nowrap text-slate-200">
                  {{ $r['doc_no'] ?? '' }}
                </td>
                <td class="px-3 py-2 text-slate-300">
                  {{ $r['description'] ?? '' }}
                </td>
                <td class="px-3 py-2 text-right text-rose-200">
                  {{ $fmtM($r['debit'] ?? 0) }}
                </td>
                <td class="px-3 py-2 text-right text-emerald-200">
                  {{ $fmtM($r['credit'] ?? 0) }}
                </td>
                <td class="px-4 py-2 text-right text-sky-200 font-semibold">
                  {{ $fmtM($r['balance'] ?? 0) }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="px-4 py-6 text-center text-slate-400">
                  No activity in this period.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

  </div>
</div>
@endsection