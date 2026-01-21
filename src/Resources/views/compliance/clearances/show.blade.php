@extends('depot-stock::layouts.app')

@section('title', 'Compliance · Clearance #' . $clearance->id)

@section('content')
@php
    $status = (string) ($clearance->status ?? 'draft');

    $statusMeta = [
        'draft' => [
            'label' => 'Draft',
            'cls'   => 'border-gray-200 bg-gray-50 text-gray-900',
            'dot'   => 'bg-gray-400',
        ],
        'submitted' => [
            'label' => 'Submitted',
            'cls'   => 'border-amber-200 bg-amber-50 text-amber-900',
            'dot'   => 'bg-amber-500',
        ],
        'tr8_issued' => [
            'label' => 'TR8 Issued',
            'cls'   => 'border-blue-200 bg-blue-50 text-blue-900',
            'dot'   => 'bg-blue-500',
        ],
        'arrived' => [
            'label' => 'Arrived',
            'cls'   => 'border-emerald-200 bg-emerald-50 text-emerald-900',
            'dot'   => 'bg-emerald-500',
        ],
        'cancelled' => [
            'label' => 'Cancelled',
            'cls'   => 'border-rose-200 bg-rose-50 text-rose-900',
            'dot'   => 'bg-rose-500',
        ],
    ];

    $m = $statusMeta[$status] ?? $statusMeta['draft'];

    $canAct = auth()->user()?->hasRole('admin|compliance|owner');
    $docsTr8 = collect($clearance->documents ?? [])->where('type', 'tr8')->values();
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    {{-- Top bar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <div class="flex items-center gap-3">
                <a href="{{ route('depot.compliance.clearances.index') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Back
                </a>

                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[12px] font-semibold {{ $m['cls'] }}">
                    <span class="h-2 w-2 rounded-full {{ $m['dot'] }}"></span>
                    {{ $m['label'] }}
                </span>
            </div>

            <div class="mt-3">
                <div class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Compliance Clearance</div>
                <div class="mt-0.5 flex flex-wrap items-end gap-x-3 gap-y-1">
                    <h1 class="text-xl sm:text-2xl font-semibold text-gray-900">
                        Clearance #{{ $clearance->id }}
                    </h1>
                    <div class="text-sm text-gray-600">
                        {{ $clearance->client?->name ?? '—' }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick actions --}}
        @if($canAct)
        <div class="flex flex-wrap items-center gap-2">
            @if($status === 'draft')
                <form method="POST" action="{{ route('depot.compliance.clearances.submit', $clearance) }}">
                    @csrf
                    <button class="h-10 inline-flex items-center justify-center rounded-xl bg-gray-900 px-4 text-sm font-semibold text-white hover:bg-gray-800">
                        Submit
                    </button>
                </form>
            @endif

            @if($status === 'submitted')
                <button type="button"
                        onclick="window.dispatchEvent(new CustomEvent('open-issue-tr8', { detail: { id: {{ $clearance->id }} } }))"
                        class="h-10 inline-flex items-center justify-center rounded-xl border border-amber-200 bg-amber-50 px-4 text-sm font-semibold text-amber-900 hover:bg-amber-100">
                    Issue TR8
                </button>

                <form method="POST" action="{{ route('depot.compliance.clearances.cancel', $clearance) }}">
                    @csrf
                    <button class="h-10 inline-flex items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-4 text-sm font-semibold text-rose-900 hover:bg-rose-100">
                        Cancel
                    </button>
                </form>
            @endif

            @if($status === 'tr8_issued')
                <form method="POST" action="{{ route('depot.compliance.clearances.arrive', $clearance) }}">
                    @csrf
                    <button class="h-10 inline-flex items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-900 hover:bg-emerald-100">
                        Mark Arrived
                    </button>
                </form>

                <form method="POST" action="{{ route('depot.compliance.clearances.cancel', $clearance) }}">
                    @csrf
                    <button class="h-10 inline-flex items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-4 text-sm font-semibold text-rose-900 hover:bg-rose-100">
                        Cancel
                    </button>
                </form>
            @endif

            @if($status === 'arrived')
                <form method="POST" action="{{ route('depot.compliance.clearances.cancel', $clearance) }}">
                    @csrf
                    <button class="h-10 inline-flex items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-4 text-sm font-semibold text-rose-900 hover:bg-rose-100">
                        Cancel
                    </button>
                </form>
            @endif
        </div>
        @endif
    </div>

    {{-- Main grid --}}
    <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Left: details --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Summary card --}}
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Key details</div>
                            <div class="mt-1 text-xs text-gray-500">Everything you need at a glance.</div>
                        </div>
                        <div class="text-xs text-gray-500">
                            Updated {{ optional($clearance->updated_at)->diffForHumans() ?? '—' }}
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <div class="text-xs font-semibold text-gray-600 uppercase">Truck</div>
                            <div class="mt-1 text-lg font-semibold text-gray-900">{{ $clearance->truck_number ?? '—' }}</div>
                            <div class="mt-1 text-xs text-gray-500">Trailer: {{ $clearance->trailer_number ?? '—' }}</div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <div class="text-xs font-semibold text-gray-600 uppercase">Loaded @20°C</div>
                            <div class="mt-1 text-lg font-semibold text-gray-900">
                                {{ is_null($clearance->loaded_20_l) ? '—' : number_format($clearance->loaded_20_l, 0) . ' L' }}
                            </div>
                            <div class="mt-1 text-xs text-gray-500">
                                Bonded: <span class="font-semibold text-gray-800">{{ $clearance->is_bonded ? 'Yes' : 'No' }}</span>
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white p-4">
                            <div class="text-xs font-semibold text-gray-600 uppercase">Border point</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900">{{ $clearance->border_point ?? '—' }}</div>
                            <div class="mt-2 grid grid-cols-2 gap-3">
                                <div>
                                    <div class="text-[11px] text-gray-500">Invoice</div>
                                    <div class="text-sm font-semibold text-gray-900">{{ $clearance->invoice_number ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-[11px] text-gray-500">Delivery note</div>
                                    <div class="text-sm font-semibold text-gray-900">{{ $clearance->delivery_note_number ?? '—' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white p-4">
                            <div class="text-xs font-semibold text-gray-600 uppercase">TR8</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900">
                                {{ $clearance->tr8_number ?: '—' }}
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-3 text-xs text-gray-600">
                                <div>
                                    <div class="text-[11px] text-gray-500">Submitted</div>
                                    <div class="font-semibold text-gray-900">{{ $clearance->submitted_at ? $clearance->submitted_at->format('Y-m-d H:i') : '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-[11px] text-gray-500">Issued</div>
                                    <div class="font-semibold text-gray-900">{{ $clearance->tr8_issued_at ? $clearance->tr8_issued_at->format('Y-m-d H:i') : '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if(!empty($clearance->notes))
                        <div class="mt-4 rounded-xl border border-gray-200 bg-white p-4">
                            <div class="text-xs font-semibold text-gray-600 uppercase">Notes</div>
                            <div class="mt-1 text-sm text-gray-800 whitespace-pre-line">{{ $clearance->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Documents --}}
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="p-4 sm:p-5 flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">Documents</div>
                        <div class="mt-1 text-xs text-gray-500">Fast access for audit and client comms.</div>
                    </div>
                    <div class="text-xs text-gray-500">
                        TR8 files: <span class="font-semibold text-gray-900">{{ $docsTr8->count() }}</span>
                    </div>
                </div>

                <div class="border-t border-gray-100">
                    @if($docsTr8->isEmpty())
                        <div class="p-5 text-sm text-gray-600">
                            No TR8 documents uploaded yet.
                        </div>
                    @else
                        <div class="divide-y divide-gray-100">
                            @foreach($docsTr8 as $doc)
                                <div class="p-4 sm:p-5 flex items-center justify-between gap-4">
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-gray-900 truncate">
                                            {{ $doc->original_name ?? basename($doc->file_path) }}
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500">
                                            Uploaded {{ optional($doc->created_at)->diffForHumans() ?? '—' }}
                                        </div>
                                    </div>

                                    {{-- ✅ OPEN via secure route (fixes Apache/XAMPP "Not Found") --}}
                                    <a href="{{ route('depot.compliance.clearances.documents.open', [$clearance, $doc]) }}" target="_blank"
                                       class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M12 3v10m0 0 4-4m-4 4-4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M5 15v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                        Open
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- Right: activity timeline --}}
        <div class="space-y-4">

            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="p-4 sm:p-5">
                    <div class="text-sm font-semibold text-gray-900">Activity</div>
                    <div class="mt-1 text-xs text-gray-500">Complete audit trail for this clearance.</div>

                    <div class="mt-4 space-y-3">
                        @forelse($events as $e)
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-gray-900">
                                            {{ strtoupper(str_replace('_',' ', $e->to_status ?? $e->event ?? 'EVENT')) }}
                                        </div>
                                        <div class="mt-1 text-xs text-gray-600">
                                            From: <span class="font-semibold text-gray-900">{{ $e->from_status ?? '—' }}</span>
                                            → To: <span class="font-semibold text-gray-900">{{ $e->to_status ?? '—' }}</span>
                                        </div>
                                    </div>
                                    <div class="text-[11px] text-gray-500 whitespace-nowrap">
                                        {{ optional($e->created_at)->format('Y-m-d H:i') ?? '—' }}
                                    </div>
                                </div>

                                <div class="mt-2 text-xs text-gray-500">
                                    By : <span class="font-semibold text-gray-800">{{ $e->user?->name ?? '—' }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-gray-600">No events recorded yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Helpful meta --}}
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="p-4 sm:p-5">
                    <div class="text-sm font-semibold text-gray-900">Meta</div>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-xs">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                            <div class="text-[11px] text-gray-500">Created</div>
                            <div class="mt-1 font-semibold text-gray-900">
                                {{ $clearance->created_at ? $clearance->created_at->format('Y-m-d H:i') : '—' }}
                            </div>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                            <div class="text-[11px] text-gray-500">Last update</div>
                            <div class="mt-1 font-semibold text-gray-900">
                                {{ $clearance->updated_at ? $clearance->updated_at->format('Y-m-d H:i') : '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- If you already include the TR8 modal on index only, include it here too --}}
@includeIf('depot-stock::compliance.clearances.partials._issue_tr8_modal')

@push('scripts')
<script>
    // If you want the "Issue TR8" button on show to open the same modal
    window.addEventListener('open-issue-tr8', (e) => {
        const id = e.detail?.id;
        if (!id) return;

        // re-use same ids you already use
        const modal = document.getElementById('issueTr8Modal');
        const form  = document.getElementById('issueTr8Form');
        if (!modal || !form) return;

        const baseUrl = @json(url('depot/compliance/clearances'));
        const action  = `${baseUrl}/${id}/issue-tr8`;
        form.setAttribute('action', action);

        // clear fields
        document.getElementById('issueTr8Number')?.focus();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    });
</script>
@endpush
@endsection