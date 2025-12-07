<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title','Depot Stock')</title>

  {{-- CSRF for AJAX --}}
  <meta name="csrf-token" content="{{ csrf_token() }}">

  {{-- CSS --}}
  <!-- Tailwind CDN – bypass Vite completely -->
<script src="https://cdn.tailwindcss.com"></script>
  
<!-- remember to uncomment this during production -->
<!-- @vite(['resources/css/app.css']) -->

  {{-- Livewire styles (if you use them) --}}
  @livewireStyles

  {{-- Page-level extras --}}
  @stack('styles')

  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
  <meta name="theme-color" content="#111827">
</head>
<body class="bg-gray-50 text-gray-900 antialiased">
  @php
    $u = auth()->user();
    $roleNames = $u?->roles?->pluck('name')->map(fn ($r) => strtolower($r))->all() ?? [];

    $isAdmin     = in_array('admin', $roleNames) || in_array('owner', $roleNames) || in_array('superadmin', $roleNames);
    $isOps       = in_array('operations', $roleNames) || in_array('ops', $roleNames);
    $isAccounts  = in_array('accounts', $roleNames) || in_array('accounting', $roleNames);

    $isPureOps   = $isOps && ! $isAdmin && ! $isAccounts;
  @endphp

  @php
    $user = auth()->user();
    $userName = $user?->name ?? 'User';
    $initials = collect(explode(' ', $userName))
        ->map(fn($s)=>mb_substr($s,0,1))
        ->take(2)
        ->implode('') ?: 'U';

    // Depot header data (safe in layout)
    $depotsHdr       = \Optima\DepotStock\Models\Depot::orderBy('name')->get();
    $activeDepotId   = session('depot.active_id');
    $activeDepot     = $activeDepotId ? $depotsHdr->firstWhere('id', $activeDepotId) : null;
    $activeDepotName = $activeDepot?->name ?? 'All Depots';
  @endphp

  {{-- Top Bar --}}
  <header class="sticky top-0 z-40 bg-white/80 backdrop-blur border-b border-gray-100">
    <div class="mx-auto max-w-7xl px-4 md:px-6 h-14 flex items-center justify-between">
      <div class="flex items-center gap-3">
        {{-- Mobile menu toggle (left) --}}
        <button
          id="mobileNavToggle"
          type="button"
          class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white p-1.5 text-gray-600 hover:bg-gray-50 md:hidden"
        >
          <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
            <path d="M3 6.25C3 5.84 3.34 5.5 3.75 5.5h12.5a.75.75 0 010 1.5H3.75A.75.75 0 013 6.25zm0 4c0-.41.34-.75.75-.75h12.5a.75.75 0 010 1.5H3.75A.75.75 0 013 10.25zm0 4c0-.41.34-.75.75-.75h8.5a.75.75 0 010 1.5h-8.5A.75.75 0 013 14.25z" />
          </svg>
        </button>

        {{-- Brand: centred on mobile, normal on md+ --}}
        @if($isPureOps)
          <span
            class="inline-flex items-center gap-2 font-semibold text-gray-900 flex-1 justify-center md:flex-none md:justify-start"
          >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
              <path d="M3 11h4v10H3zM9 3h4v18H9zM15 7h4v14h-4z"/>
            </svg>
            Depot Stock
          </span>
        @else
          <a href="{{ route('depot.dashboard') }}"
             class="inline-flex items-center gap-2 font-semibold text-gray-900 flex-1 justify-center md:flex-none md:justify-start">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
              <path d="M3 11h4v10H3zM9 3h4v18H9zM15 7h4v14h-4z"/>
            </svg>
            Depot Stock
          </a>
        @endif

        {{-- Main nav gated by roles (desktop only) --}}
        <nav class="hidden md:flex items-center gap-1">
          @unless($isPureOps)
            {{-- Everyone --}}
            <a href="{{ route('depot.dashboard') }}" class="navlink">Dashboard</a>

            {{-- Ops + Accounts + Admin --}}
            @if($isAdmin || $isAccounts || $isOps )
              <a href="{{ route('depot.clients.index') }}" class="navlink">Clients</a>
            @endif

            {{-- Accounts + Admin --}}
            @if($isAccounts || $isAdmin)
              <a href="{{ route('depot.invoices.index') }}" class="navlink">Invoices</a>
              <a href="{{ route('depot.payments.index') }}" class="navlink">Payments</a>
            @endif

            {{-- Admin + Accounts --}}
            @if($isAdmin || $isAccounts)
              <a href="{{ route('depot.pool.index') }}" class="navlink">Depot Pool</a>
            @endif
          @endunless

          {{-- Tank Dips / Depot operations (always visible) --}}
          @php
            $isDepotOps = str_starts_with(request()->route()?->getName() ?? '', 'depot.operations.');
          @endphp

          <a href="{{ route('depot.operations.index') }}"
             class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-xs font-medium
                {{ $isDepotOps ? 'bg-gray-900 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
            <svg class="h-4 w-4 opacity-80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M4 7h16M4 12h10M4 17h7M17 10l3 2-3 2" />
            </svg>
            <span>Depot operations</span>
          </a>
        </nav>
      </div>

      <div class="flex items-center gap-2">
        {{-- Quick search slot (optional) --}}
        @yield('topbar-search')

        {{-- Custom Depot dropdown (hidden for pure ops) --}}
        @unless($isPureOps)
          <div id="depotDropdown" class="hidden md:flex items-center gap-2 mr-2">
            <span class="text-[11px] uppercase tracking-wide text-gray-400">Depot</span>

            <div class="relative">
              <button id="depotDropdownBtn"
                      type="button"
                      class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white/90 pl-3 pr-2 py-1.5 text-xs font-medium text-gray-800 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-semibold text-white">
                  {{ mb_substr($activeDepotName,0,1) }}
                </span>
                <span class="max-w-[10rem] truncate">
                  {{ $activeDepotName }}
                </span>
                <svg class="h-3 w-3 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M5.25 7.5L10 12.25 14.75 7.5H5.25z"/>
                </svg>
              </button>

              <div id="depotDropdownMenu"
                   class="hidden absolute right-0 mt-2 w-56 rounded-xl border border-gray-200 bg-white shadow-lg py-1 text-xs z-50">
                <button type="button"
                        data-depot-id="all"
                        class="flex w-full items-center justify-between px-3 py-2 hover:bg-gray-50">
                  <span class="flex items-center gap-2">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-gray-900 text-[10px] font-semibold text-white">
                      *
                    </span>
                    <span>All Depots</span>
                  </span>
                  @if(!$activeDepotId)
                    <span class="text-[10px] text-emerald-600 font-semibold">Active</span>
                  @endif
                </button>

                <div class="my-1 h-px bg-gray-100"></div>

                @foreach($depotsHdr as $d)
                  <button type="button"
                          data-depot-id="{{ $d->id }}"
                          class="flex w-full items-center justify-between px-3 py-2 hover:bg-gray-50">
                    <span class="flex items-center gap-2">
                      <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-indigo-600/90 text-[10px] font-semibold text-white">
                        {{ mb_substr($d->name,0,1) }}
                      </span>
                      <span class="truncate max-w-[8rem]">{{ $d->name }}</span>
                    </span>
                    @if($activeDepotId == $d->id)
                      <span class="text-[10px] text-emerald-600 font-semibold">Active</span>
                    @endif
                  </button>
                @endforeach
              </div>
            </div>
          </div>
        @endunless

        {{-- Settings button (admin only, not pure ops) --}}
        @if($isAdmin && ! $isPureOps)
          <button id="openSettings"
                  class="hidden sm:inline-flex rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm hover:bg-gray-50">
            Settings
          </button>
        @endif

        {{-- Divider --}}
        <span class="hidden sm:inline-block h-5 w-px bg-gray-200 mx-1"></span>

        {{-- User chip + dropdown (always) --}}
        <div class="relative" id="userMenuRoot">
          <button id="userMenuBtn"
                  class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-2.5 py-1.5 hover:bg-gray-50">
            <span class="h-6 w-6 grid place-content-center rounded-md bg-gray-900 text-white text-[11px] font-semibold">
              {{ $initials }}
            </span>
            <span class="hidden sm:block text-xs text-gray-700 max-w-[10rem] truncate">
              Signed in as <span class="font-medium">{{ $userName }}</span>
            </span>
            <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
              <path d="M5.5 7.5l4.5 4.5 4.5-4.5"/>
            </svg>
          </button>

          <div id="userMenu"
               class="hidden absolute right-0 mt-2 w-56 rounded-xl border border-gray-200 bg-white shadow-lg overflow-hidden">
            <div class="px-3 py-2 text-[11px] uppercase tracking-wide text-gray-400">
              Account
            </div>

            {{-- Account settings trigger --}}
            <button type="button" id="openAccountFromMenu"
                    class="block w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
              Account settings
            </button>

            <div class="my-1 h-px bg-gray-100"></div>
            {{-- Real logout via POST to package route --}}
            <form method="POST" action="{{ route('depot.logout') }}">
              @csrf
              <button type="submit"
                      class="w-full text-left px-3 py-2 text-sm text-rose-700 hover:bg-rose-50">
                Sign out
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    {{-- Mobile collapsible nav --}}
    <div id="mobileNavPanel" class="md:hidden hidden border-t border-gray-100 bg-white">
      <div class="mx-auto max-w-7xl px-4 py-2 space-y-1 text-sm">
        @unless($isPureOps)
          <a href="{{ route('depot.dashboard') }}" class="navlink block">Dashboard</a>

          @if($isAdmin || $isAccounts || $isOps )
            <a href="{{ route('depot.clients.index') }}" class="navlink block">Clients</a>
          @endif

          @if($isAccounts || $isAdmin)
            <a href="{{ route('depot.invoices.index') }}" class="navlink block">Invoices</a>
            <a href="{{ route('depot.payments.index') }}" class="navlink block">Payments</a>
          @endif

          @if($isAdmin || $isAccounts)
            <a href="{{ route('depot.pool.index') }}" class="navlink block">Depot Pool</a>
          @endif
        @endunless

        {{-- Depot operations always --}}
        <a href="{{ route('depot.operations.index') }}" class="navlink block">Depot operations</a>
      </div>
    </div>
  </header>

  {{-- Main container --}}
  <main class="mx-auto max-w-7xl px-4 md:px-6 py-6">
    @hasSection('header')
      <h1 class="text-xl md:text-2xl font-semibold text-gray-900 mb-4">@yield('header')</h1>
    @endif

    @if (session('status'))
      <div class="p-3 mb-4 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-100">
        {{ session('status') }}
      </div>
    @endif

    @yield('content')
  </main>

  {{-- Livewire scripts --}}
  @livewireScripts

  {{-- Toast --}}
  <div id="ui-toast"
       class="pointer-events-none fixed left-1/2 top-5 z-[100] hidden -translate-x-1/2 rounded-lg px-4 py-2 text-sm shadow-lg"
       role="status" aria-live="polite"></div>

  {{-- Confirm Modal --}}
  <div id="ui-confirm" class="fixed inset-0 z-[90] hidden items-center justify-center">
    <div class="absolute inset-0 bg-black/40"></div>
    <div class="relative z-10 w-full max-w-sm rounded-xl bg-white p-5 shadow-2xl">
      <h3 id="ui-confirm-title" class="text-base font-semibold">Are you sure?</h3>
      <p id="ui-confirm-text" class="mt-1 text-sm text-gray-600"></p>
      <div class="mt-5 flex items-center justify-end gap-2">
        <button type="button" id="ui-confirm-cancel"
                class="rounded-lg border px-4 py-2 text-sm hover:bg-gray-50">Cancel</button>
        <button type="button" id="ui-confirm-ok"
                class="rounded-lg bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700">Confirm</button>
      </div>
    </div>
  </div>

  {{-- Account Settings Modal (staff profile + password) --}}
  <div id="accountModal" class="fixed inset-0 z-[94] hidden">
    <div class="absolute inset-0 bg-black/40" data-close-account></div>
    <div class="absolute inset-0 flex items-start justify-center p-4 md:p-8 overflow-y-auto">
      <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-gray-100">
        <div class="flex items-center justify-between border-b px-6 py-4 bg-gray-50 rounded-t-2xl">
          <div>
            <h3 class="font-semibold text-gray-900">Account settings</h3>
            <p class="text-xs text-gray-500 mt-0.5">
              Update your profile details and password.
            </p>
          </div>
          <button type="button"
                  class="text-gray-500 hover:text-gray-800"
                  data-close-account>
            ✕
          </button>
        </div>

        {{-- Tab header --}}
        <div class="px-6 pt-3 border-b border-gray-100">
          <div class="inline-flex rounded-full bg-gray-100 p-0.5 text-xs">
            <button type="button"
                    data-account-tab="profile"
                    class="account-tab-btn px-3 py-1.5 rounded-full font-medium text-gray-800 bg-white shadow-sm">
              Profile
            </button>
            <button type="button"
                    data-account-tab="password"
                    class="account-tab-btn px-3 py-1.5 rounded-full font-medium text-gray-500">
              Password
            </button>
          </div>
        </div>

        <div class="p-6 space-y-6">

          {{-- PROFILE TAB --}}
          <div id="accountTabProfile" class="account-tab space-y-4">
            <form method="POST" action="{{ route('depot.account.profile') }}" class="space-y-4">
              @csrf
              <div class="flex items-center gap-3">
                <div class="h-10 w-10 flex items-center justify-center rounded-lg bg-gray-900 text-white text-sm font-semibold">
                  {{ $initials }}
                </div>
                <div class="text-xs text-gray-500">
                  Logged in as<br>
                  <span class="font-medium text-gray-800">{{ $userName }}</span>
                </div>
              </div>

              <div>
                <label class="block text-[11px] uppercase tracking-wide text-gray-500">
                  Full name
                </label>
                <input type="text"
                       name="name"
                       value="{{ $userName }}"
                       class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-1 focus:ring-gray-400">
              </div>

              <div>
                <label class="block text-[11px] uppercase tracking-wide text-gray-500">
                  Email
                </label>
                <input type="email"
                       value="{{ $user?->email }}"
                       disabled
                       class="mt-1 w-full rounded-xl border border-gray-100 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                <p class="mt-1 text-[11px] text-gray-400">
                  Email changes are handled by your administrator.
                </p>
              </div>

              <div class="flex justify-end gap-2 pt-2">
                <button type="button"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                        data-close-account>
                  Cancel
                </button>
                <button type="submit"
                        class="rounded-xl bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black">
                  Save profile
                </button>
              </div>
            </form>
          </div>

          {{-- PASSWORD TAB --}}
          <div id="accountTabPassword" class="account-tab space-y-4 hidden">
            <form method="POST" action="{{ route('depot.account.password') }}" class="space-y-4">
              @csrf
              <p class="text-xs text-gray-500">
                Choose a strong password that you don’t reuse elsewhere.
              </p>

              <div class="space-y-1">
                <label class="block text-[11px] uppercase tracking-wide text-gray-500">
                  Current password
                </label>
                <div class="relative">
                  <input type="password"
                         name="current_password"
                         class="account-pwd-input mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 pr-9 text-sm focus:border-gray-400 focus:ring-1 focus:ring-gray-400">
                  <button type="button"
                          class="account-eye-btn absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z" stroke-width="1.6"/>
                      <circle cx="12" cy="12" r="3" stroke-width="1.6"/>
                    </svg>
                  </button>
                </div>
              </div>

              <div class="grid gap-3 sm:grid-cols-2">
                <div class="space-y-1">
                  <label class="block text-[11px] uppercase tracking-wide text-gray-500">
                    New password
                  </label>
                  <div class="relative">
                    <input type="password"
                           name="password"
                           class="account-pwd-input mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 pr-9 text-sm focus:border-gray-400 focus:ring-1 focus:ring-gray-400">
                    <button type="button"
                            class="account-eye-btn absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z" stroke-width="1.6"/>
                        <circle cx="12" cy="12" r="3" stroke-width="1.6"/>
                      </svg>
                    </button>
                  </div>
                </div>

                <div class="space-y-1">
                  <label class="block text-[11px] uppercase tracking-wide text-gray-500">
                    Confirm password
                  </label>
                  <div class="relative">
                    <input type="password"
                           name="password_confirmation"
                           class="account-pwd-input mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 pr-9 text-sm focus:border-gray-400 focus:ring-1 focus:ring-gray-400">
                    <button type="button"
                            class="account-eye-btn absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z" stroke-width="1.6"/>
                        <circle cx="12" cy="12" r="3" stroke-width="1.6"/>
                      </svg>
                    </button>
                  </div>
                  {{-- inline validation message --}}
                  <p id="accountPwdError" class="mt-1 text-[11px] text-rose-600 hidden">
                    Passwords do not match.
                  </p>
                </div>
              </div>

              <div class="flex justify-between items-center pt-1">
                <p class="text-[11px] text-gray-400 max-w-xs">
                  Minimum 8 characters. Use a mix of letters, numbers and symbols.
                </p>
                <div class="flex gap-2">
                  <button type="button"
                          class="rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                          data-close-account>
                    Cancel
                  </button>
                  <button type="submit"
                          id="accountPwdSubmit"
                          class="rounded-xl bg-gradient-to-r from-indigo-600 to-sky-500 px-4 py-1.5 text-sm font-semibold text-white shadow-sm hover:shadow-md hover:from-indigo-700 hover:to-sky-600 disabled:opacity-60 disabled:cursor-not-allowed">
                    Update password
                  </button>
                </div>
              </div>
            </form>
          </div>

        </div>
      </div>
    </div>
  </div>

  {{-- Settings Modal: Profit Margin + depot shortcut + Users link --}}
  <div id="settingsModal" class="fixed inset-0 z-[95] hidden">
    <div class="absolute inset-0 bg-black/40" data-close-settings></div>
    <div class="absolute inset-0 flex items-start justify-center p-4 md:p-8 overflow-y-auto">
      <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-gray-100">
        <div class="flex items-center justify-between border-b px-6 py-4 bg-gray-50 rounded-t-2xl">
          <h3 class="font-semibold text-gray-900">Configuration</h3>
          <button type="button" class="text-gray-500 hover:text-gray-800" data-close-settings>✕</button>
        </div>

        <form id="settingsForm" class="p-6 space-y-6">
          {{-- Profit margin --}}
          <div class="space-y-2">
            <label class="block text-[11px] uppercase tracking-wide text-gray-500">
              Global Profit Margin (per litre)
            </label>
            <div class="mt-1 flex items-center gap-3 flex-wrap">
              <input id="pm_margin" type="number" min="0" step="0.0001"
                     placeholder="e.g. 10.0000"
                     class="w-40 rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-1 focus:ring-gray-400">
              <span class="text-xs text-gray-500 max-w-xs">
                Used on the dashboard to compute profit for all client offloads.
              </span>
            </div>
            <p id="pm_feedback" class="hidden mt-1 text-sm"></p>
          </div>

          {{-- Depot settings shortcut --}}
          <div class="border-t border-dashed border-gray-200 pt-4">
            <div class="flex items-center justify-between gap-3 flex-wrap">
              <div>
                <div class="text-[11px] uppercase tracking-wide text-gray-500">Depot & Tanks</div>
                <p class="text-xs text-gray-500 mt-1">
                  Manage depots and tank definitions directly in depot settings.
                </p>
              </div>
              <a href="{{ route('depot.depots.index') }}"
                 class="inline-flex items-center gap-1 rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">
                <span>Open Depot Settings</span>
                <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M7 4h9v9h-2V7.414L5.707 15.707 4.293 14.293 12.586 6H7z"/>
                </svg>
              </a>
            </div>
          </div>

          @if($isAdmin)
            <div class="px-2 pt-2">
              <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 px-2 mb-1">
                Access Control
              </div>

              <a href="{{ route('depot.settings.users.index') }}"
                 class="block group">
                <div class="flex items-center justify-between
                            rounded-xl bg-white border border-slate-200
                            px-3 py-3 shadow-sm
                            hover:shadow-md hover:border-slate-300
                            hover:bg-slate-50
                            transition-all duration-150">

                  <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center
                                rounded-xl bg-gradient-to-br from-indigo-600 to-indigo-700
                                text-white text-[13px] font-bold
                                shadow-sm group-hover:shadow-md transition-all">
                      UA
                    </div>

                    <div>
                      <div class="text-sm font-medium text-slate-800 group-hover:text-slate-900">
                        Users & Roles
                      </div>
                      <div class="text-[11px] text-slate-400 group-hover:text-slate-500">
                        Manage accounts & permissions
                      </div>
                    </div>
                  </div>

                  <svg class="h-4 w-4 text-slate-400 group-hover:text-slate-600 transition"
                       fill="none" stroke="currentColor" stroke-width="2"
                       viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 5l7 7-7 7"/>
                  </svg>
                </div>
              </a>
            </div>
          @endif

          <div class="flex justify-end gap-2 pt-4 border-t border-gray-100">
            <button type="button"
                    class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700"
                    data-close-settings>Close</button>
            <button id="pm_save" type="submit"
                    class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
              Save margin
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- Global helpers --}}
  <script>
    // ---------- Topbar active link style ----------
    (function() {
      const path = location.pathname;
      document.querySelectorAll('.navlink').forEach(a => {
        const href = a.getAttribute('href') || '';
        const isActive = href && path.startsWith(href);
        a.className = 'navlink ' + (isActive ? 'navlink--active' : '');
      });
    })();

    // ---------- Mobile nav toggle ----------
    (function () {
      const btn   = document.getElementById('mobileNavToggle');
      const panel = document.getElementById('mobileNavPanel');
      if (!btn || !panel) return;
      btn.addEventListener('click', () => {
        panel.classList.toggle('hidden');
      });
    })();

    // ---------- Toast ----------
    (function () {
      const el = document.getElementById('ui-toast');
      window.toast = function (msg = 'Done', ok = true) {
        if (!el) return;
        el.textContent = msg;
        el.className =
          "pointer-events-none fixed left-1/2 top-5 z-[100] -translate-x-1/2 rounded-lg px-4 py-2 text-sm shadow-lg " +
          (ok ? "bg-emerald-600 text-white" : "bg-red-600 text-white");
        el.style.display = 'block';
        clearTimeout(el._t);
        el._t = setTimeout(() => el.style.display = 'none', 2500);
      }
    })();

    // ---------- Confirm Modal (Promise) ----------
    (function () {
      const wrap   = document.getElementById('ui-confirm');
      const title  = document.getElementById('ui-confirm-title');
      const text   = document.getElementById('ui-confirm-text');
      const btnOk  = document.getElementById('ui-confirm-ok');
      const btnCan = document.getElementById('ui-confirm-cancel');

      window.askConfirm = function ({heading="Are you sure?", message="", okText="Confirm", cancelText="Cancel"} = {}) {
        return new Promise((resolve) => {
          title.textContent = heading;
          text.textContent  = message;
          btnOk.textContent = okText;
          btnCan.textContent= cancelText;

          wrap.classList.remove('hidden','opacity-0');
          wrap.classList.add('flex');

          const done = (val) => {
            wrap.classList.add('hidden');
            wrap.classList.remove('flex');
            resolve(val);
            btnOk.onclick = btnCan.onclick = null;
            wrap.onclick = null;
            document.onkeydown = null;
          };

          btnOk.onclick  = () => done(true);
          btnCan.onclick = () => done(false);
          wrap.onclick   = (e) => { if (e.target === wrap) done(false); };
          document.onkeydown = (e) => { if (e.key === 'Escape') done(false); };
        });
      }
    })();

    // ---------- User menu dropdown ----------
    (function () {
      const root = document.getElementById('userMenuRoot');
      const btn  = document.getElementById('userMenuBtn');
      const menu = document.getElementById('userMenu');

      const openAccountFromMenu = document.getElementById('openAccountFromMenu');

      function hide(){ menu.classList.add('hidden'); }
      function toggle(){ menu.classList.toggle('hidden'); }

      btn?.addEventListener('click', (e)=>{ e.stopPropagation(); toggle(); });
      document.addEventListener('click', (e)=>{ if(!root.contains(e.target)) hide(); });
      document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') hide(); });

      // delegate account modal open
      openAccountFromMenu?.addEventListener('click', ()=>{
        hide();
        const ev = new CustomEvent('ds:account:open');
        window.dispatchEvent(ev);
      });
    })();

    // ---------- Settings Modal (Profit Margin) ----------
    (function () {
      const modal   = document.getElementById('settingsModal');
      const openBtn = document.getElementById('openSettings');
      if (!modal || !openBtn) return;

      const closeEls= modal.querySelectorAll('[data-close-settings]');
      const form    = document.getElementById('settingsForm');
      const input   = document.getElementById('pm_margin');
      const feedback= document.getElementById('pm_feedback');

      function open()  { modal.classList.remove('hidden'); fetchCurrent(); }
      function close() { modal.classList.add('hidden'); }

      openBtn?.addEventListener('click', open);
      closeEls.forEach(b => b.addEventListener('click', close));

      async function fetchCurrent() {
        try {
          const res = await fetch('{{ route('depot.settings.margins.current') }}', {
            headers: { 'Accept': 'application/json' }
          });
          const data = await res.json();
          input.value = Number(data?.margin ?? 0).toFixed(4);
        } catch(_) {
          input.value = '0.0000';
        }
      }

      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        feedback.className='hidden';
        try {
          const res = await fetch('{{ route('depot.settings.margins.setCurrent') }}', {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              'Accept': 'application/json',
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({ margin: Number(input.value || 0) })
          });
          if (!res.ok) throw new Error(await res.text());
          const data = await res.json();
          feedback.textContent = data.message || 'Saved';
          feedback.className   = 'mt-2 text-sm text-emerald-700';
          toast('Margin updated');
          window.dispatchEvent(new CustomEvent('ds:margin:updated', { detail: { value: data.margin }}));
        } catch (err) {
          feedback.textContent = 'Failed to save.';
          feedback.className   = 'mt-2 text-sm text-rose-700';
          toast('Failed to save margin', false);
        }
      });
    })();

    // ---------- Custom Depot dropdown -> set active + reload ----------
    (function () {
      const root = document.getElementById('depotDropdown');
      if (!root) return;

      const btn  = document.getElementById('depotDropdownBtn');
      const menu = document.getElementById('depotDropdownMenu');

      function hide(){ menu.classList.add('hidden'); }
      function toggle(){ menu.classList.toggle('hidden'); }

      btn?.addEventListener('click', (e)=>{
        e.stopPropagation();
        toggle();
      });

      document.addEventListener('click', (e)=>{
        if (!root.contains(e.target)) hide();
      });

      document.addEventListener('keydown', (e)=>{
        if (e.key === 'Escape') hide();
      });

      menu.querySelectorAll('[data-depot-id]').forEach(item => {
        item.addEventListener('click', async () => {
          const depotId = item.getAttribute('data-depot-id') || 'all';
          hide();
          try {
            const fd = new FormData();
            fd.append('depot_id', depotId);

            await fetch("{{ route('depot.depots.setActive') }}", {
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
              },
              body: fd
            });

            location.reload();
          } catch (e) {
            toast('Failed to switch depot', false);
          }
        });
      });
    })();

    // ---------- Account Settings Modal logic ----------
    (function () {
      const modal = document.getElementById('accountModal');
      if (!modal) return;

      const closeEls = modal.querySelectorAll('[data-close-account]');
      const tabBtns  = modal.querySelectorAll('.account-tab-btn');
      const tabs     = {
        profile: document.getElementById('accountTabProfile'),
        password: document.getElementById('accountTabPassword'),
      };

      function open() {
        modal.classList.remove('hidden');
      }
      function close() {
        modal.classList.add('hidden');
      }

      // open from global event (user menu)
      window.addEventListener('ds:account:open', open);

      closeEls.forEach(el => el.addEventListener('click', close));
      modal.addEventListener('click', (e)=>{
        if (e.target === modal) close();
      });

      function activateTab(name) {
        Object.entries(tabs).forEach(([key, el]) => {
          if (!el) return;
          if (key === name) {
            el.classList.remove('hidden');
          } else {
            el.classList.add('hidden');
          }
        });

        tabBtns.forEach(btn => {
          const target = btn.getAttribute('data-account-tab');
          if (target === name) {
            btn.classList.add('bg-white','shadow-sm','text-gray-800');
            btn.classList.remove('text-gray-500');
          } else {
            btn.classList.remove('bg-white','shadow-sm','text-gray-800');
            btn.classList.add('text-gray-500');
          }
        });
      }

      tabBtns.forEach(btn => {
        btn.addEventListener('click', ()=>{
          const name = btn.getAttribute('data-account-tab') || 'profile';
          activateTab(name);
        });
      });

      // default tab
      activateTab('profile');

      // password eyes
      const eyeBtns = modal.querySelectorAll('.account-eye-btn');
      eyeBtns.forEach(btn => {
        btn.addEventListener('click', ()=>{
          const input = btn.parentElement.querySelector('.account-pwd-input');
          if (!input) return;
          const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
          input.setAttribute('type', type);
        });
      });

      // real-time password match validation
      const pwdForm      = modal.querySelector('#accountTabPassword form');
      const newPwdInput  = pwdForm ? pwdForm.querySelector('input[name="password"]') : null;
      const confirmInput = pwdForm ? pwdForm.querySelector('input[name="password_confirmation"]') : null;
      const errorEl      = modal.querySelector('#accountPwdError');
      const submitBtn    = modal.querySelector('#accountPwdSubmit');

      function validatePwdMatch() {
        if (!newPwdInput || !confirmInput || !errorEl || !submitBtn) return;
        const p1 = newPwdInput.value;
        const p2 = confirmInput.value;

        // if both empty, no error and button enabled
        if (!p1 && !p2) {
          errorEl.classList.add('hidden');
          newPwdInput.classList.remove('border-rose-400');
          confirmInput.classList.remove('border-rose-400');
          submitBtn.disabled = false;
          return;
        }

        if (p2 && p1 !== p2) {
          errorEl.classList.remove('hidden');
          newPwdInput.classList.add('border-rose-400');
          confirmInput.classList.add('border-rose-400');
          submitBtn.disabled = true;
        } else {
          errorEl.classList.add('hidden');
          newPwdInput.classList.remove('border-rose-400');
          confirmInput.classList.remove('border-rose-400');
          submitBtn.disabled = false;
        }
      }

      if (newPwdInput)  newPwdInput.addEventListener('input', validatePwdMatch);
      if (confirmInput) confirmInput.addEventListener('input', validatePwdMatch);
    })();
  </script>

  @push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Safety: ensure any overlay starts hidden
  ['recordPaymentModal', 'applyCreditModal', 'movementsGridModal'].forEach(id => {
    const el = document.getElementById(id);
    if (el && !el.classList.contains('hidden')) {
      el.classList.add('hidden');
    }
  });
});

// Global panic button: ESC closes any visible full-screen overlay
document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  document.querySelectorAll('.fixed.inset-0')
    .forEach(el => {
      // Only hide overlays, not your whole layout
      if (el.id && (el.id.includes('Modal') || el.dataset.overlay === 'backdrop')) {
        el.classList.add('hidden');
      }
    });
});
</script>
@endpush

  {{-- JS bundle at end --}}
  @vite(['resources/js/app.js'])

  {{-- Page scripts --}}
  @stack('scripts')

  {{-- Inline small CSS for navlink --}}
  <style>
    .navlink {
      padding: .5rem .75rem;
      border-radius: .75rem;
      font-size: .875rem;
      color: #6b7280; /* gray-500 */
    }
    .navlink:hover { color:#111827; background:#F3F4F6; }
    .navlink--active { color:#111827; background:#EEF2FF; } /* indigo-50 */
  </style>

  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function () {
        navigator.serviceWorker.register("{{ asset('service-worker.js') }}");
      });
    }
  </script>

</body>
</html>