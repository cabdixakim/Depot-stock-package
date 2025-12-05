@extends('depot-stock::layouts.app')

@section('title', $client->name . ' • Client')

@section('content')

@php
    $u = auth()->user();
    $roleNames = $u?->roles?->pluck('name')->map(fn ($r) => strtolower($r))->all() ?? [];

    // tweak these to match EXACTLY what you have in roles table
    $isAdmin     = in_array('admin', $roleNames) || in_array('owner', $roleNames) || in_array('superadmin', $roleNames);
    $isOps       = in_array('operations', $roleNames) || in_array('ops', $roleNames);
    $isAccounts  = in_array('accounts', $roleNames) || in_array('accounting', $roleNames);

    // 🔒 Client lock state
    $blockedLoads    = !$client->can_load;
    $blockedOffloads = !$client->can_offload;

    // For convenience in the sidebar buttons
    $loadDisabledForOps    = $blockedLoads && !$isAdmin && !$isAccounts;
    $offloadDisabledForOps = $blockedOffloads && !$isAdmin && !$isAccounts;
@endphp

@php
  $fmtLiters = function ($v) {
      if ($v === null) return '—';
      $s = number_format((float)$v, 3, '.', ',');
      return rtrim(rtrim($s, '0'), '.');
  };

  $from = request('from');
  $to   = request('to');
  $hasFilters = $from || $to || request('tank_id') || request('product_id');
@endphp

