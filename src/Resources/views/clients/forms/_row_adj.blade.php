@php
  $dateStr = (is_object($tr->date) && method_exists($tr->date,'format'))
      ? $tr->date->format('Y-m-d')
      : substr((string) $tr->date, 0, 10);

  $tankLabel = $tr->tank
      ? ($tr->tank->depot->name.' — '.$tr->tank->product->name.' (T#'.$tr->tank->id.')')
      : ('Tank #'.$tr->tank_id);

  // try common column names for adjustment amount @20
  $vol20 = (float) (
      $tr->volume_20_l
      ?? $tr->delivered_20_l
      ?? $tr->loaded_20_l
      ?? $tr->qty_20_l
      ?? $tr->qty
      ?? 0
  );

  $reason = $tr->reason ?? $tr->note ?? $tr->reference ?? '';
@endphp

<tr class="hover:bg-gray-50">
  <td class="px-3 py-2 whitespace-nowrap">{{ $dateStr }}</td>
  <td class="px-3 py-2 text-gray-700">{{ $tankLabel }}</td>
  <td class="px-3 py-2 text-right font-medium">{{ number_format($vol20, 3) }}</td>
  <td class="px-3 py-2">{{ $reason }}</td>
</tr>