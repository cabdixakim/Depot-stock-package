{{-- resources/views/vendor/depot-stock/operations/layout.blade.php --}}
@extends('depot-stock::layouts.app')

@section('title', $title ?? 'Depot operations')

@push('styles')
<style>
    /* Base sidebar behaviour */
    .ops-sidebar {
        transition: transform 0.2s ease-out;
        will-change: transform;
    }

    /* Mobile: closed by default */
    .ops-sidebar.is-closed {
        transform: translateX(-100%);
    }

    .ops-sidebar.is-open {
        transform: translateX(0);
    }

    /* Desktop: always visible, ignore drawer transforms */
    @media (min-width: 768px) {
        .ops-sidebar {
            transform: none !important;
            position: relative !important;
        }

        #opsSidebarHandle {
            display: none !important;
        }
    }
</style>
@endpush

@section('content')
<div class="flex min-h-screen relative">

    {{-- Drawer handle (mobile only) --}}
    <button
        id="opsSidebarHandle"
        type="button"
        class="md:hidden fixed top-1/2 left-0 z-30
               inline-flex items-center justify-center
               h-8 w-6 rounded-r-full bg-gray-900/90 text-white shadow-lg
               transform -translate-y-1/2"
    >
        <svg id="opsSidebarHandleIcon"
             class="h-3.5 w-3.5 transform transition-transform"
             viewBox="0 0 20 20" fill="currentColor">
            {{-- default: chevron ">" (drawer closed) --}}
            <path d="M7.25 4.5L12.5 10l-5.25 5.5a.75.75 0 01-1.1-1.02L10.25 10 6.15 5.52a.75.75 0 011.1-1.02z" />
        </svg>
    </button>

    {{-- LEFT: sidebar (drawer on mobile, fixed on desktop) --}}
    <aside
        id="opsSidebar"
        class="ops-sidebar is-closed
               fixed md:relative
               top-14 md:top-auto bottom-0 left-0
               w-60 shrink-0 border-r border-gray-100 bg-white/95 px-3 py-4
               z-20 md:z-0"
    >
        <p class="px-2 mb-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500">
            Depot operations
            <span class="mt-0.5 block text-[10px] font-normal normal-case text-gray-400">
                Daily work, dips & clients.
            </span>
        </p>

        @php
            $r = request()->route()?->getName() ?? '';
        @endphp

        <nav class="space-y-1 text-xs">
            {{-- Dashboard --}}
            <a href="{{ route('depot.operations.index') }}"
               class="flex items-center justify-between rounded-lg px-2.5 py-2
                      {{ $r === 'depot.operations.index'
                          ? 'bg-gray-900 text-white'
                          : 'text-gray-700 hover:bg-gray-50' }}">
                <span class="inline-flex items-center gap-2">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                    <span>Dashboard</span>
                </span>
            </a>

            {{-- Daily dips (new recon module) --}}
            <a href="{{ route('depot.operations.daily-dips') }}"
               class="flex items-center justify-between rounded-lg px-2.5 py-2
                      {{ $r === 'depot.operations.daily-dips'
                          ? 'bg-gray-900 text-white'
                          : 'text-gray-700 hover:bg-gray-50' }}">
                <span class="inline-flex items-center gap-2">
                    <span class="h-1.5 w-1.5 rounded-full bg-indigo-400"></span>
                    <span>Daily dips</span>
                </span>
            </a>

            {{-- Legacy simple dip history --}}
            <a href="{{ route('depot.operations.dips-history') }}"
               class="flex items-center justify-between rounded-lg px-2.5 py-2
                      {{ $r === 'depot.operations.dips-history'
                          ? 'bg-gray-900 text-white'
                          : 'text-gray-700 hover:bg-gray-50' }}">
                <span class="inline-flex items-center gap-2">
                    <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
                    <span>Simple dip history</span>
                </span>
            </a>

            {{-- Operations clients --}}
            <a href="{{ route('depot.operations.clients.index') }}"
               class="flex items-center justify-between rounded-lg px-2.5 py-2
                      {{ $r === 'depot.operations.clients.index'
                          ? 'bg-gray-900 text-white'
                          : 'text-gray-700 hover:bg-gray-50' }}">
                <span class="inline-flex items-center gap-2">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                    <span>Clients</span>
                </span>
            </a>
        </nav>
    </aside>

    {{-- RIGHT: main content area --}}
    <main class="flex-1 bg-slate-50/60 px-4 md:px-6 py-6">
        <div class="mx-auto max-w-6xl space-y-4">
            @yield('ops-content')
        </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('opsSidebar');
    const handle  = document.getElementById('opsSidebarHandle');
    const icon    = document.getElementById('opsSidebarHandleIcon');

    if (!sidebar || !handle || !icon) return;

    const isMobile = () => window.innerWidth < 768;

    function openSidebar() {
        sidebar.classList.remove('is-closed');
        sidebar.classList.add('is-open');
        icon.classList.add('rotate-180');   // flip to "<"
    }

    function closeSidebar() {
        sidebar.classList.remove('is-open');
        sidebar.classList.add('is-closed');
        icon.classList.remove('rotate-180'); // back to ">"
    }

    function toggleSidebar() {
        if (!isMobile()) return; // desktop just uses fixed sidebar
        if (sidebar.classList.contains('is-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    handle.addEventListener('click', (e) => {
        e.preventDefault();
        toggleSidebar();
    });

    // Close when you tap a link (on mobile)
    sidebar.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            if (isMobile()) {
                closeSidebar();
            }
        });
    });

    // Click outside closes it (mobile)
    document.addEventListener('click', (e) => {
        if (!isMobile()) return;
        if (!sidebar.classList.contains('is-open')) return;

        const sidebarRect = sidebar.getBoundingClientRect();
        const handleRect  = handle.getBoundingClientRect();
        const x = e.clientX;
        const y = e.clientY;

        const insideSidebar =
            x >= sidebarRect.left && x <= sidebarRect.right &&
            y >= sidebarRect.top && y <= sidebarRect.bottom;

        const insideHandle =
            x >= handleRect.left && x <= handleRect.right &&
            y >= handleRect.top && y <= handleRect.bottom;

        if (!insideSidebar && !insideHandle) {
            closeSidebar();
        }
    });

    // On resize: make sure desktop is always open
    window.addEventListener('resize', () => {
        if (!isMobile()) {
            sidebar.classList.remove('is-closed');
            sidebar.classList.add('is-open');
            icon.classList.remove('rotate-180');
        } else {
            // default closed when coming back to mobile
            closeSidebar();
        }
    });

    // Initial state
    if (!isMobile()) {
        sidebar.classList.remove('is-closed');
        sidebar.classList.add('is-open');
    } else {
        closeSidebar();
    }
});
</script>
@endpush