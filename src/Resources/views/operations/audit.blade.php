{{-- resources/views/vendor/depot-stock/operations/audit.blade.php --}}
@extends('depot-stock::operations.layout')

@section('ops-content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">Audit: Flagged Variances</h1>
            <p class="mt-0.5 text-xs text-gray-500">Review tanks/days with opening or closing variances.</p>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white/95 p-4 shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs md:text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">Date</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">Depot</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">Tank</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">Opening</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">Expected Opening</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">Opening Variance</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">Closing</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">Expected Closing</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">Closing Variance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($flaggedDays as $day)
                        <tr class="border-b border-gray-100 hover:bg-emerald-50/30">
                            <td class="px-3 py-2">{{ $day->date->toDateString() }}</td>
                            <td class="px-3 py-2">{{ $day->tank->depot->name }}</td>
                            <td class="px-3 py-2">T{{ $day->tank->id }} ({{ $day->tank->product->name }})</td>
                            <td class="px-3 py-2">{{ number_format($day->opening_l_20, 0) }} L</td>
                            <td class="px-3 py-2">{{ number_format($day->expected_opening_l_20 ?? 0, 0) }} L</td>
                            <td class="px-3 py-2">
                                <span class="{{ $day->opening_variance_l_20 > 0 ? 'text-emerald-600' : ($day->opening_variance_l_20 < 0 ? 'text-rose-600' : 'text-gray-900') }}">
                                    {{ $day->opening_variance_l_20 > 0 ? '+' : '' }}{{ number_format($day->opening_variance_l_20, 0) }} L
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ number_format($day->closing_actual_l_20, 0) }} L</td>
                            <td class="px-3 py-2">{{ number_format($day->closing_expected_l_20, 0) }} L</td>
                            <td class="px-3 py-2">
                                <span class="{{ $day->variance_l_20 > 0 ? 'text-emerald-600' : ($day->variance_l_20 < 0 ? 'text-rose-600' : 'text-gray-900') }}">
                                    {{ $day->variance_l_20 > 0 ? '+' : '' }}{{ number_format($day->variance_l_20, 0) }} L
                                    ({{ number_format($day->variance_pct, 2) }}%)
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($flaggedDays->isEmpty())
            <div class="mt-6 text-center text-xs text-gray-400">No flagged variances found for the selected period.</div>
        @endif
    </div>
</div>
@endsection
