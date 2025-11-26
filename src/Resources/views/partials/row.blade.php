<tr>
  <td class="px-4 py-2">{{ $dip->date->format('Y-m-d') }}</td>
  <td class="px-4 py-2">
      {{ $dip->tank->depot->name }} — {{ $dip->tank->product->name }} (T{{ $dip->tank_id }})
  </td>
  <td class="px-4 py-2">{{ number_format($dip->dip_height, 2) }}</td>
  <td class="px-4 py-2 text-right">{{ number_format($dip->observed_volume, 0) }}</td>
  <td class="px-4 py-2 text-right">{{ number_format($dip->volume_20, 0) }}</td>
  <td class="px-4 py-2 text-right">
      <span class="font-medium text-gray-700">
        {{-- you can compute variance client-side if you have book --}}
        —
      </span>
  </td>
  <td class="px-4 py-2">{{ \Illuminate\Support\Str::limit($dip->note, 48) }}</td>
  <td class="px-4 py-2 text-right">
      <a href="{{ route('depot.dips.show',$dip) }}" class="text-indigo-600 hover:underline">View</a>
  </td>
</tr>
