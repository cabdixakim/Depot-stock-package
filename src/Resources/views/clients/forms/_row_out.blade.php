@php
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
    <div class="text-sm text-gray-800 leading-5">
      {{ $prod ?: '—' }}
      <span class="text-gray-400">•</span>
      <span class="text-gray-600">{{ $depot ?: '—' }}</span>
    </div>
    <div class="text-[11px] text-gray-400">T#{{ $tankId ?? '—' }}</div>
  </td>

  {{-- Loaded @20 (highlight OUT = red) --}}
  <td class="px-3 py-2 text-right align-middle">
    <span class="tabular-nums text-[13px] font-semibold text-rose-700">
      {{ $fmt($tr->loaded_20_l ?? $tr->loaded_20 ?? null) }}
    </span>
  </td>

  {{-- Temp --}}
  <td class="px-3 py-2 text-right align-middle">
    <span class="tabular-nums text-[12px] text-gray-700">{{ $fmt($tr->temperature_c ?? null) }}</span>
  </td>

  {{-- Density --}}
  <td class="px-3 py-2 text-right align-middle">
    <span class="tabular-nums text-[12px] text-gray-700">{{ $fmt($tr->density_kg_l ?? null) }}</span>
  </td>

  <!-- {{-- Truck / Trailer / Ref --}}
  <td class="px-3 py-2 align-middle">
    <div class="text-[12px] text-gray-700">{{ $tr->truck_plate ?? '—' }}</div>
    <div class="text-[11px] text-gray-400">{{ $tr->trailer_plate ?? '—' }}</div>
  </td> -->
  <!-- <td class="px-3 py-2 align-middle">
    <div class="text-[12px] text-gray-500">{{ $tr->reference ?? '—' }}</div>
  </td> -->
</tr>