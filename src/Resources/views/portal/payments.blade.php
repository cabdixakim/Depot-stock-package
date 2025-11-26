@extends('depot-stock::layouts.portal')

@section('title', 'Payments — ' . ($client->name ?? 'Client'))

@section('content')
@php
  $currency = $currency ?? config('depot-stock.currency','USD');
  $fmtM = fn($v)=>$currency.' '.number_format((float)$v,2,'.',',');
@endphp

<div class="min-h-[100dvh] bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-slate-100">
  <div class="mx-auto max-w-6xl px-4 py-6 md:py-8 space-y-6">

    {{-- HEADER --}}
    <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <div class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">
          Payments
        </div>
        <h1 class="mt-1 text-xl md:text-2xl font-semibold text-slate-50">
          {{ $client->name ?? 'Client' }}
        </h1>
        <p class="mt-1 text-xs text-slate-400 max-w-md">
          View payments recorded against your account. Values shown here are read-only.
        </p>
      </div>

      <div class="flex flex-col items-end gap-2 text-right text-xs text-slate-400">
        <div>Showing latest payments (page {{ $payments->currentPage() }} of {{ $payments->lastPage() }})</div>
        <div class="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-900/70 px-3 py-1">
          <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
          <span>
            Total on this page:
            <span class="font-semibold text-emerald-200">
              {{ $fmtM($payments->sum('amount')) }}
            </span>
          </span>
        </div>
      </div>
    </header>

    {{-- TABLE CARD --}}
    <section class="rounded-2xl border border-slate-800 bg-slate-900/80 shadow-md overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-800/80 flex items-center justify-between">
        <div>
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-300">Payment History</div>
          <p class="text-[11px] text-slate-400">
            Sorted by most recent first.
          </p>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-xs text-slate-100">
          <thead class="bg-slate-900/90 border-b border-slate-800/80 text-[11px] uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-4 py-2 text-left">Date</th>
              <th class="px-3 py-2 text-left">Reference</th>
              <th class="px-3 py-2 text-left">Method</th>
              <th class="px-3 py-2 text-right">Amount</th>
              <th class="px-3 py-2 text-right">Currency</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/80">
            @forelse($payments as $p)
              <tr class="hover:bg-slate-800/70">
                <td class="px-4 py-2 whitespace-nowrap text-slate-200">
                  {{ optional($p->date)->format('Y-m-d') ?? $p->date }}
                </td>
                <td class="px-3 py-2 whitespace-nowrap text-slate-200">
                  {{ $p->reference ?? ('PAY-'.$p->id) }}
                </td>
                <td class="px-3 py-2 whitespace-nowrap text-slate-300">
                  {{ $p->method ? strtoupper($p->method) : '—' }}
                </td>
                <td class="px-3 py-2 text-right text-emerald-200 font-semibold">
                  {{ $fmtM($p->amount ?? 0) }}
                </td>
                <td class="px-3 py-2 text-right text-slate-300">
                  {{ $p->currency ?? $currency }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="px-4 py-6 text-center text-slate-400">
                  No payments have been recorded on your account yet.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- PAGINATION --}}
      @if($payments->hasPages())
        <div class="px-4 py-3 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-400">
          <div>
            Showing
            <span class="font-semibold text-slate-200">{{ $payments->firstItem() }}</span>
            –
            <span class="font-semibold text-slate-200">{{ $payments->lastItem() }}</span>
            of
            <span class="font-semibold text-slate-200">{{ $payments->total() }}</span>
          </div>
          <div class="text-xs">
            {{ $payments->onEachSide(1)->links('depot-stock::components.portal-pagination') }}
          </div>
        </div>
      @endif
    </section>

  </div>
</div>
@endsection