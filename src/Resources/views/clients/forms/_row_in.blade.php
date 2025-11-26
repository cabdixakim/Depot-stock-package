@php
  // local helpers
  $fmt = function ($v) {
      if ($v === null || $v === '') return '—';
      $s = number_format((float)$v, 3, '.', ',');
      return rtrim(rtrim($s, '0'), '.');
  };
  $dt = is_string($tr->date ?? null)
        ? substr($tr->date, 0, 10)
        : optional($tr->date)->format('Y-m-d');

  $depot  = trim((string) optional($tr->tank?->depot)->name);
  $prod   = trim((string) optional($tr->tank?->product)->name);
  $tankId = $tr->tank->id ?? null;
@endphp

<tr class="group border-b last:border-b-0">
  {{-- Date --}}
  <td class="px-3 py-2 align-middle">
    <div class="text-[11px] text-gray-500">{{ $dt ?: '—' }}</div>
  </td>

  {{-- Tank / Product --}}
  <td class="px-3 py-2 align-middle">
    <div class="text-[10px] text-gray-800 leading-5">
      {{ $prod ?: '—' }}
      <span class="text-gray-400">•</span>
      <span class="text-[10px] text-gray-600">{{ $depot ?: '—' }}</span>
    </div>
    <div class="text-[11px] text-gray-400">T#{{ $tankId ?? '—' }}</div>
  </td>

  {{-- Observed --}}
  <td class="px-3 py-2 text-right align-middle">
    <span class="tabular-nums text-[13px] text-gray-700">{{ $fmt($tr->delivered_observed_l ?? $tr->observed_l ?? null) }}</span>
  </td>

  {{-- Delivered @20 (highlight IN = green) --}}
  <td class="px-3 py-2 text-right align-middle">
    <span class="tabular-nums text-[13px] font-semibold text-emerald-700">
      {{ $fmt($tr->delivered_20_l ?? $tr->delivered_20 ?? null) }}
    </span>
  </td>

  {{-- Short @20 --}}
  <td class="px-3 py-2 text-right align-middle">
    <span class="tabular-nums text-[13px] text-gray-700">
      {{ $fmt($tr->shortfall_20_l ?? $tr->short_20 ?? null) }}
    </span>
  </td>

  {{-- Allow. @20 --}}
  <!-- <td class="px-3 py-2 text-right align-middle">
    <span class="tabular-nums text-[13px] text-gray-700">
      {{ $fmt($tr->depot_allowance_20_l ?? $tr->allowance_20 ?? null) }}
    </span>
  </td> -->

  {{-- Truck / Trailer / Ref --}}
  <td class="px-3 py-2 align-middle">
    <div class="text-[12px] text-gray-700">{{ $tr->truck_plate ?? '—' }}</div>
    <div class="text-[11px] text-gray-400">{{ $tr->trailer_plate ?? '—' }}</div>
  </td>
  <!-- <td class="px-3 py-2 align-middle">
    <div class="text-[12px] text-gray-500">{{ $tr->reference ?? '—' }}</div>
  </td> -->
</tr>