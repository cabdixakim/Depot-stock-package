@extends('depot-stock::operations.layout')

@section('ops-content')
@php
    $totalClients = (method_exists($clients, 'total')
        ? $clients->total()
        : (is_countable($clients) ? count($clients) : 0));
@endphp

<div class="space-y-5">
    {{-- Page header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">
                Operations clients
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Quick access to all depot clients you work with daily.
            </p>
        </div>

        <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center">
            {{-- Search --}}
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" />
                    </svg>
                </span>
                <input
                    id="opsClientSearch"
                    type="text"
                    class="w-full rounded-xl border border-gray-200 bg-white pl-9 pr-3 py-2 text-xs text-gray-800 shadow-sm
                           placeholder:text-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/60"
                    placeholder="Search client by name or ID…"
                    autocomplete="off"
                >
            </div>

            <div class="flex items-center justify-end gap-3 text-[11px] text-gray-500">
                <div class="rounded-full bg-gray-100 px-3 py-1 font-medium">
                    {{ $totalClients }} client{{ $totalClients === 1 ? '' : 's' }}
                </div>
            </div>
        </div>
    </div>

    {{-- Stats row --}}
    <div class="grid gap-3 sm:grid-cols-2">
        <div
            class="rounded-2xl border border-gray-100 bg-white/90 p-4 shadow-sm
                   transition duration-150 hover:-translate-y-0.5 hover:shadow-md">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                Total clients
            </p>
            <p class="mt-2 text-2xl font-semibold text-gray-900">
                {{ $totalClients }}
            </p>
            <p class="mt-1 text-[11px] text-gray-400">
                All clients configured in the depot module.
            </p>
        </div>

        <div
            class="rounded-2xl border border-gray-100 bg-white/90 p-4 shadow-sm
                   transition duration-150 hover:-translate-y-0.5 hover:shadow-md">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                Recently added
            </p>
            @php
                $recentCount = $clients->filter(fn($c) =>
                    optional($c->created_at)->gt(now()->subDays(7))
                )->count();
            @endphp
            <p class="mt-2 text-2xl font-semibold text-gray-900">
                {{ $recentCount }}
            </p>
            <p class="mt-1 text-[11px] text-gray-400">
                Created in the last 7 days.
            </p>
        </div>
    </div>

    {{-- Clients list --}}
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">
                    Clients
                </h2>
                <p class="text-[11px] text-gray-500">
                    Lightweight list optimised for daily depot operations.
                </p>
            </div>
        </div>

        @if($totalClients === 0)
            <div class="px-6 py-10 text-center text-sm text-gray-500">
                No clients found yet. Create clients first in the main <span class="font-semibold">Clients</span> module.
            </div>
        @else
            <ul id="opsClientList" class="divide-y divide-gray-100">
                @foreach($clients as $client)
                    @php
                        $rawCode = $client->code
                            ?? $client->short_code
                            ?? $client->account_code
                            ?? $client->reference
                            ?? null;

                        $code = $rawCode ? strtoupper(trim($rawCode)) : null;

                        // Code pill text
                        $pillText = $code
                            ? strtoupper(mb_substr($code, 0, 8))
                            : strtoupper(mb_substr($client->name ?? 'CL', 0, 3));

                        $since = optional($client->created_at)->format('Y-m-d');
                        $isLocked = (bool)($client->is_locked ?? false);
                        $statusLabel = $isLocked ? 'Locked' : 'Active';
                        $statusColor = $isLocked
                            ? 'bg-rose-50 text-rose-700 border-rose-100'
                            : 'bg-emerald-50 text-emerald-700 border-emerald-100';
                    @endphp

                    <li class="group transition-colors hover:bg-gray-50/80"
                        data-name="{{ mb_strtolower($client->name ?? '') }}"
                        data-id="{{ $client->id }}"
                    >
                        <div class="flex w-full items-center justify-between gap-4 px-4 py-3 text-sm">

                            {{-- Left side --}}
                            <a href="{{ route('depot.clients.show', $client) }}"
                               class="flex min-w-0 flex-1 items-center gap-3">
                                {{-- NEW subtle stylish code pill --}}
                                <span
                                    class="inline-flex items-center justify-center rounded-xl
                                           bg-gray-100 border border-gray-200 text-gray-700
                                           px-3 py-1 text-[10px] font-semibold tracking-wide uppercase
                                           shadow-sm transition duration-150
                                           group-hover:shadow group-hover:scale-[1.03]"
                                >
                                    {{ $pillText }}
                                </span>

                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-900">
                                        {{ $client->name ?? 'Unnamed client #'.$client->id }}
                                    </p>
                                    <p class="mt-0.5 truncate text-[11px] text-gray-500">
                                        Client ID: {{ $client->id }}
                                        @if($code) · Code: {{ $code }} @endif
                                        @if($since) · Since {{ $since }} @endif
                                    </p>
                                </div>
                            </a>

                            {{-- Right side --}}
                            <div class="flex flex-col items-end gap-1 text-right">
                                <span
                                    class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium {{ $statusColor }}">
                                    <span class="mr-1 inline-block h-1.5 w-1.5 rounded-full {{ $isLocked ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
                                    {{ $statusLabel }}
                                </span>
                                <a href="{{ route('depot.clients.show', $client) }}"
                                   class="inline-flex items-center gap-1 rounded-full border border-gray-300 bg-white px-3 py-1 text-[11px]
                                          font-medium text-gray-700 shadow-sm hover:bg-gray-50 active:scale-[0.97]">
                                    Open workspace
                                </a>
                            </div>

                        </div>
                    </li>
                @endforeach
            </ul>

            {{-- Pagination --}}
            @if(method_exists($clients, 'links'))
                <div class="border-t border-gray-100 px-4 py-3">
                    {{ $clients->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('opsClientSearch');
    const list  = document.getElementById('opsClientList');
    if (!input || !list) return;

    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        [...list.querySelectorAll('li[data-name]')].forEach(li => {
            const name = li.dataset.name ?? '';
            const id   = li.dataset.id ?? '';
            li.classList.toggle('hidden', !(name.includes(q) || id.includes(q)));
        });
    });
});
</script>
@endpush