<div class="min-h-[100dvh] bg-[#F8FAFC] text-[15px] sm:text-[14px]">

  {{-- ===== Sticky top bar ===== --}}
  <header class="sticky top-0 z-30 bg-white/80 backdrop-blur border-b border-gray-100">
    <div class="mx-auto max-w-7xl px-4 md:px-6 h-14 flex items-center justify-between gap-3">
      <div class="flex items-center gap-3 min-w-0">
        <button type="button" id="btnOpenDrawer"
                class="md:hidden inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        <div class="truncate">
          <div class="text-[11px] uppercase tracking-wide text-gray-500">Client</div>
          <div class="font-semibold text-gray-900 truncate">{{ $client->name }}</div>
        </div>

        @php
          $statusPillText  = 'Active';
          $statusPillClass = 'bg-emerald-50 text-emerald-700';
          if ($blockedLoads && $blockedOffloads) {
            $statusPillText  = 'Loads & offloads halted';
            $statusPillClass = 'bg-rose-50 text-rose-700';
          } elseif ($blockedLoads) {
            $statusPillText  = 'Loads halted';
            $statusPillClass = 'bg-amber-50 text-amber-800';
          } elseif ($blockedOffloads) {
            $statusPillText  = 'Offloads halted';
            $statusPillClass = 'bg-rose-50 text-rose-700';
          }
        @endphp

        <span class="shrink-0 inline-flex items-center gap-1 rounded-full {{ $statusPillClass }} px-2 py-0.5 text-[11px] border border-current/20">
          @if($blockedLoads || $blockedOffloads)
            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
          @else
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
          @endif
          {{ $statusPillText }}
        </span>
      </div>

      <nav class="flex items-center gap-2">
        <a href="{{ url()->previous() }}"
         class="hidden md:inline-flex h-9 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 hover:bg-gray-50">
        ← Back
        </a>
      </nav>
    </div>
  </header>

  {{-- ===== Page grid ===== --}}
  <div class="mx-auto max-w-7xl px-4 md:px-6 py-6 grid md:grid-cols-[18rem,1fr] gap-6">

    {{-- ===== Sidebar (drawer on mobile) ===== --}}
    <aside id="sideDrawer"
           class="fixed inset-y-0 left-0 z-40 w-[19rem] -translate-x-full transition-transform duration-300
                  md:static md:translate-x-0 md:w-[18rem] md:z-auto md:sticky md:top-6 md:self-start">

      <div class="h-full overflow-y-auto md:overflow-visible md:h-auto">
        <div class="rounded-2xl bg-white/80 backdrop-blur shadow-sm ring-1 ring-gray-100 p-5 space-y-6">

          {{-- Client card --}}
          <section class="space-y-3">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <h1 class="text-sm font-semibold text-gray-900 truncate">{{ $client->name }}</h1>
                <p class="mt-0.5 text-[11px] text-gray-500">
                  Code: <span class="font-mono">{{ $client->code }}</span>
                </p>
              </div>
              <a href="{{ route('depot.clients.index') }}"
                 class="text-xs text-gray-500 hover:text-gray-700 whitespace-nowrap">← Back</a>
            </div>

            <div class="text-[13px] leading-6 space-y-1">
              @if($client->email)
                <div class="truncate">
                  <a class="text-indigo-600 hover:underline" href="mailto:{{ $client->email }}">{{ $client->email }}</a>
                </div>
              @endif
              @if($client->phone)
                <div class="text-gray-700">{{ $client->phone }}</div>
              @endif
              @if($client->billing_terms)
                <div class="text-gray-500">
                  Terms: <span class="font-medium text-gray-800">{{ $client->billing_terms }}</span>
                </div>
              @endif
            </div>
          </section>

          {{-- Quick Actions --}}
          <section>
            <div class="mb-2 text-[11px] uppercase tracking-wide text-gray-500">Quick Actions</div>
            <div class="grid gap-2">
              @if($isOps || $isAdmin)
                {{-- Offload (IN) --}}
                <button type="button" data-open-offload
                  class="group inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 text-white px-3 py-2 text-sm hover:bg-indigo-700 shadow-sm
                         {{ $offloadDisabledForOps ? 'opacity-40 cursor-not-allowed pointer-events-none' : '' }}">
                  <svg class="h-4 w-4 opacity-80 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-width="2" d="M12 5v14M5 12h14"/>
                  </svg>
                  Offload (IN)
                </button>

                {{-- Load (OUT) --}}
                <button type="button" data-open-load
                  class="group inline-flex items-center justify-center gap-2 rounded-xl bg-sky-600 text-white px-3 py-2 text-sm hover:bg-sky-700 shadow-sm
                         {{ $loadDisabledForOps ? 'opacity-40 cursor-not-allowed pointer-events-none' : '' }}">
                  <svg class="h-4 w-4 opacity-80 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-width="2" d="M12 5v14M5 12h14"/>
                  </svg>
                  Load (OUT)
                </button>
              @endif

              @if($isAdmin)
                <button type="button" data-open-adjust
                  class="group inline-flex items-center justify-center gap-2 rounded-xl bg-amber-600 text-white px-3 py-2 text-sm hover:bg-amber-700 shadow-sm">
                  <svg class="h-4 w-4 opacity-80 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-width="2" d="M12 6v12M6 12h12"/>
                  </svg>
                  Adjustment
                </button>
              @endif
            </div>

            {{-- Small notice for operators if blocked --}}
            @if(($blockedLoads || $blockedOffloads) && $isOps && !$isAdmin && !$isAccounts)
              <p class="mt-2 text-[11px] text-rose-600 bg-rose-50 border border-rose-100 rounded-lg px-2 py-1.5">
                This client is currently halted for
                @if($blockedLoads && $blockedOffloads)
                  loads and offloads.
                @elseif($blockedLoads)
                  loads.
                @else
                  offloads.
                @endif
                Please contact Accounts / Admin for approval.
              </p>
            @endif
          </section>

          {{-- Billing / Reports --}}
          <section>
            <div class="mb-2 text-[11px] uppercase tracking-wide text-gray-500">Billing & Reports</div>
            <nav class="space-y-1 text-sm">
              @if($isAccounts || $isAdmin)
                <a href="{{ route('depot.clients.billing.waiting', $client) }}
