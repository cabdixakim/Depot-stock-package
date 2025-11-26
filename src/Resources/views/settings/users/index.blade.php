@extends('depot-stock::layouts.app')

@section('title', 'Users & Roles')

@section('content')
<div class="min-h-[100dvh] bg-[#F7FAFC]">
  {{-- ===== Sticky Header ===== --}}
  <div class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-slate-100">
    <div class="mx-auto max-w-7xl px-4 md:px-6 h-14 flex items-center justify-between">
      <div class="leading-tight">
        <div class="text-[11px] uppercase tracking-wide text-slate-500">
          Settings
        </div>
        <div class="font-semibold text-slate-900">
          Users &amp; Roles
        </div>
      </div>

      <div class="flex items-center gap-3">
        {{-- Search --}}
        <form method="GET" class="hidden md:flex items-center">
          <div class="relative">
            <input
              type="text"
              name="q"
              value="{{ $q }}"
              placeholder="Search user or email…"
              class="h-9 w-56 rounded-xl border border-slate-200 bg-slate-50 px-8 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-0">
            <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="11" cy="11" r="6" stroke-width="1.7"/>
                <path d="M16 16l4 4" stroke-width="1.7" stroke-linecap="round"/>
              </svg>
            </span>
          </div>
        </form>

        {{-- New user button --}}
        <button
          type="button"
          id="btnOpenNewUser"
          class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M12 5v14M5 12h14" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          New user
        </button>
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
            <path d="M12 2l3 7h7l-5.5 4.1L18 21l-6-4-6 4 1.5-7.9L2 9h7z" stroke-width="1.6" stroke-linejoin="round"/>
            <path d="M9 12l2 2 4-4" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="text-xs md:text-[13px]">
          Manage <span class="font-semibold">internal users</span>, assign <span class="font-semibold">multiple roles</span> and reset passwords.
        </div>
      </div>
      <div class="text-[11px] text-slate-300">
        Admin / owner accounts are highlighted.
      </div>
    </div>

    {{-- Users grid --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
      @forelse($users as $user)
        @php
          $isAdmin = $user->roles->contains(fn($r) => in_array($r->name, ['admin','owner','superadmin']));
        @endphp

        <div class="rounded-2xl border {{ $isAdmin ? 'border-amber-300 shadow-[0_0_0_1px_rgba(251,191,36,0.35)]' : 'border-slate-200' }} bg-white p-4 shadow-sm flex flex-col gap-3">
          <div class="flex items-start justify-between gap-2">
            <div class="space-y-0.5">
              <div class="flex items-center gap-2">
                <div class="text-sm font-semibold text-slate-900">
                  {{ $user->name ?: 'No name' }}
                </div>
                @if($isAdmin)
                  <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 uppercase tracking-wide">
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M12 2l3 7h7l-5.5 4.1L18 21l-6-4-6 4 1.5-7.9L2 9h7z"/>
                    </svg>
                    Owner / Admin
                  </span>
                @endif
              </div>
              <div class="text-xs text-slate-500">
                {{ $user->email }}
              </div>
              @if($user->client)
                <div class="text-[11px] text-slate-500">
                  Client:
                  <span class="font-medium text-slate-700 user-client-label">
                    {{ $user->client->name }}
                  </span>
                </div>
              @else
                <div class="text-[11px] text-slate-400 user-client-label">
                  No client linked
                </div>
              @endif
            </div>

            <div class="flex flex-col gap-1 items-end">
              {{-- Edit basic info --}}
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-700 hover:bg-slate-50 edit-btn"
                data-user-id="{{ $user->id }}"
                data-user-name="{{ e($user->name ?: '') }}"
                data-user-email="{{ e($user->email) }}"
                data-user-client-id="{{ $user->client_id ?? '' }}"
              >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path d="M4 20h4l10-10-4-4L4 16v4z" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M14 6l4 4" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Edit
              </button>

              {{-- Reset password -> opens modal --}}
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-lg bg-slate-900/90 px-2.5 py-1 text-[11px] font-medium text-white hover:bg-black reset-btn"
                data-user-id="{{ $user->id }}"
                data-user-name="{{ e($user->name ?: $user->email) }}"
              >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path d="M4 4v6h6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M5 14a7 7 0 0 0 12 2.5L20 18" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M19 10a7 7 0 0 0-12-2.5L4 6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Reset
              </button>
            </div>
          </div>

          {{-- Roles form --}}
          <form method="POST" action="{{ route('depot.settings.users.roles', $user) }}" class="mt-1 user-roles-form">
            @csrf

            {{-- where we store client_id if popup is used --}}
            <input type="hidden" name="client_id" value="{{ $user->client_id ?? '' }}">

            <div class="mb-1 text-[11px] uppercase tracking-wide text-slate-500">
              Roles
            </div>

            <div class="flex flex-wrap gap-1.5">
              @foreach($roles as $role)
                @php
                  $checked = $user->roles->contains('id', $role->id);
                @endphp
                <label
                  class="role-chip inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] cursor-pointer
                    {{ $checked ? 'role-chip-active border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-slate-300' }}"
                  data-role-id="{{ $role->id }}"
                  data-role-name="{{ $role->name }}"
                >
                  <input
                    type="checkbox"
                    name="roles[]"
                    value="{{ $role->id }}"
                    class="hidden role-checkbox"
                    {{ $checked ? 'checked' : '' }}>
                  <span>{{ ucfirst($role->name) }}</span>
                </label>
              @endforeach
            </div>

            <div class="mt-2 flex justify-end">
              <button
                type="submit"
                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] text-slate-700 hover:bg-slate-100">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path d="M5 13l4 4L19 7" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Save roles
              </button>
            </div>
          </form>
        </div>
      @empty
        <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
          No users found.
        </div>
      @endforelse
    </div>

    {{-- Pagination --}}
    <div>
      {{ $users->links() }}
    </div>
  </div>
</div>

{{-- ===== New User Modal ===== --}}
<div id="newUserModal"
     class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-900/60">
  <div class="mx-4 w-full max-w-md rounded-2xl bg-white shadow-xl">
    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
      <div>
        <div class="text-sm font-semibold text-slate-900">Create new user</div>
        <div class="text-[11px] text-slate-500">Assign roles and (optionally) link a client.</div>
      </div>
      <button type="button" id="btnCloseNewUser" class="text-slate-400 hover:text-slate-600">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M6 6l12 12M18 6L6 18" stroke-width="1.7" stroke-linecap="round"/>
        </svg>
      </button>
    </div>

    <form method="POST" action="{{ route('depot.settings.users.create') }}" class="px-4 py-4 space-y-3 new-user-form">
      @csrf

      <div>
        <label class="block text-[11px] uppercase tracking-wide text-slate-500">Name</label>
        <input type="text" name="name" required autocomplete="off"
               class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-slate-400 focus:outline-none focus:ring-0">
      </div>

      <div>
        <label class="block text-[11px] uppercase tracking-wide text-slate-500">Email</label>
        <input type="email" name="email" required autocomplete="off"
               class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-slate-400 focus:outline-none focus:ring-0">
      </div>

      <div>
        <label class="block text-[11px] uppercase tracking-wide text-slate-500">Client (optional)</label>
        <select name="client_id"
                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:border-slate-400 focus:outline-none focus:ring-0">
          <option value="">— No client link —</option>
          @foreach($clients as $client)
            <option value="{{ $client->id }}">{{ $client->name }}</option>
          @endforeach
        </select>
        <p class="mt-1 text-[11px] text-slate-500">
          If you link a client, the <strong>client</strong> role will be added automatically.
        </p>
      </div>

      <div>
        <label class="block text-[11px] uppercase tracking-wide text-slate-500">Password</label>
        <input type="password" name="password" autocomplete="new-password"
               placeholder="Leave blank to auto-generate"
               class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-slate-400 focus:outline-none focus:ring-0">
        <p class="mt-1 text-[11px] text-slate-500">
          If empty, a strong random password will be generated and shown once.
        </p>
      </div>

      <div>
        <div class="mb-1 text-[11px] uppercase tracking-wide text-slate-500">Roles</div>
        <div class="flex flex-wrap gap-1.5">
          @foreach($roles as $role)
            <label
              class="new-role-chip inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] text-slate-700 cursor-pointer hover:border-slate-300"
              data-role-id="{{ $role->id }}"
              data-role-name="{{ $role->name }}"
            >
              <input type="checkbox" name="roles[]" value="{{ $role->id }}" class="hidden new-role-checkbox">
              <span>{{ ucfirst($role->name) }}</span>
            </label>
          @endforeach
        </div>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <button type="button" id="btnCancelNewUser"
                class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
          Cancel
        </button>
        <button type="submit"
                class="rounded-xl bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black">
          Create user
        </button>
      </div>
    </form>
  </div>
</div>

{{-- ===== Edit User Modal ===== --}}
<div id="editUserModal"
     class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-900/60">
  <div class="mx-4 w-full max-w-md rounded-2xl bg-white shadow-xl">
    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
      <div>
        <div class="text-sm font-semibold text-slate-900">Edit user</div>
        <div class="text-[11px] text-slate-500" id="editUserSubtitle">
          Update name, email and client link.
        </div>
      </div>
      <button type="button" id="btnCloseEditUser" class="text-slate-400 hover:text-slate-600">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M6 6l12 12M18 6L6 18" stroke-width="1.7" stroke-linecap="round"/>
        </svg>
      </button>
    </div>

    <form id="editUserForm"
          method="POST"
          data-action-template="{{ route('depot.settings.users.basic', ['user' => '__USER__']) }}"
          class="px-4 py-4 space-y-3">
      @csrf

      <div>
        <label class="block text-[11px] uppercase tracking-wide text-slate-500">Name</label>
        <input type="text" name="name" id="editUserName" required autocomplete="off"
               class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-slate-400 focus:outline-none focus:ring-0">
      </div>

      <div>
        <label class="block text-[11px] uppercase tracking-wide text-slate-500">Email</label>
        <input type="email" name="email" id="editUserEmail" required autocomplete="off"
               class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-slate-400 focus:outline-none focus:ring-0">
      </div>

      <div>
        <label class="block text-[11px] uppercase tracking-wide text-slate-500">Client (optional)</label>
        <select name="client_id" id="editUserClientId"
                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:border-slate-400 focus:outline-none focus:ring-0">
          <option value="">— No client link —</option>
          @foreach($clients as $client)
            <option value="{{ $client->id }}">{{ $client->name }}</option>
          @endforeach
        </select>
        <p class="mt-1 text-[11px] text-slate-500">
          Linking a client keeps the <strong>client</strong> role on this account.
        </p>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <button type="button" id="btnCancelEditUser"
                class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
          Cancel
        </button>
        <button type="submit"
                class="rounded-xl bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black">
          Save changes
        </button>
      </div>
    </form>
  </div>
</div>

{{-- ===== Link Client Modal (when ticking "client" role on existing user) ===== --}}
<div id="linkClientModal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60">
  <div class="mx-4 w-full max-w-sm rounded-2xl bg-white shadow-xl p-4 space-y-3">
    <div class="flex items-start justify-between gap-2">
      <div>
        <div class="text-sm font-semibold text-slate-900">Link client</div>
        <div class="text-[11px] text-slate-500">
          You selected the <strong>client</strong> role. Choose which client this user belongs to.
        </div>
      </div>
      <button type="button" id="btnCloseLinkClient" class="text-slate-400 hover:text-slate-600">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M6 6l12 12M18 6L6 18" stroke-width="1.7" stroke-linecap="round"/>
        </svg>
      </button>
    </div>

    <div>
      <label class="block text-[11px] uppercase tracking-wide text-slate-500">Client</label>
      <select id="linkClientSelect"
              class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-slate-400 focus:outline-none focus:ring-0">
        <option value="">— Select client —</option>
        @foreach($clients as $client)
          <option value="{{ $client->id }}">{{ $client->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="flex justify-end gap-2 pt-2">
      <button type="button" id="btnCancelLinkClient"
              class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
        Cancel
      </button>
      <button type="button" id="btnConfirmLinkClient"
              class="rounded-xl bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black">
        Link client
      </button>
    </div>
  </div>
</div>

{{-- ===== Reset Password Modal (interactive) ===== --}}
<div id="resetUserModal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60">
  <div class="mx-4 w-full max-w-md rounded-2xl bg-white shadow-xl p-4 space-y-3">
    <div class="flex items-start justify-between gap-2">
      <div>
        <div class="text-sm font-semibold text-slate-900">Reset password</div>
        <div class="text-[11px] text-slate-500" id="resetUserNameLabel">
          {{-- filled by JS --}}
        </div>
      </div>
      <button type="button" id="btnCloseResetUser" class="text-slate-400 hover:text-slate-600">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M6 6l12 12M18 6L6 18" stroke-width="1.7" stroke-linecap="round"/>
        </svg>
      </button>
    </div>

    <form id="resetUserForm"
          method="POST"
          data-action-template="{{ route('depot.settings.users.reset', ['user' => '__USER__']) }}">
      @csrf

      <div>
        <label class="block text-[11px] uppercase tracking-wide text-slate-500">New password</label>
        <input type="text" name="manual_password"
               placeholder="Leave blank to auto-generate a strong password"
               class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-slate-400 focus:outline-none focus:ring-0">
        <p class="mt-1 text-[11px] text-slate-500">
          If empty, the system will generate a random strong password and show it to you in the green popup.
        </p>
      </div>

      <div class="flex justify-end gap-2 pt-3">
        <button type="button" id="btnCancelResetUser"
                class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
          Cancel
        </button>
        <button type="submit"
                class="rounded-xl bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black">
          Reset password
        </button>
      </div>
    </form>
  </div>
</div>

{{-- ===== Password Modal (after create/reset) ===== --}}
@if($resetFor && $resetPlain)
  <div id="passwordModal"
       class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60">
    <div class="mx-4 w-full max-w-md rounded-2xl bg-white shadow-xl p-4 space-y-3">
      <div class="flex items-start justify-between gap-2">
        <div>
          <div class="text-sm font-semibold text-slate-900">New password generated</div>
          <div class="text-[11px] text-slate-500">
            Copy this password and share it with the user securely. It will not be shown again.
          </div>
        </div>
        <button type="button" id="btnClosePasswordModal" class="text-slate-400 hover:text-slate-600">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M6 6l12 12M18 6L6 18" stroke-width="1.7" stroke-linecap="round"/>
          </svg>
        </button>
      </div>

      <div class="rounded-xl bg-slate-900 px-3 py-2 flex items-center justify-between gap-2">
        <div id="pwdValue"
             class="font-mono text-sm text-emerald-300 break-all">
          {{ $resetPlain }}
        </div>
        <button type="button" id="btnCopyPassword"
                class="inline-flex items-center gap-1 rounded-lg bg-emerald-500 px-2 py-1 text-[11px] font-medium text-white hover:bg-emerald-600">
          <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <rect x="9" y="9" width="11" height="11" rx="2" stroke-width="1.5"/>
            <rect x="4" y="4" width="11" height="11" rx="2" stroke-width="1.5"/>
          </svg>
          Copy
        </button>
      </div>
    </div>
  </div>
@endif

@endsection

@push('scripts')
<script>
(function(){
  // ========== New user modal ==========
  const openBtn  = document.getElementById('btnOpenNewUser');
  const closeBtn = document.getElementById('btnCloseNewUser');
  const cancelBtn = document.getElementById('btnCancelNewUser');
  const newModal = document.getElementById('newUserModal');

  function closeNewModal(){
    if (!newModal) return;
    newModal.classList.add('hidden');
    newModal.classList.remove('flex');
  }

  if (openBtn && newModal) {
    openBtn.addEventListener('click', () => {
      newModal.classList.remove('hidden');
      newModal.classList.add('flex');
    });
  }
  [closeBtn, cancelBtn].forEach(btn => {
    if (btn && newModal) {
      btn.addEventListener('click', closeNewModal);
    }
  });
  if (newModal) {
    newModal.addEventListener('click', (e) => {
      if (e.target === newModal) closeNewModal();
    });
  }

  // ========== Role chips (existing users) ==========
  function toggleChipVisual(label, active) {
    if (!label) return;
    if (active) {
      label.classList.add('role-chip-active','border-slate-900','bg-slate-900','text-white');
      label.classList.remove('border-slate-200','bg-slate-50','text-slate-700');
    } else {
      label.classList.remove('role-chip-active','border-slate-900','bg-slate-900','text-white');
      label.classList.add('border-slate-200','bg-slate-50','text-slate-700');
    }
  }

  document.querySelectorAll('.role-chip').forEach(label => {
    const cb = label.querySelector('.role-checkbox');
    if (!cb) return;

    label.addEventListener('click', function(e){
      e.preventDefault(); // handle manually
      const willBeActive = !cb.checked;
      cb.checked = willBeActive;
      toggleChipVisual(label, willBeActive);

      // If this is the client role and user has no client yet -> open link client modal
      const roleName = label.dataset.roleName;
      if (roleName === 'client' && willBeActive) {
        const form = label.closest('.user-roles-form');
        if (!form) return;
        const hiddenClient = form.querySelector('input[name="client_id"]');
        const currentClientId = hiddenClient ? hiddenClient.value : '';

        if (!currentClientId) {
          // open popup and attach this form as target
          openLinkClientModal(form, label, cb);
        }
      }
    });

    // initial visual
    toggleChipVisual(label, cb.checked);
  });

  // ========== Role chips (new user modal) ==========
  function toggleNewChipVisual(label, active) {
    if (!label) return;
    if (active) {
      label.classList.add('border-slate-900','bg-slate-900','text-white');
      label.classList.remove('border-slate-200','bg-slate-50','text-slate-700');
    } else {
      label.classList.remove('border-slate-900','bg-slate-900','text-white');
      label.classList.add('border-slate-200','bg-slate-50','text-slate-700');
    }
  }

  document.querySelectorAll('.new-role-chip').forEach(label => {
    const cb = label.querySelector('.new-role-checkbox');
    if (!cb) return;

    label.addEventListener('click', function(e){
      e.preventDefault();
      const willBeActive = !cb.checked;
      cb.checked = willBeActive;
      toggleNewChipVisual(label, willBeActive);
    });

    toggleNewChipVisual(label, cb.checked);
  });

  // ========== Link client popup (for existing users) ==========
  const linkModal   = document.getElementById('linkClientModal');
  const linkSelect  = document.getElementById('linkClientSelect');
  const btnLinkOk   = document.getElementById('btnConfirmLinkClient');
  const btnLinkCancel = document.getElementById('btnCancelLinkClient');
  const btnLinkClose  = document.getElementById('btnCloseLinkClient');

  let linkTargetForm = null;
  let linkTargetLabel = null;
  let linkTargetCheckbox = null;

  function openLinkClientModal(form, label, cb) {
    linkTargetForm = form;
    linkTargetLabel = label;
    linkTargetCheckbox = cb;
    if (!linkModal) return;
    linkSelect.value = '';
    linkModal.classList.remove('hidden');
    linkModal.classList.add('flex');
  }

  function closeLinkClientModal() {
    if (!linkModal) return;
    linkModal.classList.add('hidden');
    linkModal.classList.remove('flex');
  }

  if (btnLinkOk && linkSelect) {
    btnLinkOk.addEventListener('click', () => {
      const val = linkSelect.value;
      if (!val) {
        // no client chosen -> do nothing, keep modal open
        return;
      }
      if (linkTargetForm) {
        const hiddenClient = linkTargetForm.querySelector('input[name="client_id"]');
        if (hiddenClient) {
          hiddenClient.value = val;
        }
      }
      closeLinkClientModal();
    });
  }

  function cancelLinkClient() {
    // revert the client chip toggle if user cancels
    if (linkTargetCheckbox && linkTargetLabel) {
      linkTargetCheckbox.checked = false;
      toggleChipVisual(linkTargetLabel, false);
    }
    closeLinkClientModal();
  }

  [btnLinkCancel, btnLinkClose].forEach(btn=>{
    if (btn) btn.addEventListener('click', cancelLinkClient);
  });

  if (linkModal) {
    linkModal.addEventListener('click', (e)=>{
      if (e.target === linkModal) {
        cancelLinkClient();
      }
    });
  }

  // ========== Edit user modal ==========
  const editModal     = document.getElementById('editUserModal');
  const editForm      = document.getElementById('editUserForm');
  const editCloseBtn  = document.getElementById('btnCloseEditUser');
  const editCancelBtn = document.getElementById('btnCancelEditUser');
  const editNameInput   = document.getElementById('editUserName');
  const editEmailInput  = document.getElementById('editUserEmail');
  const editClientInput = document.getElementById('editUserClientId');
  const editSubtitle    = document.getElementById('editUserSubtitle');

  function openEditModal(userId, userName, userEmail, clientId) {
    if (!editModal || !editForm) return;

    const tpl = editForm.dataset.actionTemplate;
    if (tpl) {
      editForm.action = tpl.replace('__USER__', userId);
    }

    if (editNameInput)   editNameInput.value = userName || '';
    if (editEmailInput)  editEmailInput.value = userEmail || '';
    if (editClientInput) {
      editClientInput.value = clientId || '';
    }

    if (editSubtitle) {
      editSubtitle.textContent = 'Editing ' + (userName || userEmail || 'user');
    }

    editModal.classList.remove('hidden');
    editModal.classList.add('flex');
  }

  function closeEditModal() {
    if (!editModal) return;
    editModal.classList.add('hidden');
    editModal.classList.remove('flex');
  }

  document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const id   = btn.dataset.userId;
      const name = btn.dataset.userName || '';
      const email= btn.dataset.userEmail || '';
      const cid  = btn.dataset.userClientId || '';
      openEditModal(id, name, email, cid);
    });
  });

  [editCloseBtn, editCancelBtn].forEach(btn => {
    if (btn) btn.addEventListener('click', closeEditModal);
  });

  if (editModal) {
    editModal.addEventListener('click', (e) => {
      if (e.target === editModal) closeEditModal();
    });
  }

  // ========== Reset user password modal ==========
  const resetModal   = document.getElementById('resetUserModal');
  const resetForm    = document.getElementById('resetUserForm');
  const resetNameLbl = document.getElementById('resetUserNameLabel');
  const resetClose   = document.getElementById('btnCloseResetUser');
  const resetCancel  = document.getElementById('btnCancelResetUser');

  function openResetModal(userId, userName){
    if (!resetModal || !resetForm) return;

    if (resetNameLbl) {
      resetNameLbl.textContent = 'Reset password for ' + userName;
    }

    const tpl = resetForm.dataset.actionTemplate;
    if (tpl) {
      resetForm.action = tpl.replace('__USER__', userId);
    }

    const pwdInput = resetForm.querySelector('input[name="manual_password"]');
    if (pwdInput) pwdInput.value = '';

    resetModal.classList.remove('hidden');
    resetModal.classList.add('flex');
  }

  function closeResetModal(){
    if (!resetModal) return;
    resetModal.classList.add('hidden');
    resetModal.classList.remove('flex');
  }

  document.querySelectorAll('.reset-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const id   = btn.dataset.userId;
      const name = btn.dataset.userName || 'this user';
      openResetModal(id, name);
    });
  });

  [resetClose, resetCancel].forEach(btn => {
    if (btn) btn.addEventListener('click', closeResetModal);
  });

  if (resetModal) {
    resetModal.addEventListener('click', e => {
      if (e.target === resetModal) closeResetModal();
    });
  }

  // ========== Password modal ==========
  const pwModal = document.getElementById('passwordModal');
  const pwClose = document.getElementById('btnClosePasswordModal');
  const pwCopy  = document.getElementById('btnCopyPassword');
  const pwValEl = document.getElementById('pwdValue');

  if (pwCopy && pwValEl) {
    pwCopy.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(pwValEl.textContent.trim());
        pwCopy.textContent = 'Copied';
        setTimeout(() => { pwCopy.textContent = 'Copy'; }, 1500);
      } catch (e) {}
    });
  }

  if (pwClose && pwModal) {
    pwClose.addEventListener('click', () => {
      pwModal.remove();
    });
  }
})();
</script>
@endpush
