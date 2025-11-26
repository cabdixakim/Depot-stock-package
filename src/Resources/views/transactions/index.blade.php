@extends('depot-stock::layouts.app')
@section('title','Transactions')
@section('content')
<form method="POST" action="{{ route('depot.transactions.store') }}" class="grid grid-cols-4 gap-4 bg-white p-4 rounded shadow mb-6">
@csrf
  <div>
    <label class="text-xs">Type</label>
    <select name="type" class="w-full border rounded p-2">
      <option>IN</option><option>OUT</option><option>ADJ</option>
    </select>
  </div>
  <div><label class="text-xs">Depot ID</label><input name="depot_id" class="w-full border rounded p-2" required></div>
  <div><label class="text-xs">Tank ID</label><input name="tank_id" class="w-full border rounded p-2" required></div>
  <div><label class="text-xs">Product ID</label><input name="product_id" class="w-full border rounded p-2" required></div>
  <div><label class="text-xs">Client ID</label><input name="client_id" class="w-full border rounded p-2"></div>
  <div><label class="text-xs">Truck ID</label><input name="truck_id" class="w-full border rounded p-2"></div>
  <div><label class="text-xs">Observed Volume (L)</label><input name="observed_volume" type="number" step="0.001" class="w-full border rounded p-2" required></div>
  <div><label class="text-xs">Temperature (°C)</label><input name="temperature" type="number" step="0.01" class="w-full border rounded p-2" required></div>
  <div><label class="text-xs">Density</label><input name="density" type="number" step="0.001" class="w-full border rounded p-2" required></div>
  <div><label class="text-xs">Date</label><input name="date" type="date" class="w-full border rounded p-2" required></div>
  <div class="col-span-4"><label class="text-xs">Notes</label><textarea name="notes" class="w-full border rounded p-2"></textarea></div>
  <div class="col-span-4"><button class="px-4 py-2 bg-blue-600 text-white rounded">Save Transaction</button></div>
</form>

<table class="w-full bg-white shadow rounded">
<thead><tr class="text-left">
<th class="p-2">Date</th><th class="p-2">Type</th><th class="p-2">Tank</th><th class="p-2">Delivered@20</th><th class="p-2">Allowance@20</th>
</tr></thead>
<tbody>
@foreach($transactions as $t)
<tr class="border-t">
<td class="p-2">{{ $t->date }}</td>
<td class="p-2">{{ $t->type }}</td>
<td class="p-2">{{ $t->tank_id }}</td>
<td class="p-2">{{ number_format($t->delivered_20,3) }}</td>
<td class="p-2 text-green-700">{{ number_format($t->allowance_20,3) }}</td>
</tr>
@endforeach
</tbody>
</table>
@endsection
