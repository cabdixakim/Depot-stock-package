@extends('depot-stock::layouts.portal')

@section('title', 'Invoices — ' . ($client->name ?? 'Client'))

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
          Invoices
        </div>
        <h1 class="mt-1 text-xl md:text-2xl font-semibold text-slate-50">
          {{ $client->name ?? 'Client' }}
        </h1>
        <p class="mt-1 text-xs text-slate-400 max-w-md">
          Read-only list of invoices issued to your account. Click an invoice to see its offloads.
        </p>
      </div>

      <div class="flex flex-col items-end gap-2 text-right text-xs text-slate-400">
        <div>Showing latest invoices (page {{ $invoices->currentPage() }} of {{ $invoices->lastPage() }})</div>
        <div class="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-900/70 px-3 py-1">
          <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
          <span>
            Outstanding on this page:
            <span class="font-semibold text-amber-200">
              {{ $fmtM(
                $invoices->where('status','!=','paid')->sum('total')
              ) }}
            </span>
          </span>
        </div>
      </div>
    </header>

    {{-- TABLE CARD --}}
    <section class="rounded-2xl border border-slate-800 bg-slate-900/80 shadow-md overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-800/80 flex items-center justify-between">
        <div>
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-300">Invoice List</div>
          <p class="text-[11px] text-slate-400">
            Click an invoice to see its detailed breakdown and offloads.
          </p>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-xs text-slate-100">
          <thead class="bg-slate-900/90 border-b border-slate-800/80 text-[11px] uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-4 py-2 text-left">Date</th>
              <th class="px-3 py-2 text-left">Invoice #</th>
              <th class="px-3 py-2 text-left">Status</th>
              <th class="px-3 py-2 text-right">Total</th>
              <th class="px-3 py-2 text-right">Currency</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/80">
            @forelse($invoices as $inv)
              <tr class="hover:bg-slate-800/70">
                <td class="px-4 py-2 whitespace-nowrap text-slate-200">
                  {{ optional($inv->date)->format('Y-m-d') ?? $inv->date }}
                </td>
                <td class="px-3 py-2 whitespace-nowrap text-slate-200">
                  <a href="{{ route('portal.invoices.show', $inv) }}"
                     class="inline-flex items-center gap-1 hover:text-sky-300">
                    <span>{{ $inv->number ?? ('INV-'.$inv->id) }}</span>
                    <svg class="h-3 w-3 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path d="M9 18l6-6-6-6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                  </a>
                </td>
                <td class="px-3 py-2 whitespace-nowrap">
                  @php
                    $status = strtolower($inv->status ?? 'unpaid');
                    $badgeClasses = match ($status) {
                      'paid' => 'bg-emerald-900/60 text-emerald-200 border-emerald-700/70',
                      'partial','partially_paid' => 'bg-amber-900/50 text-amber-200 border-amber-700/70',
                      default => 'bg-rose-900/50 text-rose-200 border-rose-700/70',
                    };
                  @endphp
                  <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium {{ $badgeClasses }}">
                    {{ strtoupper($status) }}
                  </span>
                </td>
                <td class="px-3 py-2 text-right text-slate-100 font-semibold">
                  {{ $fmtM($inv->total ?? 0) }}
                </td>
                <td class="px-3 py-2 text-right text-slate-300">
                  {{ $inv->currency ?? $currency }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="px-4 py-6 text-center text-slate-400">
                  No invoices have been issued on your account yet.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- PAGINATION --}}
      @if($invoices->hasPages())
        <div class="px-4 py-3 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-400">
          <div>
            Showing
            <span class="font-semibold text-slate-200">{{ $invoices->firstItem() }}</span>
            –
            <span class="font-semibold text-slate-200">{{ $invoices->lastItem() }}</span>
            of
            <span class="font-semibold text-slate-200">{{ $invoices->total() }}</span>
          </div>
          <div class="text-xs">
            {{ $invoices->onEachSide(1)->links() }}
          </div>
        </div>
      @endif
    </section>

  </div>
</div>
@endsection