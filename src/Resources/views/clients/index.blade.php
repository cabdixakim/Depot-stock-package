@extends('depot-stock::layouts.app')

@section('title', 'Clients')

@section('content')
@php
    $auth = auth()->user();
    $roleNames = $auth?->roles?->pluck('name')->map(fn($r)=>strtolower($r))->all() ?? [];
    $isAdmin     = in_array('admin', $roleNames) || in_array('owner', $roleNames) || in_array('superadmin', $roleNames);
    $isAccount   = in_array('accountant', $roleNames) || in_array('accounts', $roleNames) || in_array('accounting', $roleNames);
    $canLock     = $isAdmin || $isAccount;
    $canManage   = $isAdmin || $isAccount;

    $fmt = fn($v) => number_format((float)$v, 2, '.', ',');
@endphp

<div class="min-h-[100dvh] bg-[#F7FAFC]">

  {{-- ===== Sticky Header ===== --}}
  <div class="sticky top-0 z-20 bg-white/90 backdrop-blur border-b border-slate-100">
    <div class="mx-auto max-w-7xl px-4 md:px-6 h-14 flex items-center justify-between gap-3">
      <div class="leading-tight">
        <div class="text-[11px] uppercase tracking-wide text-slate-500">
          Clients
        </div>
        <div class="font-semibold text-slate-900">
          Storage &amp; entitlement overview
        </div>
      </div>

      <div class="flex items-center gap-2">
        <form method="GET" class="flex items-center gap-2">
          <div class="relative">
            <input
              type="text"
              name="q"
              value="{{ $q ?? '' }}"
              placeholder="Search client, code, email…"
              class="h-9 w-48 sm:w-72 rounded-xl border border-slate-200 bg-slate-50 px-8 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-0">
            <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="11" cy="11" r="6" stroke-width="1.7"/>
                <path d="M16 16l4 4" stroke-width="1.7" stroke-linecap="round"/>
              </svg>
            </span>
          </div>
          <a href="{{ route('depot.clients.index') }}"
             class="hidden sm:inline-flex text-[11px] text-slate-500 hover:text-slate-700">
            Reset
          </a>
        </form>

        @if($canManage)
          <button
            type="button"
            id="clientCreateOpen"
            class="inline-flex items-center gap-1 rounded-xl bg-slate-900 text-white px-3 py-1.5 text-[11px] font-medium hover:bg-black shadow-sm">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path d="M12 5v14M5 12h14" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            New client
          </button>
        @endif
      </div>
    </div>
  </div>

  {{-- ===== Body ===== --}}
  <div class="mx-auto max-w-7xl px-4 md:px-6 py-6 space-y-5">

    {{-- Info banner --}}
    <div class="rounded-2xl bg-slate-900 text-slate-100 px-4 py-3 flex flex-wrap items-center justify-between gap-3 shadow-sm">
      <div class="flex items-center gap-2">
        <div class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-300">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M4 12a8 8 0 0 1 16 0" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M9 12l3 3 3-3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M12 15v5" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="text-xs md:text-[13px]">
          See how much of each client’s <span class="font-semibold">physical stock</span> is actually
          <span class="font-semibold">cleared &amp; ready to load</span>, and lock loading/offloading when needed.
        </div>
      </div>
      <div class="text-[11px] text-slate-300 text-right">
        @if($canLock)
          Admin / Accounts can halt <span class="font-semibold">loads</span> or <span class="font-semibold">offloads</span> per client.
        @else
          Some clients may be blocked – contact Accounts or Admin if action is required.
        @endif
      </div>
    </div>

    {{-- Clients grid --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
      @forelse($clients as $client)
        @php
          /** @var \Optima\DepotStock\Services\ClientRiskSnapshot|null $risk */
          $risk = $riskSnapshots[$client->id] ?? null;

          $physical    = $risk?->physicalStock    ?? 0;
          $cleared     = $risk?->clearedStock     ?? 0;
          $available   = $risk?->availableToLoad  ?? 0;
          $uncleared   = $risk?->unclearedStock   ?? 0;
          $idle        = $risk?->clearedIdleStock ?? 0; // shown as "Idle stock" in UI
          $status      = $risk?->status ?? 'ok';
          $flags       = $risk?->flags  ?? [];
          $blockedLoads    = !$client->can_load;
          $blockedOffloads = !$client->can_offload;

          // ==== last payment info (if relation exists) ====
          $lastPaymentDate   = null;
          $daysSincePayment  = null;
          if (method_exists($client, 'payments')) {
              $lastRaw = $client->payments()->latest('date')->value('date')
                       ?? $client->payments()->latest('created_at')->value('created_at');
              if ($lastRaw) {
                  $c = \Carbon\Carbon::parse($lastRaw);
                  $lastPaymentDate  = $c->format('d M Y');
                  $daysSincePayment = $c->diffInDays(now());
              }
          }

          // ==== Status styling ====
          $cardBorder  = [
            'ok'       => 'border-emerald-100',
            'warn'     => 'border-amber-200',
            'critical' => 'border-rose-200',
          ][$status] ?? 'border-slate-200';

          $cardShadow  = [
            'ok'       => 'shadow-sm',
            'warn'     => 'shadow-[0_0_0_1px_rgba(251,191,36,0.35)]',
            'critical' => 'shadow-[0_0_0_1px_rgba(239,68,68,0.40)]',
          ][$status] ?? 'shadow-sm';

          $statusLabel = [
            'ok'       => 'In good standing',
            'warn'     => 'Watchlist',
            'critical' => 'Attention needed',
          ][$status] ?? 'In good standing';

          $statusPill  = [
            'ok'       => 'bg-emerald-50 text-emerald-700 border border-emerald-100',
            'warn'     => 'bg-amber-50 text-amber-800 border border-amber-100',
            'critical' => 'bg-rose-50 text-rose-700 border border-rose-100',
          ][$status] ?? 'bg-slate-50 text-slate-700 border border-slate-200';

          // ==== Human-friendly recommendation text ====
          $whyShort = null;
          $whyLong  = null;
          $reasons  = [];

          // 1) Idle storage
          if (in_array('storage_congestion', $flags, true) && $idle > 0) {
              $reasons[] = 'Client is leaving a big block of stock sitting idle in our depot beyond the agreed storage window.';
          }

          // 2) Big uncleared & no recent payment
          if (in_array('no_entitlement_uncleared_stock', $flags, true)) {
              if ($uncleared > 200000 && $daysSincePayment !== null && $daysSincePayment >= 10) {
                  $reasons[] = 'Client has more than '.number_format($uncleared, 0).' L of uncleared stock and their last payment was '.$daysSincePayment.' days ago.';
              } elseif ($uncleared > 0) {
                  $reasons[] = 'Client is holding physical stock in our depot that has not been cleared yet.';
              }
          }

          // 3) Numbers not matching (over-loading / messy entitlement)
          if (in_array('excess_entitlement_gap', $flags, true)) {
              $reasons[] = 'Client’s cleared litres and physical stock are out of balance – likely over-loading or wrong billing.';
          }

          if (!empty($reasons)) {
              $whyShort = $reasons[0];

              $whyLongPieces = [];

              if ($uncleared > 0) {
                  $whyLongPieces[] = 'Current uncleared stock is about '.number_format($uncleared, 0).' L.';
              }
              if ($lastPaymentDate && $daysSincePayment !== null) {
                  $whyLongPieces[] = 'Last payment received: '.$lastPaymentDate.' ('.$daysSincePayment.' days ago).';
              }

              // Suggested actions in plain depot language
              $whyLongPieces[] = 'Suggested action: pause new offloads/loads for this client and review their invoices, payments and movements. '
                               . 'Clear the account or put a payment plan before opening the tap again.';

              if ($idle > 0) {
                  $whyLongPieces[] = 'They also have old idle stock sitting in our tanks. Consider charging storage or asking them to move it.';
              }

              $whyLong = implode(' ', $whyLongPieces);
          }
        @endphp

        <div class="client-card rounded-2xl bg-white {{ $cardBorder }} {{ $cardShadow }} p-4 flex flex-col gap-3"
             data-client-id="{{ $client->id }}"
             data-client-name="{{ $client->name }}"
             data-client-idle-litres="{{ $idle }}"
             data-client-physical="{{ $physical }}"
             data-client-uncleared="{{ $uncleared }}"
             data-client-code="{{ $client->code }}"
             data-client-email="{{ $client->email }}"
             data-client-phone="{{ $client->phone }}"
             data-client-billing-terms="{{ $client->billing_terms }}"
             data-update-url="{{ route('depot.clients.update', $client) }}">

          {{-- Header row --}}
          <div class="flex items-start justify-between gap-3">
            <div class="space-y-0.5">
              <div class="flex items-center gap-2">
                <div class="h-8 w-8 flex items-center justify-center rounded-xl bg-slate-900 text-white text-xs font-semibold">
                  {{ mb_substr($client->name ?? 'C', 0, 2) }}
                </div>
                <div>
                  <div class="flex items-center gap-1.5">
                    <div class="text-sm font-semibold text-slate-900">
                      {{ $client->name ?? 'Unnamed client' }}
                    </div>
                    @if($canManage)
                      <button
                        type="button"
                        class="client-edit-btn inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] text-slate-500 hover:bg-slate-50"
                        title="Edit client details">
                        ✏
                      </button>
                    @endif
                  </div>
                  @if(!empty($client->code))
                    <div class="text-[11px] text-slate-500">
                      Code: <span class="font-medium">{{ $client->code }}</span>
                    </div>
                  @endif
                  @if($client->email)
                    <div class="text-[11px] text-slate-500">
                      {{ $client->email }}
                    </div>
                  @endif
                </div>
              </div>
            </div>

            <div class="flex flex-col items-end gap-1">
              {{-- Status pill --}}
              <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-medium {{ $statusPill }}">
                @if($status === 'ok')
                  <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                @elseif($status === 'warn')
                  <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                @else
                  <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                @endif
                {{ $statusLabel }}
              </span>

              {{-- Hard lock + policy / storage badges --}}
              <div class="flex flex-wrap justify-end gap-1">
                @if($blockedOffloads && $blockedLoads)
                  <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700 uppercase tracking-wide">
                    🔒 Offload &amp; Load halted
                  </span>
                @elseif($blockedOffloads)
                  <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700 uppercase tracking-wide">
                    🔒 Offloads halted
                  </span>
                @elseif($blockedLoads)
                  <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 uppercase tracking-wide">
                    ⛔ Loads halted
                  </span>
                @endif

                {{-- Storage / idle badge --}}
                @if($idle > 0)
                  <button
                    type="button"
                    class="client-storage-btn inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-800 border border-amber-200"
                    title="Client has idle stock sitting in depot; consider charging storage.">
                    ⚠ Idle stock
                  </button>
                @endif

                {{-- Why? badge if there are risk flags --}}
                @if($whyShort)
                  <button
                    type="button"
                    class="client-why-btn inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-700 hover:bg-slate-200">
                    Why?
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path d="M6 9l6 6 6-6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </button>
                @endif
              </div>
            </div>
          </div>

          {{-- Metrics row --}}
          <div class="rounded-xl border border-slate-100 bg-slate-50/60 px-3 py-2.5 grid grid-cols-2 md:grid-cols-3 gap-2 text-[11px] text-slate-600">
            <div>
              <div class="uppercase tracking-wide text-[10px] text-slate-400">Physical stock</div>
              <div class="text-sm font-semibold text-slate-900">
                {{ $fmt($physical) }} <span class="text-[10px] text-slate-500">L @20°C</span>
              </div>
            </div>
            <div>
              <!-- <div class="uppercase tracking-wide text-[10px] text-slate-400">Cleared</div>
              <div class="text-sm font-semibold text-emerald-700">
                {{ $fmt($cleared) }} <span class="text-[10px] text-slate-500">L</span>
              </div> -->
            </div>
            <div>
              <div class="uppercase tracking-wide text-[10px] text-slate-400">Available to load</div>
              <div class="text-sm font-semibold {{ $available <= 0 ? 'text-rose-700' : 'text-slate-900' }}">
                {{ $fmt($available) }} <span class="text-[10px] text-slate-500">L</span>
              </div>
            </div>
            <div>
              <div class="uppercase tracking-wide text-[10px] text-slate-400">Uncleared</div>
              <div class="text-sm font-semibold {{ $uncleared > 0 ? 'text-amber-700' : 'text-slate-900' }}">
                {{ $fmt($uncleared) }} <span class="text-[10px] text-slate-500">L</span>
              </div>
            </div>
            <div>
              <div class="uppercase tracking-wide text-[10px] text-slate-400">Idle stock</div>
              <div class="text-sm font-semibold {{ $idle > 0 ? 'text-amber-700' : 'text-slate-900' }}">
                {{ $fmt($idle) }} <span class="text-[10px] text-slate-500">L</span>
              </div>
            </div>
            {{-- 6th tile intentionally blank (we removed entitlement gap from UI) --}}
          </div>

          {{-- Policy reasoning panel --}}
          @if($whyShort)
            <div class="client-why-panel mt-1 hidden rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-3 py-2.5 text-[11px] text-slate-600">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <div class="font-semibold text-[11px] text-slate-800 mb-0.5">
                    System recommendation
                  </div>
                  <div class="mb-1">
                    {{ $whyShort }}
                  </div>
                  @if($whyLong)
                    <div class="text-[11px] text-slate-500">
                      {{ $whyLong }}
                    </div>
                  @endif
                </div>
                <button type="button" class="client-why-close text-slate-400 hover:text-slate-600">
                  ✕
                </button>
              </div>
            </div>
          @endif

          {{-- Controls row --}}
          <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-slate-100 mt-1">
            <a href="{{ route('depot.clients.show', $client) }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[11px] text-slate-700 hover:bg-slate-50">
              <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M5 12h14M12 5l7 7-7 7" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              Open client
            </a>

            @if($canLock)
              <div class="ml-auto flex flex-wrap items-center gap-1.5">
                {{-- Toggle loads --}}
                <button
                  type="button"
                  class="client-lock-btn inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[11px] font-medium border
                    {{ $blockedLoads
                        ? 'border-amber-300 bg-amber-50 text-amber-800'
                        : 'border-emerald-300 bg-emerald-50 text-emerald-800' }}"
                  data-client-id="{{ $client->id }}"
                  data-kind="load"
                  data-current="{{ $blockedLoads ? '0' : '1' }}">
                  @if($blockedLoads)
                    ⛔ Loads halted
                  @else
                    ✅ Loads allowed
                  @endif
                </button>

                {{-- Toggle offloads --}}
                <button
                  type="button"
                  class="client-lock-btn inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[11px] font-medium border
                    {{ $blockedOffloads
                        ? 'border-rose-300 bg-rose-50 text-rose-700'
                        : 'border-sky-300 bg-sky-50 text-sky-800' }}"
                  data-client-id="{{ $client->id }}"
                  data-kind="offload"
                  data-current="{{ $blockedOffloads ? '0' : '1' }}">
                  @if($blockedOffloads)
                    🔒 Offloads halted
                  @else
                    🚚 Offloads allowed
                  @endif
                </button>
              </div>
            @endif
          </div>

        </div>
      @empty
        <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
          No clients found.
        </div>
      @endforelse
    </div>

    <div>
      {{ $clients->links() }}
    </div>
  </div>
</div>

{{-- ===== Storage charge modal ===== --}}
<div id="storageChargeModal" class="fixed inset-0 z-40 hidden items-center justify-center">
  <div class="absolute inset-0 bg-black/40"></div>
  <div class="relative z-50 w-full max-w-md rounded-2xl bg-white shadow-xl border border-slate-200 p-4 sm:p-5">
    <div class="flex items-start justify-between gap-3 mb-2">
      <div>
        <div class="text-[10px] font-semibold uppercase tracking-wide text-amber-600">
          Storage recommendation
        </div>
        <div class="text-sm font-semibold text-slate-900" id="storageClientName">
          Client
        </div>
      </div>
      <button type="button" class="storage-close text-slate-400 hover:text-slate-600 text-sm">✕</button>
    </div>

    <div class="text-[13px] text-slate-700 space-y-2 mb-3">
      <p>
        This client has stock sitting in our depot beyond the agreed storage window.
        It’s occupying space and should either be loaded or billed as storage.
      </p>
      <p class="text-slate-600">
        <span class="font-medium">Idle stock:</span>
        <span id="storageIdleLitres">0</span> L
        &nbsp;•&nbsp;
        <span class="font-medium">Physical in depot:</span>
        <span id="storagePhysicalLitres">0</span> L
      </p>
    </div>

    <div class="space-y-2 mb-3 text-[12px] text-slate-700">
      <div class="grid grid-cols-2 gap-2">
        <label class="space-y-1">
          <span class="block text-[11px] uppercase tracking-wide text-slate-500">Litres to charge</span>
          <input
            type="number"
            step="0.001"
            min="0"
            id="storageIdleInput"
            class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[12px] focus:outline-none focus:ring-0 focus:border-slate-400">
        </label>
        <label class="space-y-1">
          <span class="block text-[11px] uppercase tracking-wide text-slate-500">Months</span>
          <input
            type="number"
            step="1"
            min="1"
            id="storageMonthsInput"
            value="1"
            class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[12px] focus:outline-none focus:ring-0 focus:border-slate-400">
        </label>
      </div>

      <div class="grid grid-cols-2 gap-2">
        <label class="space-y-1 col-span-2">
          <span class="block text-[11px] uppercase tracking-wide text-slate-500">Rate per 1,000 L per month (e.g. 10 = $10 / 1,000 L)</span>
          <input
            type="number"
            step="0.01"
            min="0"
            id="storageRateInput"
            value="10"
            class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[12px] focus:outline-none focus:ring-0 focus:border-slate-400">
        </label>
      </div>

      <div class="mt-1 text-[12px] text-slate-600">
        Estimated fee:
        <span class="font-semibold" id="storageTotalAmount">$0.00</span>
      </div>
    </div>

    <div class="flex flex-wrap justify-end gap-1.5 mt-2">
      <button
        type="button"
        class="inline-flex items-center gap-1 rounded-lg bg-amber-600 px-2.5 py-1.5 text-[11px] font-medium text-white hover:bg-amber-700"
        id="storageChargeBtn">
        Charge storage
      </button>
      <button
        type="button"
        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-medium text-slate-700 hover:bg-slate-50"
        id="storageExtendBtn">
        Extend grace
      </button>
    </div>
  </div>
</div>

{{-- ===== Create Client modal ===== --}}
@if($canManage)
<div id="clientCreateModal" class="fixed inset-0 z-40 hidden items-center justify-center">
  <div class="absolute inset-0 bg-black/40"></div>
  <div class="relative z-50 w-full max-w-md rounded-2xl bg-white shadow-xl border border-slate-200 p-4 sm:p-5">
    <div class="flex items-start justify-between gap-3 mb-3">
      <div>
        <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
          New client
        </div>
        <div class="text-sm font-semibold text-slate-900">
          Add storage client
        </div>
      </div>
      <button type="button" class="client-create-close text-slate-400 hover:text-slate-600 text-sm">✕</button>
    </div>

    <form method="POST" action="{{ route('depot.clients.store') }}" class="space-y-3 text-[13px]">
      @csrf
      <div class="grid grid-cols-2 gap-2">
        <label class="space-y-1 col-span-1">
          <span class="block text-[11px] uppercase tracking-wide text-slate-500">Code</span>
          <input name="code" required
                 class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[13px] focus:outline-none focus:ring-0 focus:border-slate-400">
        </label>
        <label class="space-y-1 col-span-1">
          <span class="block text-[11px] uppercase tracking-wide text-slate-500">Name</span>
          <input name="name" required
                 class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[13px] focus:outline-none focus:ring-0 focus:border-slate-400">
        </label>
      </div>
      <label class="space-y-1">
        <span class="block text-[11px] uppercase tracking-wide text-slate-500">Email (portal login)</span>
        <input name="email" type="email"
               class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[13px] focus:outline-none focus:ring-0 focus:border-slate-400">
      </label>
      <label class="space-y-1">
        <span class="block text-[11px] uppercase tracking-wide text-slate-500">Phone</span>
        <input name="phone"
               class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[13px] focus:outline-none focus:ring-0 focus:border-slate-400">
      </label>
      <label class="space-y-1">
        <span class="block text-[11px] uppercase tracking-wide text-slate-500">Billing terms (optional)</span>
        <input name="billing_terms"
               class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[13px] focus:outline-none focus:ring-0 focus:border-slate-400"
               placeholder="e.g. 7 days after invoice">
      </label>

      <div class="mt-3 flex justify-end gap-2">
        <button type="button"
                class="client-create-close inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[12px] text-slate-700 hover:bg-slate-50">
          Cancel
        </button>
        <button type="submit"
                class="inline-flex items-center rounded-lg bg-slate-900 px-3 py-1.5 text-[12px] font-medium text-white hover:bg-black">
          Save client
        </button>
      </div>
    </form>
  </div>
</div>

{{-- ===== Edit Client modal ===== --}}
<div id="clientEditModal" class="fixed inset-0 z-40 hidden items-center justify-center">
  <div class="absolute inset-0 bg-black/40"></div>
  <div class="relative z-50 w-full max-w-md rounded-2xl bg-white shadow-xl border border-slate-200 p-4 sm:p-5">
    <div class="flex items-start justify-between gap-3 mb-3">
      <div>
        <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
          Edit client
        </div>
        <div class="text-sm font-semibold text-slate-900" id="clientEditTitle">
          Client
        </div>
      </div>
      <button type="button" class="client-edit-close text-slate-400 hover:text-slate-600 text-sm">✕</button>
    </div>

    <form method="POST" id="clientEditForm" class="space-y-3 text-[13px]">
      @csrf
      @method('PATCH')
      <div class="grid grid-cols-2 gap-2">
        <label class="space-y-1 col-span-1">
          <span class="block text-[11px] uppercase tracking-wide text-slate-500">Code</span>
          <input name="code" id="editCode" required
                 class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[13px] focus:outline-none focus:ring-0 focus:border-slate-400">
        </label>
        <label class="space-y-1 col-span-1">
          <span class="block text-[11px] uppercase tracking-wide text-slate-500">Name</span>
          <input name="name" id="editName" required
                 class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[13px] focus:outline-none focus:ring-0 focus:border-slate-400">
        </label>
      </div>
      <label class="space-y-1">
        <span class="block text-[11px] uppercase tracking-wide text-slate-500">Email</span>
        <input name="email" id="editEmail" type="email"
               class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[13px] focus:outline-none focus:ring-0 focus:border-slate-400">
      </label>
      <label class="space-y-1">
        <span class="block text-[11px] uppercase tracking-wide text-slate-500">Phone</span>
        <input name="phone" id="editPhone"
               class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[13px] focus:outline-none focus:ring-0 focus:border-slate-400">
      </label>
      <label class="space-y-1">
        <span class="block text-[11px] uppercase tracking-wide text-slate-500">Billing terms</span>
        <input name="billing_terms" id="editBillingTerms"
               class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[13px] focus:outline-none focus:ring-0 focus:border-slate-400">
      </label>

      <div class="mt-3 flex justify-end gap-2">
        <button type="button"
                class="client-edit-close inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[12px] text-slate-700 hover:bg-slate-50">
          Cancel
        </button>
        <button type="submit"
                class="inline-flex items-center rounded-lg bg-slate-900 px-3 py-1.5 text-[12px] font-medium text-white hover:bg-black">
          Save changes
        </button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function(){
  // Expand / collapse "Why?" policy panel
  document.querySelectorAll('.client-card').forEach(card => {
    const btn   = card.querySelector('.client-why-btn');
    const panel = card.querySelector('.client-why-panel');
    const close = card.querySelector('.client-why-close');

    if (btn && panel) {
      btn.addEventListener('click', () => {
        panel.classList.toggle('hidden');
      });
    }
    if (close && panel) {
      close.addEventListener('click', () => {
        panel.classList.add('hidden');
      });
    }
  });

  // Lock toggles (Admin / Accounts only)
  const token = document.querySelector('meta[name="csrf-token"]')?.content;

  async function postLock(clientId, payload) {
    const url = "{{ route('depot.clients.lock', '__CLIENT__') }}".replace('__CLIENT__', clientId);

    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': token,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload)
    });

    if (!res.ok) {
      const text = await res.text();
      throw new Error(text || 'Failed to update lock.');
    }
    return await res.json().catch(() => ({}));
  }

  document.querySelectorAll('.client-lock-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const clientId = btn.getAttribute('data-client-id');
      const kind     = btn.getAttribute('data-kind'); // 'load'|'offload'
      const current  = btn.getAttribute('data-current') === '1'; // true means currently allowed

      const title = current
        ? (kind === 'load' ? 'Halt loads for this client?' : 'Halt offloads for this client?')
        : (kind === 'load' ? 'Allow loads for this client?' : 'Allow offloads for this client?');

      const detail = current
        ? 'Operators will be blocked from performing this action until you re-enable it.'
        : 'Operators will be able to perform this action immediately.';

      const ok = await window.askConfirm?.({
        heading: title,
        message: detail,
        okText: current ? 'Yes, halt' : 'Yes, allow',
        cancelText: 'Cancel',
      }) ?? confirm(title + '\n' + detail);

      if (!ok) return;

      try {
        const payload = {};
        if (kind === 'load') {
          payload.can_load = !current;
        } else {
          payload.can_offload = !current;
        }

        await postLock(clientId, payload);
        location.reload();
      } catch (e) {
        console.error(e);
        window.toast?.('Failed to update lock', false);
      }
    });
  });

  // ===== Storage charge modal logic =====
  const storageModal       = document.getElementById('storageChargeModal');
  const storageCloseBtns   = storageModal ? storageModal.querySelectorAll('.storage-close') : [];
  const storageClientName  = storageModal ? storageModal.querySelector('#storageClientName') : null;
  const storageIdleLitres  = storageModal ? storageModal.querySelector('#storageIdleLitres') : null;
  const storagePhysicalLitres = storageModal ? storageModal.querySelector('#storagePhysicalLitres') : null;
  const storageIdleInput   = storageModal ? storageModal.querySelector('#storageIdleInput') : null;
  const storageRateInput   = storageModal ? storageModal.querySelector('#storageRateInput') : null;
  const storageMonthsInput = storageModal ? storageModal.querySelector('#storageMonthsInput') : null;
  const storageTotalAmount = storageModal ? storageModal.querySelector('#storageTotalAmount') : null;
  const storageChargeBtn   = storageModal ? storageModal.querySelector('#storageChargeBtn') : null;
  const storageExtendBtn   = storageModal ? storageModal.querySelector('#storageExtendBtn') : null;

  const storageChargeUrlTemplate = "{{ route('depot.clients.storage.charge', '__CLIENT__') }}";
  const storageExtendUrlTemplate = "{{ route('depot.clients.storage.extend', '__CLIENT__') }}";

  function formatMoney(amount) {
    const n = Number(amount) || 0;
    return '$' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function recalcStorageTotal() {
    if (!storageIdleInput || !storageRateInput || !storageMonthsInput || !storageTotalAmount) return;
    const litres = Number(storageIdleInput.value || '0');
    const rate   = Number(storageRateInput.value || '0'); // per 1000 L per month
    const months = Number(storageMonthsInput.value || '0') || 1;

    const total = (litres / 1000) * rate * months;
    storageTotalAmount.textContent = formatMoney(total);
  }

  function openStorageModal(card) {
    if (!storageModal) return;
    const name     = card.getAttribute('data-client-name') || 'Client';
    const idle     = Number(card.getAttribute('data-client-idle-litres') || '0');
    const physical = Number(card.getAttribute('data-client-physical') || '0');
    const clientId = card.getAttribute('data-client-id');

    storageModal.dataset.clientId = clientId;

    if (storageClientName)      storageClientName.textContent = name;
    if (storageIdleLitres)      storageIdleLitres.textContent = idle.toLocaleString();
    if (storagePhysicalLitres)  storagePhysicalLitres.textContent = physical.toLocaleString();
    if (storageIdleInput)       storageIdleInput.value = idle > 0 ? idle : 0;
    if (storageMonthsInput && !storageMonthsInput.value) storageMonthsInput.value = 1;
    if (storageRateInput && !storageRateInput.value) storageRateInput.value = 10;

    recalcStorageTotal();

    storageModal.classList.remove('hidden');
    storageModal.classList.add('flex');
  }

  function closeStorageModal() {
    if (!storageModal) return;
    storageModal.classList.add('hidden');
    storageModal.classList.remove('flex');
  }

  document.querySelectorAll('.client-storage-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const card = btn.closest('.client-card');
      if (card) openStorageModal(card);
    });
  });

  storageCloseBtns.forEach(btn => btn.addEventListener('click', closeStorageModal));
  if (storageModal) {
    storageModal.addEventListener('click', (e) => {
      if (e.target === storageModal) closeStorageModal();
    });
  }

  if (storageIdleInput)   storageIdleInput.addEventListener('input', recalcStorageTotal);
  if (storageRateInput)   storageRateInput.addEventListener('input', recalcStorageTotal);
  if (storageMonthsInput) storageMonthsInput.addEventListener('input', recalcStorageTotal);

  async function postJson(url, payload) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': token,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    });
    if (!res.ok) {
      const txt = await res.text();
      throw new Error(txt || 'Request failed');
    }
    return await res.json().catch(() => ({}));
  }

  if (storageChargeBtn) {
    storageChargeBtn.addEventListener('click', async () => {
      if (!storageModal) return;
      const clientId = storageModal.dataset.clientId;
      const litres   = Number(storageIdleInput?.value || '0');
      const rate     = Number(storageRateInput?.value || '0');
      const months   = Number(storageMonthsInput?.value || '0') || 1;
      const expected = (litres / 1000) * rate * months;

      const url = storageChargeUrlTemplate.replace('__CLIENT__', clientId);

      try {
        const data = await postJson(url, {
          idle_litres: litres,
          rate_per_1000: rate,
          months: months,
          expected_amount: expected,
        });
        window.toast?.(data.message || 'Storage charge recorded.', true);
        closeStorageModal();
        // location.reload(); // enable if you want to refresh UI immediately
      } catch (e) {
        console.error(e);
        window.toast?.('Failed to record storage charge.', false);
      }
    });
  }

  if (storageExtendBtn) {
    storageExtendBtn.addEventListener('click', async () => {
      if (!storageModal) return;
      const clientId = storageModal.dataset.clientId;
      const url      = storageExtendUrlTemplate.replace('__CLIENT__', clientId);

      try {
        const data = await postJson(url, {});
        window.toast?.(data.message || 'Grace period extended for this client.', true);
        closeStorageModal();
      } catch (e) {
        console.error(e);
        window.toast?.('Failed to update storage grace period.', false);
      }
    });
  }

  // ===== New Client modal =====
  const clientCreateModal = document.getElementById('clientCreateModal');
  const clientCreateOpen  = document.getElementById('clientCreateOpen');
  const clientCreateCloses = clientCreateModal ? clientCreateModal.querySelectorAll('.client-create-close') : [];

  function openClientCreate() {
    if (!clientCreateModal) return;
    clientCreateModal.classList.remove('hidden');
    clientCreateModal.classList.add('flex');
  }
  function closeClientCreate() {
    if (!clientCreateModal) return;
    clientCreateModal.classList.add('hidden');
    clientCreateModal.classList.remove('flex');
  }

  if (clientCreateOpen) clientCreateOpen.addEventListener('click', openClientCreate);
  clientCreateCloses.forEach(btn => btn.addEventListener('click', closeClientCreate));
  if (clientCreateModal) {
    clientCreateModal.addEventListener('click', (e) => {
      if (e.target === clientCreateModal) closeClientCreate();
    });
  }

  // ===== Edit Client modal =====
  const clientEditModal  = document.getElementById('clientEditModal');
  const clientEditForm   = document.getElementById('clientEditForm');
  const clientEditTitle  = document.getElementById('clientEditTitle');
  const clientEditCloses = clientEditModal ? clientEditModal.querySelectorAll('.client-edit-close') : [];
  const editCode         = document.getElementById('editCode');
  const editName         = document.getElementById('editName');
  const editEmail        = document.getElementById('editEmail');
  const editPhone        = document.getElementById('editPhone');
  const editBillingTerms = document.getElementById('editBillingTerms');

  function openClientEdit(card) {
    if (!clientEditModal || !clientEditForm) return;
    const name   = card.getAttribute('data-client-name') || 'Client';
    const code   = card.getAttribute('data-client-code') || '';
    const email  = card.getAttribute('data-client-email') || '';
    const phone  = card.getAttribute('data-client-phone') || '';
    const terms  = card.getAttribute('data-client-billing-terms') || '';
    const url    = card.getAttribute('data-update-url');

    if (clientEditTitle) clientEditTitle.textContent = name;
    if (editCode)        editCode.value = code;
    if (editName)        editName.value = name;
    if (editEmail)       editEmail.value = email;
    if (editPhone)       editPhone.value = phone;
    if (editBillingTerms) editBillingTerms.value = terms;

    clientEditForm.action = url;

    clientEditModal.classList.remove('hidden');
    clientEditModal.classList.add('flex');
  }

  function closeClientEdit() {
    if (!clientEditModal) return;
    clientEditModal.classList.add('hidden');
    clientEditModal.classList.remove('flex');
  }

  document.querySelectorAll('.client-edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const card = btn.closest('.client-card');
      if (card) openClientEdit(card);
    });
  });

  clientEditCloses.forEach(btn => btn.addEventListener('click', closeClientEdit));
  if (clientEditModal) {
    clientEditModal.addEventListener('click', (e) => {
      if (e.target === clientEditModal) closeClientEdit();
    });
  }

})();
</script>
@endpush