@extends('depot-stock::layouts.app')

@section('title','Dip Detail')

@section('content')
@php
    use Illuminate\Support\Carbon;

    $date = $dip->date instanceof Carbon
        ? $dip->date
        : ($dip->date ? Carbon::parse($dip->date) : null);

    $book20   = (float)($dip->book_volume_20 ?? 0);
    $vol20    = (float)($dip->volume_20 ?? 0);
    $observed = (float)($dip->observed_volume ?? 0);
    $variance = $book20 ? $vol20 - $book20 : null;

    $tankLabel = trim(
        ($dip->tank?->depot?->name ?? '') .
        ' — ' .
        ($dip->tank?->product?->name ?? ''),
        ' —'
    );
@endphp

<div class="max-w-5xl mx-auto space-y-6">

  {{-- HEADER --}}
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-xl font-semibold text-gray-900 flex items-center gap-2">
        Dip detail
        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-medium text-gray-600">
          T{{ $dip->tank_id }}
        </span>
      </h1>
      <p class="mt-1 text-sm text-gray-500">
        Snapshot of this dip reading with book comparison & metadata.
      </p>
    </div>

    <div class="flex items-center gap-2">
      <a href="{{ route('depot.dips.index', request()->only(['tank','range','month'])) }}"
         class="inline-flex items-center gap-1 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/>
        </svg>
        Back to dips
      </a>
    </div>
  </div>

  {{-- TOP SUMMARY ROW --}}
  <div class="grid gap-4 lg:grid-cols-2">

    {{-- LEFT: main info + KPIs --}}
    <div class="space-y-5">

      {{-- Tank + date --}}
      <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Tank</p>
            <p class="mt-1 text-base font-semibold text-gray-900">
              {{ $tankLabel ?: 'Unknown tank' }}
              <span class="text-xs font-normal text-gray-500">(T{{ $dip->tank_id }})</span>
            </p>
          </div>

          <div class="flex flex-wrap gap-3">
            <div class="rounded-xl bg-gray-50 px-4 py-3 text-right">
              <p class="text-[11px] uppercase tracking-wide text-gray-500">Date</p>
              <p class="mt-1 text-sm font-semibold text-gray-900">
                {{ $date?->format('Y-m-d') ?? '—' }}
              </p>
            </div>
            <div class="rounded-xl bg-gray-50 px-4 py-3 text-right">
              <p class="text-[11px] uppercase tracking-wide text-gray-500">Dip height</p>
              <p class="mt-1 text-sm font-semibold text-gray-900">
                {{ number_format($dip->dip_height ?? 0, 2) }} cm
              </p>
            </div>
          </div>
        </div>
      </div>

      {{-- KPI Cards --}}
      <div class="grid gap-3 sm:grid-cols-3">
        {{-- Observed --}}
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Observed volume</p>
          <p class="mt-2 text-2xl font-semibold text-gray-900">
            {{ number_format($observed, 0) }} <span class="text-sm font-normal text-gray-400">L</span>
          </p>
        </div>

        {{-- @20C --}}
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Volume @20°</p>
          <p class="mt-2 text-2xl font-semibold text-gray-900">
            {{ number_format($vol20, 0) }} <span class="text-sm font-normal text-gray-400">L</span>
          </p>
        </div>

        {{-- Temp • Density --}}
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Temp • Density</p>
          <p class="mt-2 text-lg font-semibold text-gray-900">
            {{ $dip->temperature ? number_format($dip->temperature,1) : '—' }}°C
            <span class="text-gray-300 mx-1">•</span>
            {{ $dip->density !== null ? rtrim(rtrim(number_format($dip->density,4),'0'),'.') : '—' }}
          </p>
        </div>
      </div>
    </div>

    {{-- RIGHT: Variance box --}}
    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
      <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Variance vs book</p>

      <div class="mt-3 space-y-3 text-sm">
        <div class="flex justify-between">
          <span class="text-gray-500">Book @20°</span>
          <span class="font-semibold text-gray-800">
            {{ $book20 ? number_format($book20) .' L' : '—' }}
          </span>
        </div>

        <div class="flex justify-between">
          <span class="text-gray-500">This dip @20°</span>
          <span class="font-semibold text-gray-800">{{ number_format($vol20) }} L</span>
        </div>

        <div class="flex justify-between">
          <span class="text-gray-500">Variance</span>
          <span class="font-semibold
            @if($variance !== null)
              {{ $variance >= 0 ? 'text-emerald-600' : 'text-rose-600' }}
            @else
              text-gray-400
            @endif">
            @if($variance === null)
              —
            @else
              {{ $variance >= 0 ? '+' : '' }}{{ number_format($variance) }} L
            @endif
          </span>
        </div>
      </div>
    </div>
  </div>

  {{-- LOWER DETAILS --}}
  <div class="grid gap-4 lg:grid-cols-2">
    {{-- Summary --}}
    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
      <h2 class="text-sm font-semibold text-gray-900 mb-3">Reading summary</h2>

      <dl class="grid gap-4 sm:grid-cols-2 text-sm">
        <div>
          <dt class="text-[11px] uppercase tracking-wide text-gray-500">Strapping chart</dt>
          <dd class="mt-1 font-medium break-all text-gray-800">
            {{ $dip->strapping_chart_path ?: '—' }}
          </dd>
        </div>

        <div>
          <dt class="text-[11px] uppercase tracking-wide text-gray-500">Reference</dt>
          <dd class="mt-1 font-medium text-gray-800">
            {{ $dip->reference ?: '—' }}
          </dd>
        </div>

        <div class="sm:col-span-2">
          <dt class="text-[11px] uppercase tracking-wide text-gray-500">Note</dt>
          <dd class="mt-1 font-medium text-gray-800 whitespace-pre-line">
            {{ $dip->note ?: 'No note recorded.' }}
          </dd>
        </div>
      </dl>
    </div>

    {{-- Audit --}}
    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
      <h2 class="text-sm font-semibold text-gray-900 mb-3">Audit trail</h2>

      <dl class="space-y-3 text-sm">
        <div>
          <dt class="text-xs text-gray-500">Created by</dt>
          <dd class="mt-1 font-medium">
              @if($dip->createdBy)
                  {{ $dip->createdBy->name ?? $dip->createdBy->email ?? ('User #'.$dip->created_by_id) }}
              @elseif($dip->created_by_id)
                  User #{{ $dip->created_by_id }}
              @else
                  —
              @endif
          </dd>
      </div>

        <div>
          <dt class="text-[11px] uppercase tracking-wide text-gray-500">Updated at</dt>
          <dd class="mt-1 font-medium text-gray-800">
            {{ $dip->updated_at?->format('Y-m-d H:i') ?? '—' }}
          </dd>
        </div>
      </dl>
    </div>
  </div>
</div>
@endsection