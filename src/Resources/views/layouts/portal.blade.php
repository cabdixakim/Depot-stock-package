{{-- resources/views/depot-stock/layouts/portal.blade.php --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title','Client Portal')</title>

    @vite(['resources/css/app.css','resources/js/app.js'])

    <style>
        .portal-nav {
            backdrop-filter: blur(14px);
            background: linear-gradient(
                120deg,
                rgba(15,23,42,0.90),
                rgba(15,23,42,0.85)
            );
        }
    </style>

    @stack('styles')
    <link rel="manifest" href="{{ asset('client.webmanifest') }}">
<meta name="theme-color" content="#111827">
</head>

<body class="h-full bg-slate-950 text-slate-100">

    {{-- ========================================================= --}}
    {{-- 🔥 Sticky Portal Navbar --}}
    {{-- ========================================================= --}}
    <nav class="portal-nav sticky top-0 z-50 border-b border-slate-800/80 shadow-[0_10px_40px_rgba(15,23,42,0.9)]">
        @php
            $user   = auth()->user();
            $client = $user?->client;
            $clientName = $client->name ?? 'Client';
        @endphp

        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-3">

            {{-- Left side: brand + desktop nav + mobile toggle --}}
            <div class="flex items-center gap-4">

                {{-- Brand: {ClientName} Portal (click → home) --}}
                <a href="{{ route('portal.home') }}"
                   class="flex items-center gap-2 group">
                    <div class="h-7 w-7 rounded-2xl bg-sky-500/20 border border-sky-400/40 grid place-items-center">
                        <span class="text-[11px] font-semibold text-sky-200 tracking-[0.16em]">
                            {{ strtoupper(mb_substr($clientName,0,2)) }}
                        </span>
                    </div>
                    <div class="font-semibold text-slate-100 tracking-wide text-sm max-w-[160px] truncate group-hover:text-sky-300 transition">
                        {{ $clientName }} Portal
                    </div>
                </a>

                {{-- Desktop nav links --}}
                <div class="hidden md:flex items-center gap-5 text-sm">

                    <a href="{{ route('portal.movements') }}"
                       class="transition text-slate-300 hover:text-sky-400 {{ request()->routeIs('portal.movements*') ? 'text-sky-400 font-medium' : '' }}">
                        Movements
                    </a>

                    <a href="{{ route('portal.statements') }}"
                       class="transition text-slate-300 hover:text-sky-400 {{ request()->routeIs('portal.statements*') ? 'text-sky-400 font-medium' : '' }}">
                        Statements
                    </a>

                    <a href="{{ route('portal.invoices') }}"
                       class="transition text-slate-300 hover:text-sky-400 {{ request()->routeIs('portal.invoices*') ? 'text-sky-400 font-medium' : '' }}">
                        Invoices
                    </a>

                    <a href="{{ route('portal.payments') }}"
                       class="transition text-slate-300 hover:text-sky-400 {{ request()->routeIs('portal.payments*') ? 'text-sky-400 font-medium' : '' }}">
                        Payments
                    </a>
                </div>

                {{-- Mobile hamburger --}}
                <button type="button"
                        id="portalMobileToggle"
                        class="md:hidden inline-flex items-center justify-center h-8 w-8 rounded-lg border border-slate-700/70 bg-slate-900/70 text-slate-200 hover:border-sky-500/70 hover:text-sky-300 transition">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M4 7h16M4 12h16M4 17h16"
                              stroke-width="1.8"
                              stroke-linecap="round"
                              stroke-linejoin="round" />
                    </svg>
                </button>
            </div>

            {{-- Right side: User dropdown --}}
            <div class="flex items-center gap-2">

                <div class="relative">
                    <button type="button"
                            id="portalUserBtn"
                            class="group flex items-center gap-2 rounded-full border border-slate-700/70 bg-slate-900/70 px-2.5 py-1.5 text-xs text-slate-200 hover:border-sky-500/70 hover:bg-slate-900/90 transition">
                        {{-- Avatar --}}
                        <div class="relative h-7 w-7 rounded-full bg-gradient-to-br from-sky-500/70 via-sky-400/70 to-emerald-400/70 border border-sky-300/60 grid place-items-center">
                            <span class="text-[11px] font-semibold text-slate-950">
                                {{ $user ? strtoupper(mb_substr($user->name,0,1)) : 'U' }}
                            </span>
                            {{-- Online dot --}}
                            <span class="absolute -bottom-0.5 -right-0.5 h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_6px_3px_rgba(16,185,129,0.8)]"></span>
                        </div>

                        {{-- Name + chevron (desktop) --}}
                        <span class="hidden md:flex items-center gap-1">
                            <span class="max-w-[110px] truncate text-[12px] font-medium text-slate-100">
                                {{ $user->name ?? 'User' }}
                            </span>
                            <svg class="h-3.5 w-3.5 text-slate-400 group-hover:text-sky-400 transition"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor">
                                <path d="M6 9l6 6 6-6"
                                      stroke-width="1.8"
                                      stroke-linecap="round"
                                      stroke-linejoin="round" />
                            </svg>
                        </span>

                        {{-- Mobile chevron only --}}
                        <span class="md:hidden">
                            <svg class="h-3.5 w-3.5 text-slate-400 group-hover:text-sky-400 transition"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor">
                                <path d="M6 9l6 6 6-6"
                                      stroke-width="1.8"
                                      stroke-linecap="round"
                                      stroke-linejoin="round" />
                            </svg>
                        </span>
                    </button>

                    {{-- Dropdown menu --}}
                    <div id="portalUserMenu"
                         class="hidden absolute right-0 mt-2 w-60 rounded-2xl border border-slate-800 bg-slate-950/95 shadow-2xl text-sm overflow-hidden">
                        {{-- Header --}}
                        <div class="px-4 py-3 border-b border-slate-800/80 bg-gradient-to-r from-slate-950 via-slate-900 to-slate-950">
                            <div class="text-[11px] uppercase tracking-[0.2em] text-slate-500 mb-0.5">
                                Signed in
                            </div>
                            <div class="text-[13px] font-semibold text-slate-50 truncate">
                                {{ $user->name ?? 'Client user' }}
                            </div>
                            <div class="text-[11px] text-slate-400 truncate">
                                {{ $user->email ?? 'client@example.com' }}
                            </div>
                        </div>

                        {{-- Account link --}}
                        <a href="{{ route('portal.account') }}"
                           class="flex items-center gap-2.5 px-4 py-2.5 text-slate-200 hover:bg-slate-900/90">
                            <div class="h-7 w-7 rounded-xl bg-sky-500/15 border border-sky-500/30 grid place-items-center">
                                <svg class="h-4 w-4 text-sky-300" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zM4 20a8 8 0 0 1 16 0"
                                          stroke-width="1.7"
                                          stroke-linecap="round"
                                          stroke-linejoin="round" />
                                </svg>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-[13px] font-medium text-slate-50">Account &amp; Security</span>
                                <span class="text-[11px] text-slate-400">
                                    Manage password and basic details.
                                </span>
                            </div>
                        </a>

                        {{-- Divider --}}
                        <div class="border-t border-slate-800/80"></div>

                        {{-- Logout --}}
                        <form action="{{ route('depot.logout') }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="w-full flex items-center gap-2.5 px-4 py-2.5 text-[13px] text-slate-200 hover:bg-slate-900/90">
                                <span class="inline-block h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_6px_2px_rgba(16,185,129,0.6)]"></span>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Mobile dropdown nav --}}
        <div id="portalMobileNav"
             class="md:hidden hidden border-t border-slate-800/80 bg-slate-950/95">
            <div class="max-w-6xl mx-auto px-4 py-2 flex flex-col gap-1 text-sm">
                <a href="{{ route('portal.movements') }}"
                   class="py-1.5 text-slate-200 hover:text-sky-300 {{ request()->routeIs('portal.movements*') ? 'text-sky-400 font-medium' : '' }}">
                    Movements
                </a>
                <a href="{{ route('portal.statements') }}"
                   class="py-1.5 text-slate-200 hover:text-sky-300 {{ request()->routeIs('portal.statements*') ? 'text-sky-400 font-medium' : '' }}">
                    Statements
                </a>
                <a href="{{ route('portal.invoices') }}"
                   class="py-1.5 text-slate-200 hover:text-sky-300 {{ request()->routeIs('portal.invoices*') ? 'text-sky-400 font-medium' : '' }}">
                    Invoices
                </a>
                <a href="{{ route('portal.payments') }}"
                   class="py-1.5 text-slate-200 hover:text-sky-300 {{ request()->routeIs('portal.payments*') ? 'text-sky-400 font-medium' : '' }}">
                    Payments
                </a>
            </div>
        </div>
    </nav>

    {{-- ========================================================= --}}
    {{-- Page Content --}}
    {{-- ========================================================= --}}
    <main class="pt-4">
        @yield('content')
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const userBtn   = document.getElementById('portalUserBtn');
            const userMenu  = document.getElementById('portalUserMenu');
            const mobBtn    = document.getElementById('portalMobileToggle');
            const mobNav    = document.getElementById('portalMobileNav');

            // User dropdown
            if (userBtn && userMenu) {
                userBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userMenu.classList.toggle('hidden');
                });

                userMenu.addEventListener('click', (e) => e.stopPropagation());
            }

            // Mobile menu toggle
            if (mobBtn && mobNav) {
                mobBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    mobNav.classList.toggle('hidden');
                });
            }

            // Global click → close both menus
            document.addEventListener('click', () => {
                if (userMenu) userMenu.classList.add('hidden');
                if (mobNav)   mobNav.classList.add('hidden');
            });
        });
    </script>

    {{-- For page-specific JS (password eye etc.) --}}
    @stack('scripts')
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register("{{ asset('service-worker.js') }}");
        });
    }
</script>
</body>
</html>