"
                   class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-gray-50">
                  <span class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path stroke-width="2" d="M8 6h8M7 10h10M6 14h12M5 18h14"/>
                    </svg>
                    Billing
                  </span>
                  <span class="text-gray-400">›</span>
                </a>

                @if (Route::has('depot.invoices.index'))
                  <a href="{{ route('depot.invoices.index', ['client' => $client->id]) }}"
                     class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-gray-50">
                    <span class="inline-flex items-center gap-2">
                      <svg class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-width="2" d="M8 6h8M8 10h8M8 14h5M5 19h14V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v14Z"/>
                      </svg>
                      Invoices
                    </span>
                    <span class="text-gray-400">›</span>
                  </a>
                @else
                  <span class="flex items-center justify-between rounded-lg px-3 py-2 text-gray-400 bg-gray-50">
                    <span class="inline-flex items-center gap-2">
                      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-width="2" d="M8 6h8M8 10h8M8 14h5M5 19h14V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v14Z"/>
                      </svg>
                      Invoices
                    </span>
                    <span>—</span>
                  </span>
                @endif

                @if (Route::has('depot.payments.index'))
                  <a href="{{ route('depot.payments.index', ['client' => $client->id]) }}"
                     class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-gray-50">
                    <span class="inline-flex items-center gap-2">
                      <svg class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-width="2" d="M21 12a9 9 0 1 1-9-9"/>
                      </svg>
                      Payments
                    </span>
                    <span class="text-gray-400">›</span>
                  </a>
                @else
                  <span class="flex items-center justify-between rounded-lg px-3 py-2 text-gray-400 bg-gray-50">
                    <span class="inline-flex items-center gap-2">
                      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-width="2" d="M21 12a9 9 0 1 1-9-9"/>
                      </svg>
                      Payments
                    </span>
                    <span>—</span>
                  </span>
                @endif

                {{-- Reports --}}
                @if (Route::has('depot.clients.statement'))
                  <a href="{{ route('depot.clients.statement', ['client' => $client->id]) }}"
                     class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-gray-50">
                    <span class="inline-flex items-center gap-2">
                      <svg class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-width="2" d="M7 8h10M7 12h10M7 16h7"/>
                      </svg>
                      Statement
                    </span>
                    <span class="text-gray-400">›</span>
                  </a>
                @else
                  <span class="flex items-center justify-between rounded-lg px-3 py-2 text-gray-400 bg-gray-50">
                    <span class="inline-flex items-center gap-2">
                      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-width="2" d="M7 8h10M7 12h10M7 16h7"/>
                      </svg>
                      Statement
                    </span>
                    <span>—</span>
                  </span>
                @endif
              @endif

              @if($isAdmin)
                @if (Route::has('depot.pool.index'))
                  <a href="{{ route('depot.pool.index') }}"
                     class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-gray-50">
                    <span class="inline-flex items-center gap-2">
                      <svg class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/>
                      </svg>
                      Depot Pool
                    </span>
                    <span class="text-gray-400">›</span>
                  </a>
                @endif
              @endif
            </nav>
          </section>

        </div>
      </div>
    </aside>

    {{-- Backdrop for mobile drawer --}}
    <div id="drawerBackdrop" class="fixed inset-0 z-30 bg-black/40 hidden md:hidden"></div>

    {{-- ===== Main content ===== --}}
    <main class="space-y-6">

      {{-- If operator & blocked, show a prominent banner at top --}}
      @if(($blockedLoads || $blockedOffloads) && $isOps && !$isAdmin && !$isAccounts)
        <section class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 flex items-start gap-3">
          <div class="mt-0.5">⚠️</div>
          <div>
            <div class="font-semibold mb-0.5">Client access is restricted.</div>
            <div class="text-[13px]">
              This client is currently halted for
              @if($blockedLoads && $blockedOffloads)
                loading and offloading.
              @elseif($blockedLoads)
                loading.
              @else
                offloading.
              @endif
              You can still view history, but cannot perform new movements. Please ask Accounts / Admin to review and unlock if appropriate.
            </div>
          </div>
        </section>
      @endif

      {{-- Filters --}}
      <form method="GET" class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 p-4">
        <div class="flex flex-wrap items-end gap-3">
          <div>
            <label class="block text-[11px] uppercase tracking-wide text-gray-500">From</label>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                   class="mt-1 rounded-xl border-gray-200 focus:ring-0">
          </div>
          <div>
            <label class="block text-[11px] uppercase tracking-wide text-gray-500">To</label>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                   class="mt-1 rounded-xl border-gray-200 focus:ring-0">
          </div>
          <div>
            <label class="block text-[11px] uppercase tracking-wide text-gray-500">Tank</label>
            <select name="tank_id" class="mt-1 rounded-xl border-gray-200 focus:ring-0">
              <option value="">All tanks</option>
              @foreach($tanks as $t)
                <option value="{{ $t->id }}" @selected(($filters['tank_id'] ?? null) == $t->id)>
                  {{ $t->depot->name }} — {{ $t->product->name }} (T#{{ $t->id }})
                </option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="block text-[11px] uppercase tracking-wide text-gray-500">Product</label>
            <select name="product_id" class="mt-1 rounded-xl border-gray-200 focus:ring-0">
              <option value="">All products</option>
              @foreach(($products ?? collect()) as $p)
                <option value="{{ $p->id }}" @selected(($filters['product_id'] ?? null) == $p->id)>{{ $p->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="ml-auto flex gap-2">
            <button class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm hover:bg-gray-50">Apply</button>
            <a href="{{ route('depot.clients.show', $client) }}"
               class="rounded-xl bg-gray-900 text-white px-3 py-2 text-sm hover:bg-black">Reset</a>
          </div>
        </div>

        {{-- Filter chips --}}
        @if ($hasFilters)
          <div class="mt-3 flex flex-wrap gap-2 text-xs">
            @if ($from)
              <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-gray-700">
                From: {{ \Carbon\Carbon::parse($from)->format('d M Y') }}
              </span>
            @endif
            @if ($to)
              <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-gray-700">
                To: {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
              </span>
            @endif
            @if (request('tank_id'))
              <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-gray-700">
                Tank: T#{{ request('tank_id') }}
              </span>
            @endif
            @if (request('product_id') && isset($products))
              @php $p = $products->firstWhere('id', request('product_id')); @endphp
              @if($p)
                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-gray-700">
                  Product: {{ $p->name }}
                </span>
              @endif
            @endif
          </div>
        @endif
      </form>

      {{-- Loss summary --}}
      <section class="rounded-2xl bg-gradient-to-r from-amber-50 via-white to-gray-50 border border-amber-100 p-3 sm:p-4 flex flex-wrap items-center justify-between shadow-sm">
        <div class="flex items-center gap-3 text-sm">
          <span class="inline-flex items-center justify-center h-7 w-7 rounded-lg bg-amber-100 text-amber-700">Δ</span>
          <div>
            @if ($hasFilters)
              <span class="font-semibold text-gray-800">Loss as at</span>
              <span class="ml-1 inline-block text-xs text-gray-700 bg-gray-100 px-1.5 py-0.5 rounded">
                {{ \Carbon\Carbon::parse($to ?? now())->format('d M Y') }}
              </span>
              <span class="ml-1 text-xs text-gray-500">(shrinkage & shortfall)</span>
            @else
              <span class="font-semibold text-gray-800">All Time Loss</span>
              <span class="ml-1 text-xs text-gray-500">(shrinkage & shortfall)</span>
            @endif
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-4 text-sm mt-2 sm:mt-0">
          <div class="flex items-center gap-1 text-amber-700">
            <span class="font-medium">{{ number_format(($loss['depot_shrink'] ?? 0), 1) }}</span><span class="text-xs">L Shrinkage</span>
          </div>
          <div class="flex items-center gap-1 text-rose-700">
            <span class="font-medium">{{ number_format(($loss['truck_short'] ?? 0), 1) }}</span><span class="text-xs">L Shortfall</span>
          </div>
          <div class="flex items-center gap-1 text-gray-900 font-semibold">
            <span>{{ number_format(($loss['total_loss'] ?? 0), 1) }}</span><span class="text-xs text-gray-500">L Total</span>
          </div>
          <button type="button"
                  class="ml-1 sm:ml-3 rounded-lg border border-gray-200 px-2.5 py-1 text-xs text-gray-600 hover:bg-gray-100"
                  data-open-loss>View Details</button>
        </div>
      </section>

      {{-- KPI cards --}}
      <section class="grid grid-cols-1 lg:grid-cols-5 gap-4">

        {{-- Current Stock / Stock as at (Hero card, first) --}}
        <div class="lg:col-span-2 rounded-2xl relative overflow-hidden shadow-sm ring-1 ring-emerald-200/60 bg-gradient-to-br from-emerald-50 via-white to-cyan-50">
          {{-- soft pattern --}}
          <div aria-hidden="true" class="pointer-events-none absolute -top-8 -right-10 h-40 w-40 rounded-full bg-emerald-200/30 blur-2xl"></div>
          <div aria-hidden="true" class="pointer-events-none absolute -bottom-10 -left-10 h-48 w-48 rounded-full bg-cyan-200/30 blur-2xl"></div>

          <div class="relative p-4 sm:p-5">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-2">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-600 text-white">
                  <!-- check icon -->
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12l5 5L20 7"/>
                  </svg>
                </span>
                <div class="text-[11px] uppercase tracking-wide text-emerald-800/80">
                  {{ $hasFilters ? 'Stock as at' : 'Current Stock' }}
                </div>
              </div>

              @if ($hasFilters)
                <span class="shrink-0 inline-flex items-center rounded-lg bg-white/70 ring-1 ring-emerald-200 px-2 py-1 text-[11px] text-emerald-900">
                  {{ \Carbon\Carbon::parse($to ?? now())->format('d M Y') }}
                </span>
              @endif
            </div>

            <div class="mt-2 sm:mt-3 flex items-end gap-2">
              <div class="text-3xl sm:text-4xl font-semibold text-gray-900 leading-none">
                {{ $fmtLiters($currentStock ?? 0) }}
              </div>
              <div class="pb-1 text-sm text-gray-500">L</div>
            </div>

            <div class="mt-1 text-[11px] text-gray-500">
              IN − OUT + ADJ
            </div>

            {{-- quick breakdown chips --}}
            <div class="mt-3 flex flex-wrap gap-2 text-xs">
              <span class="inline-flex items-center gap-1 rounded-full bg-emerald-600/10 text-emerald-800 px-2 py-1 ring-1 ring-emerald-200">
                IN: <span class="font-medium">{{ $fmtLiters($totIn ?? 0) }}</span>L
              </span>
              <span class="inline-flex items-center gap-1 rounded-full bg-sky-600/10 text-sky-800 px-2 py-1 ring-1 ring-sky-200">
                OUT: <span class="font-medium">{{ $fmtLiters($totOut ?? 0) }}</span>L
              </span>
              <span class="inline-flex items-center gap-1 rounded-full bg-amber-600/10 text-amber-800 px-2 py-1 ring-1 ring-amber-200">
                ADJ: <span class="font-medium">{{ $fmtLiters($totAdj ?? 0) }}</span>L
              </span>
            </div>
          </div>
        </div>

        {{-- Offloaded (IN) --}}
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
          <div class="flex items-center justify-between">
            <span class="text-[11px] uppercase tracking-wide text-gray-500">Offloaded (IN)</span>
            <svg class="h-4 w-4 text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="2" d="M12 19V5m0 0l-6 6m6-6 6 6"/></svg>
          </div>
          <div class="mt-1 text-2xl font-semibold text-gray-900">
            {{ $fmtLiters($totIn ?? 0) }} <span class="text-sm text-gray-500">L</span>
          </div>
          <div class="text-[11px] text-gray-400 mt-1">
            @if ($from || $to)
              From <span class="text-gray-600">{{ $from ? \Carbon\Carbon::parse($from)->format('d M Y') : '—' }}</span>
              to <span class="text-gray-600">{{ $to ? \Carbon\Carbon::parse($to)->format('d M Y') : now()->format('d M Y') }}</span>
            @else
              To date
            @endif
          </div>
        </div>

        {{-- Loaded (OUT) --}}
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
          <div class="flex items-center justify-between">
            <span class="text-[11px] uppercase tracking-wide text-gray-500">Loaded (OUT)</span>
            <svg class="h-4 w-4 text-sky-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="2" d="M12 5v14m0 0l6-6m-6 6-6-6"/></svg>
          </div>
          <div class="mt-1 text-2xl font-semibold text-gray-900">
            {{ $fmtLiters($totOut ?? 0) }} <span class="text-sm text-gray-500">L</span>
          </div>
          <div class="text-[11px] text-gray-400 mt-1">
            @if ($from || $to)
              From <span class="text-gray-600">{{ $from ? \Carbon\Carbon::parse($from)->format('d M Y') : '—' }}</span>
              to <span class="text-gray-600">{{ $to ? \Carbon\Carbon::parse($to)->format('d M Y') : now()->format('d M Y') }}</span>
            @else
              To date
            @endif
          </div>
        </div>

        {{-- Adjustments --}}
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
          <div class="flex items-center justify-between">
            <span class="text-[11px] uppercase tracking-wide text-gray-500">Adjustments</span>
            <svg class="h-4 w-4 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="2" d="M4 12h16M12 4v16"/></svg>
          </div>
          <div class="mt-1 text-2xl font-semibold text-gray-900">
            {{ $fmtLiters($totAdj ?? 0) }} <span class="text-sm text-gray-500">L</span>
          </div>
          <div class="text-[11px] text-gray-400 mt-1">
            @if ($from || $to)
              From <span class="text-gray-600">{{ $from ? \Carbon\Carbon::parse($from)->format('d M Y') : '—' }}</span>
              to <span class="text-gray-600">{{ $to ? \Carbon\Carbon::parse($to)->format('d M Y') : now()->format('d M Y') }}</span>
            @else
              To date
            @endif
          </div>
        </div>

      </section>

      {{-- ===== Tables ===== --}}
      <section class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        {{-- Offloads (IN) --}}
        <article class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
          <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-white">
            <div class="text-[12px] font-semibold tracking-wide text-gray-700">Offloads (IN)</div>
            <button type="button"
                    class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline"
                    data-open-movements data-kind="offloads">
              See all
            </button>
          </div>

          {{-- small screens: list --}}
          <div class="sm:hidden divide-y divide-gray-100">
            @forelse($incoming as $tr)
              <div class="px-4 py-3">
                <div class="flex justify-between text-[12px] text-gray-500">
                  <span>{{ optional($tr->date)->format('d M Y') ?: '—' }}</span>
                  <span>T#{{ $tr->tank_id }} • {{ $tr->tank->product->name ?? '' }}</span>
                </div>
                <div class="mt-1 flex justify-between">
                  <div class="text-gray-700">
                    Obs: <span class="font-medium">{{ $fmtLiters($tr->delivered_observed_l) }}</span> L
                    • @20: <span class="font-medium">{{ $fmtLiters($tr->delivered_20_l) }}</span> L
                  </div>
                  <div class="text-rose-600">Short: {{ $fmtLiters($tr->shortfall_20_l) }}</div>
                </div>
                <div class="text-[12px] text-gray-500 mt-0.5">
                  Plates: {{ trim(($tr->truck_plate ? $tr->truck_plate.' ' : '').($tr->trailer_plate ?? '')) ?: '—' }}
                </div>
              </div>
            @empty
              <div class="px-4 py-6 text-center text-gray-500">No offloads{{ $hasFilters ? ' for this range.' : '.' }}</div>
            @endforelse
          </div>

          {{-- md+ screens: table --}}
          <div class="overflow-x-auto hidden sm:block">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 z-10 bg-gradient-to-b from-gray-50 to-white backdrop-blur-sm border-b border-gray-200">
                <tr>
                  <th class="px-3 py-2 text-[10px] font-semibold text-gray-600 text-left uppercase">Date</th>
                  <th class="px-3 py-2 text-[10px] font-semibold text-gray-600 text-left uppercase">Tank / Product</th>
                  <th class="px-3 py-2 text-[10px] font-semibold text-gray-600 text-right uppercase whitespace-nowrap">Observed (L)</th>
                  <th class="px-3 py-2 text-[10px] font-semibold text-gray-600 text-right uppercase whitespace-nowrap">@20°C (L)</th>
                  <th class="px-3 py-2 text-[10px] font-semibold text-gray-600 text-right uppercase">Short</th>
                  <th class="px-3 py-2 text-[10px] font-semibold text-gray-600 text-left uppercase">Plates</th>
                </tr>
              </thead>
              <tbody id="recentInBody" class="divide-y divide-gray-100">
                @forelse($incoming as $tr)
                  @include('depot-stock::clients.forms._row_in', ['tr' => $tr])
                @empty
                  <tr>
                    <td colspan="9" class="px-3 py-6 text-center text-gray-500">
                      @if ($from || $to)
                        No offloads from
                        <span class="text-gray-700">{{ $from ? \Carbon\Carbon::parse($from)->format('d M Y') : '—' }}</span>
                        to
                        <span class="text-gray-700">{{ $to ? \Carbon\Carbon::parse($to)->format('d M Y') : now()->format('d M Y') }}</span>
                      @else
                        To date
                      @endif
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="px-4 py-3 flex items-center justify-end">
            @if($incoming instanceof \Illuminate\Pagination\Paginator || $incoming instanceof \Illuminate\Pagination\LengthAwarePaginator)
              {{ $incoming->withQueryString()->links() }}
            @elseif(Route::has('depot.clients.offloads.index'))
              <a href="{{ route('depot.clients.offloads.index', ['client' => $client->id]) }}"
                 class="text-xs text-gray-600 hover:text-gray-800">View all</a>
            @endif
          </div>
        </article>

        {{-- Loads (OUT) --}}
        <article class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
          <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-white">
            <div class="text-[12px] font-semibold tracking-wide text-gray-700">Loads (OUT)</div>
            <button type="button"
                    class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline"
                    data-open-movements data-kind="loads">
              See all
            </button>
          </div>

          {{-- small screens: list --}}
          <div class="sm:hidden divide-y divide-gray-100">
            @forelse($outgoing as $tr)
              <div class="px-4 py-3">
                <div class="flex justify-between text-[12px] text-gray-500">
                  <span>{{ optional($tr->date)->format('d M Y') ?: '—' }}</span>
                  <span>T#{{ $tr->tank_id }} • {{ $tr->tank->product->name ?? '' }}</span>
                </div>
                <div class="mt-1 flex justify-between">
                  <div class="text-gray-700">
                    Loaded @20: <span class="font-medium">{{ $fmtLiters($tr->loaded_20_l) }}</span> L
                  </div>
                  <div class="text-gray-500">
                    {{ $fmtLiters($tr->temperature_c) }}°C • ρ {{ $fmtLiters($tr->density_kg_l) }}
                  </div>
                </div>
                <div class="text-[12px] text-gray-500 mt-0.5">
                  Plates: {{ trim(($tr->truck_plate ? $tr->truck_plate.' ' : '').($tr->trailer_plate ?? '')) ?: '—' }}
                </div>
              </div>
            @empty
              <div class="px-4 py-6 text-center text-gray-500">No loads{{ $hasFilters ? ' for this range.' : '.' }}</div>
            @endforelse
          </div>

          {{-- md+ screens: table --}}
          <div class="overflow-x-auto hidden sm:block">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 z-10 bg-gradient-to-b from-gray-50 to-white backdrop-blur-sm border-b border-gray-200">
                <tr>
                  <th class="px-3 py-2 text-[10px] font-semibold text-gray-600 text-left uppercase whitespace-nowrap">Date</th>
                  <th class="px-3 py-2 text-[10px] font-semibold text-gray-600 text-left uppercase whitespace-nowrap">Tank / Product</th>
                  <th class="px-3 py-2 text-[10px] font-semibold text-gray-600 text-right uppercase whitespace-nowrap">Loaded @20°C (L)</th>
                  <th class="px-3 py-2 text-[10px] font-semibold text-gray-600 text-right uppercase whitespace-nowrap">Temp (°C)</th>
                  <th class="px-3 py-2 text-[10px] font-semibold text-gray-600 text-right uppercase whitespace-nowrap">Density</th>
                  <th class="px-3 py-2 text-[10px] font-semibold text-gray-600 text-left uppercase whitespace-nowrap">Plates</th>
                </tr>
              </thead>
              <tbody id="recentOutBody" class="divide-y divide-gray-100">
                @forelse($outgoing as $tr)
                  @include('depot-stock::clients.forms._row_out', ['tr' => $tr])
                @empty
                  <tr>
                    <td colspan="8" class="px-3 py-6 text-center text-gray-500">
                      @if ($from || $to)
                        No loads from
                        <span class="text-gray-700">{{ $from ? \Carbon\Carbon::parse($from)->format('d M Y') : '—' }}</span>
                        to
                        <span class="text-gray-700">{{ $to ? \Carbon\Carbon::parse($to)->format('d M Y') : now()->format('d M Y') }}</span>
                      @else
                        To date
                      @endif
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="px-4 py-3 flex items-center justify-end">
            @if($outgoing instanceof \Illuminate\Pagination\Paginator || $outgoing instanceof \Illuminate\Pagination\LengthAwarePaginator)
              {{ $outgoing->withQueryString()->links() }}
            @elseif(Route::has('depot.clients.loads.index'))
              <a href="{{ route('depot.clients.loads.index', ['client' => $client->id]) }}"
                 class="text-xs text-gray-600 hover:text-gray-800">View all</a>
            @endif
          </div>
        </article>
      </section>

      {{-- Adjustments (optional block kept commented) --}}
      {{-- … --}}
    </main>
  </div>
</div>

{{-- Modals (hidden by default) --}}
@include('depot-stock::clients.forms.offload', ['client' => $client, 'tanks' => $tanks])
@include('depot-stock::clients.forms.load',    ['client' => $client, 'tanks' => $tanks])
@include('depot-stock::clients.forms.adjust',  ['client' => $client, 'tanks' => $tanks])
@include('depot-stock::clients.forms.lossModal')
@include('depot-stock::clients.forms.movementsGridModal')
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Drawer
  const drawer   = document.getElementById('sideDrawer');
  const backdrop = document.getElementById('drawerBackdrop') || (() => {
    const d = document.createElement('div');
    d.id = 'drawerBackdrop';
    d.className = 'fixed inset-0 z-30 bg-black/40 hidden md:hidden';
    document.body.appendChild(d);
    return d;
  })();

  const openDrawer  = () => { drawer?.classList.remove('-translate-x-full'); backdrop.classList.remove('hidden'); };
  const closeDrawer = () => { drawer?.classList.add('-translate-x-full');    backdrop.classList.add('hidden');    };

  document.getElementById('btnOpenDrawer')?.addEventListener('click', openDrawer);
  backdrop.addEventListener('click', closeDrawer);

  // Open / Close modals by data-* hooks
  const openModal  = id => document.getElementById(id)?.classList.remove('hidden');
  const closeModal = id => document.getElementById(id)?.classList.add('hidden');

  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-open-offload]')) { openModal('offloadModal'); closeDrawer(); }
    if (e.target.closest('[data-open-load]'))    { openModal('loadModal');    closeDrawer(); }
    if (e.target.closest('[data-open-adjust]'))  { openModal('adjustModal');  closeDrawer(); }
    if (e.target.closest('[data-open-loss]'))    { openModal('lossModal');    closeDrawer(); }

    if (e.target.closest('[data-offload-close]')) closeModal('offloadModal');
    if (e.target.closest('[data-load-close]'))    closeModal('loadModal');
    if (e.target.closest('[data-adjust-close]'))  closeModal('adjustModal');
    if (e.target.closest('[data-loss-close]'))    closeModal('lossModal');
  });
});
</script>
@endpush