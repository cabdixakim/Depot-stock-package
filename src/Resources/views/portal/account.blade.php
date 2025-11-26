{{-- resources/views/depot-stock/portal/account.blade.php --}}
@extends('depot-stock::layouts.portal')

@section('title', 'Account & Security — ' . ($client->name ?? 'Client'))

@section('content')
@php
  $currency = config('depot-stock.currency','USD');
  $user = auth()->user();
@endphp

<div class="min-h-[100dvh] bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-slate-100">
  <div class="mx-auto max-w-5xl px-4 py-7 md:py-10 space-y-6">

    {{-- Header --}}
    <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <div class="text-[11px] font-semibold uppercase tracking-[0.25em] text-slate-400">
          Account &amp; Security
        </div>
        <h1 class="mt-1 text-2xl md:text-3xl font-semibold text-slate-50">
          {{ $client->name ?? 'Client' }}
        </h1>
        <p class="mt-2 text-xs md:text-sm text-slate-400 max-w-xl">
          Review your profile details and keep your login credentials safe.
        </p>
      </div>

      <div class="flex items-center gap-3 text-xs">
        <div class="inline-flex items-center gap-1 rounded-full bg-slate-900/70 border border-slate-700/80 px-2.5 py-1">
          <span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_6px_2px_rgba(16,185,129,0.8)]"></span>
          <span class="text-slate-200">Portal access active</span>
        </div>
      </div>
    </header>

    {{-- Alerts --}}
    @if(session('status'))
      <div class="rounded-2xl border border-emerald-500/40 bg-gradient-to-br from-emerald-900/40 via-emerald-900/20 to-slate-900/60 px-4 py-3 text-sm text-emerald-100 flex items-start gap-2">
        <svg class="mt-0.5 h-4 w-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <circle cx="12" cy="12" r="9" stroke-width="1.7" />
          <path d="M12 8v5M12 16h.01" stroke-width="1.7" stroke-linecap="round" />
        </svg>
        <div>{{ session('status') }}</div>
      </div>
    @endif

    @if($errors->any())
      <div class="rounded-2xl border border-rose-500/40 bg-gradient-to-br from-rose-900/50 via-rose-900/30 to-slate-900/60 px-4 py-3 text-sm text-rose-100">
        <ul class="list-disc list-inside space-y-0.5">
          @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1.3fr)] gap-5">

      {{-- Profile Card --}}
      <section class="rounded-2xl border border-slate-800 bg-slate-950/70 shadow-xl">
        <div class="px-5 py-4 border-b border-slate-800/80 flex items-center justify-between">
          <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-300">
              Profile
            </div>
            <p class="text-[11px] text-slate-400">
              Basic client & login details (read-only in portal).
            </p>
          </div>
          <div class="hidden md:flex items-center gap-1.5 text-[11px] text-slate-400">
            <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
            <span>Managed by your fuel provider</span>
          </div>
        </div>

        <div class="px-5 py-4 space-y-4 text-sm">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-sky-500/70 to-emerald-400/70 border border-sky-300/70 grid place-items-center">
              <span class="text-sm font-semibold text-slate-950">
                {{ strtoupper(mb_substr($client->name,0,1)) }}
              </span>
            </div>
            <div>
              <div class="text-slate-100 font-semibold">
                {{ $client->name }}
              </div>
              <div class="text-[11px] text-slate-400">
                Client ID: <span class="font-mono text-slate-300">{{ $client->id }}</span>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
            <div class="space-y-1">
              <div class="text-[11px] uppercase tracking-wide text-slate-500">Client Code</div>
              <div class="text-slate-100">
                {{ $client->code ?? '—' }}
              </div>
            </div>

            <div class="space-y-1">
              <div class="text-[11px] uppercase tracking-wide text-slate-500">Login Email</div>
              <div class="text-slate-100">
                {{ $user->email }}
              </div>
            </div>

            <div class="space-y-1">
              <div class="text-[11px] uppercase tracking-wide text-slate-500">Contact Phone</div>
              <div class="text-slate-100">
                {{ $client->phone ?? '—' }}
              </div>
            </div>

            <div class="space-y-1">
              <div class="text-[11px] uppercase tracking-wide text-slate-500">Currency</div>
              <div class="text-slate-100">
                {{ $currency }}
              </div>
            </div>
          </div>

          <p class="pt-2 text-[11px] text-slate-500 border-t border-slate-800/70 mt-3">
            Need to change your company details or contact info?
            Please reach out to your Optima / depot support team.
          </p>
        </div>
      </section>

      {{-- Security / Change Password --}}
      <section class="rounded-2xl border border-slate-800 bg-slate-950/70 shadow-xl">
        <div class="px-5 py-4 border-b border-slate-800/80 flex items-center justify-between gap-3">
          <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-300">
              Login Security
            </div>
            <p class="text-[11px] text-slate-400">
              Update your password regularly, especially after changing staff.
            </p>
          </div>
          <div class="hidden md:flex items-center gap-1.5 text-[11px] text-emerald-300">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path d="M5 13l4 4L19 7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span>Recommended: strong unique password</span>
          </div>
        </div>

        <form method="POST"
              action="{{ route('portal.account.password') }}"
              class="px-5 py-5 space-y-4 text-sm">
          @csrf

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Current password --}}
            <div class="space-y-1.5">
              <label class="text-[11px] uppercase tracking-wide text-slate-400">
                Current password
              </label>
              <div class="relative">
                <input type="password"
                       name="current_password"
                       autocomplete="current-password"
                       class="w-full rounded-lg border border-slate-700 bg-slate-950/80 px-3 pr-9 py-2 text-sm
                              text-slate-100 placeholder-slate-500 focus:border-sky-400 focus:ring-0"
                       data-password-input
                       required>
                <button type="button"
                        class="absolute inset-y-0 right-2 flex items-center text-slate-500 hover:text-slate-200"
                        data-password-toggle
                        tabindex="-1">
                  {{-- eye (show) --}}
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" data-eye-open>
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="12" r="3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  {{-- eye-off (hide) --}}
                  <svg class="h-4 w-4 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" data-eye-closed>
                    <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-10-8-10-8a18.28 18.28 0 0 1 5.06-6.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"
                          stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </button>
              </div>
            </div>

            {{-- New password --}}
            <div class="space-y-1.5">
              <label class="text-[11px] uppercase tracking-wide text-slate-400">
                New password
              </label>
              <div class="relative">
                <input type="password"
                       name="password"
                       autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-700 bg-slate-950/80 px-3 pr-9 py-2 text-sm
                              text-slate-100 placeholder-slate-500 focus:border-sky-400 focus:ring-0"
                       data-password-input
                       required>
                <button type="button"
                        class="absolute inset-y-0 right-2 flex items-center text-slate-500 hover:text-slate-200"
                        data-password-toggle
                        tabindex="-1">
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" data-eye-open>
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="12" r="3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  <svg class="h-4 w-4 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" data-eye-closed>
                    <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-10-8-10-8a18.28 18.28 0 0 1 5.06-6.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"
                          stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </button>
              </div>
            </div>

            {{-- Confirm password --}}
            <div class="space-y-1.5 md:col-span-2">
              <label class="text-[11px] uppercase tracking-wide text-slate-400">
                Confirm new password
              </label>
              <div class="relative">
                <input type="password"
                       name="password_confirmation"
                       autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-700 bg-slate-950/80 px-3 pr-9 py-2 text-sm
                              text-slate-100 placeholder-slate-500 focus:border-sky-400 focus:ring-0"
                       data-password-input
                       required>
                <button type="button"
                        class="absolute inset-y-0 right-2 flex items-center text-slate-500 hover:text-slate-200"
                        data-password-toggle
                        tabindex="-1">
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" data-eye-open>
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="12" r="3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  <svg class="h-4 w-4 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" data-eye-closed>
                    <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-10-8-10-8a18.28 18.28 0 0 1 5.06-6.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"
                          stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 pt-3 border-t border-slate-800/80 mt-3">
            <p class="text-[11px] text-slate-500 max-w-md">
              Use at least <span class="text-slate-300 font-medium">8 characters</span> with a mix of
              letters, numbers and symbols. Avoid reusing passwords from other systems.
            </p>

            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 shadow-[0_10px_30px_rgba(56,189,248,0.35)]">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M5 13l4 4L19 7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              Update Password
            </button>
          </div>
        </form>
      </section>

    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggles = document.querySelectorAll('[data-password-toggle]');

    toggles.forEach(btn => {
        const wrapper = btn.closest('.relative');
        if (!wrapper) return;

        const input = wrapper.querySelector('[data-password-input]');
        const eyeOpen  = btn.querySelector('[data-eye-open]');
        const eyeClosed = btn.querySelector('[data-eye-closed]');

        if (!input) return;

        btn.addEventListener('click', () => {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';

            if (eyeOpen && eyeClosed) {
                if (isPassword) {
                    eyeOpen.classList.add('hidden');
                    eyeClosed.classList.remove('hidden');
                } else {
                    eyeClosed.classList.add('hidden');
                    eyeOpen.classList.remove('hidden');
                }
            }
        });
    });
});
</script>
@endpush
@endsection