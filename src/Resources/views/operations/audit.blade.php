{{-- resources/views/vendor/depot-stock/operations/audit.blade.php --}}
@extends('depot-stock::operations.layout')

@section('title', 'Depot Operations Audit')

@section('ops-content')
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">Audit Trail</h1>
            <p class="mt-0.5 text-xs text-gray-500">Comprehensive log of all depot operations: dips, adjustments, offloads, loads, locks, and more.</p>
        </div>
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="date" name="date" value="{{ request('date', now()->toDateString()) }}" class="rounded-lg border border-gray-200 px-2 py-1.5 text-xs text-gray-800" />
            <select name="user" class="rounded-lg border border-gray-200 px-2 py-1.5 text-xs text-gray-800">
                <option value="">All users</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @if(request('user') == $user->id) selected @endif>{{ $user->name }}</option>
                @endforeach
            </select>
            <select name="type" class="rounded-lg border border-gray-200 px-2 py-1.5 text-xs text-gray-800">
                <option value="">All types</option>
                <option value="dip" @if(request('type')=='dip') selected @endif>Dip</option>
                <option value="adjustment" @if(request('type')=='adjustment') selected @endif>Adjustment</option>
                <option value="offload" @if(request('type')=='offload') selected @endif>Offload</option>
                <option value="load" @if(request('type')=='load') selected @endif>Load</option>
                <option value="lock" @if(request('type')=='lock') selected @endif>Lock</option>
                <option value="variance" @if(request('type')=='variance') selected @endif>Variance</option>
            </select>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search details..." class="rounded-lg border border-gray-200 px-2 py-1.5 text-xs text-gray-800" />
            <button class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-black">Filter</button>
            <a href="#" id="exportAuditCsv" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700 ml-2">Export CSV</a>
        </form>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-100 bg-white shadow-sm">
        <table class="min-w-full text-xs text-left">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 font-semibold text-gray-600 cursor-pointer" onclick="sortAudit('date')">Date/Time</th>
                    <th class="px-4 py-2 font-semibold text-gray-600 cursor-pointer" onclick="sortAudit('user')">User</th>
                    <th class="px-4 py-2 font-semibold text-gray-600 cursor-pointer" onclick="sortAudit('depot')">Depot</th>
                    <th class="px-4 py-2 font-semibold text-gray-600 cursor-pointer" onclick="sortAudit('tank')">Tank</th>
                    <th class="px-4 py-2 font-semibold text-gray-600 cursor-pointer" onclick="sortAudit('type')">Operation</th>
                    <th class="px-4 py-2 font-semibold text-gray-600">Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($auditEntries as $entry)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-2 whitespace-nowrap">{{ $entry->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ $entry->user->name ?? '—' }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ $entry->depot->name ?? '—' }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ $entry->tank ? 'T'.$entry->tank->id : '—' }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 {{
                                [
                                    'dip' => 'bg-sky-50 text-sky-700',
                                    'adjustment' => 'bg-indigo-50 text-indigo-700',
                                    'offload' => 'bg-emerald-50 text-emerald-700',
                                    'load' => 'bg-amber-50 text-amber-700',
                                    'lock' => 'bg-gray-100 text-gray-700',
                                    'variance' => 'bg-rose-50 text-rose-700',
                                ][$entry->type] ?? 'bg-gray-50 text-gray-700'
                            }}">
                                {{ ucfirst($entry->type) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-gray-700">
                          {!! $entry->details !!}
                          @if(property_exists($entry, 'before_value') && $entry->before_value !== null || property_exists($entry, 'after_value') && $entry->after_value !== null)
                            <div class="mt-1 text-[11px] text-gray-500">
                              @if(property_exists($entry, 'before_value') && $entry->before_value !== null)
                                <span class="mr-2">Before: <b>{{ $entry->before_value }}</b></span>
                              @endif
                              @if(property_exists($entry, 'after_value') && $entry->after_value !== null)
                                <span>After: <b>{{ $entry->after_value }}</b></span>
                              @endif
                            </div>
                          @endif
                          @if(property_exists($entry, 'note') && $entry->note)
                            <div class="mt-1 text-[11px] text-amber-700">Note: {{ $entry->note }}</div>
                          @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-400">No audit entries found for the selected filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{-- Pagination --}}
        <div class="p-4 flex justify-center">
          {{ $auditEntries->links() }}
        </div>
    </div>
</div>
@push('scripts')
<script>
function sortAudit(field) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', field);
    url.searchParams.set('direction', url.searchParams.get('direction') === 'asc' ? 'desc' : 'asc');
    window.location = url.toString();
}

document.getElementById('exportAuditCsv')?.addEventListener('click', function(e) {
    e.preventDefault();
    const url = new URL(window.location.origin + '{{ route('audit.export', [], false) }}');
    // Copy current filters to export URL
    for (const [key, value] of new URL(window.location.href).searchParams.entries()) {
        url.searchParams.set(key, value);
    }
    window.open(url.toString(), '_blank');
});
</script>
@endpush
@endsection
