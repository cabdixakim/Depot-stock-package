@php
    $clients = $clients ?? collect();
@endphp

<div id="createClearanceModal"
     class="hidden fixed inset-0 z-50 items-center justify-center"
     aria-hidden="true"
>
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]" data-close-modal="create_clearance"></div>

    {{-- Modal --}}
    <div class="relative w-full max-w-2xl mx-4 rounded-2xl bg-white shadow-2xl border border-gray-200 overflow-hidden">
        {{-- Header --}}
        <div class="p-5 border-b border-gray-100 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="text-xs font-semibold tracking-wide text-gray-500 uppercase">New clearance</div>
                <div class="mt-1 text-lg font-semibold text-gray-900">Create draft</div>
                <div class="mt-1 text-xs text-gray-600">Starts as <span class="font-semibold">Draft</span>. Submit when ready.</div>
            </div>

            <button type="button"
                class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                data-close-modal="create_clearance"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
                Close
            </button>
        </div>

        {{-- Body --}}
        <form method="POST" action="{{ route('depot.compliance.clearances.store') }}" class="p-5">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Client --}}
                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold text-gray-700">Client</label>
                    <select name="client_id" required
                        class="mt-1 h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10">
                        <option value="" disabled selected>Select client…</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Truck --}}
                <div>
                    <label class="text-xs font-semibold text-gray-700">Truck number</label>
                    <input name="truck_number" required
                        class="mt-1 h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10"
                        placeholder="e.g. KBT 123A">
                </div>

                {{-- Trailer --}}
                <div>
                    <label class="text-xs font-semibold text-gray-700">Trailer number</label>
                    <input name="trailer_number"
                        class="mt-1 h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10"
                        placeholder="Optional">
                </div>

                {{-- Loaded --}}
                <div>
                    <label class="text-xs font-semibold text-gray-700">Loaded @20°C (L)</label>
                    <input name="loaded_20_l" type="number" step="0.01"
                        class="mt-1 h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10"
                        placeholder="Optional">
                </div>

                {{-- Border --}}
                <div>
                    <label class="text-xs font-semibold text-gray-700">Border point</label>
                    <input name="border_point"
                        class="mt-1 h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10"
                        placeholder="Optional">
                </div>

                {{-- Invoice --}}
                <div>
                    <label class="text-xs font-semibold text-gray-700">Invoice number</label>
                    <input name="invoice_number"
                        class="mt-1 h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10"
                        placeholder="Optional">
                </div>

                {{-- Delivery --}}
                <div>
                    <label class="text-xs font-semibold text-gray-700">Delivery note number</label>
                    <input name="delivery_note_number"
                        class="mt-1 h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10"
                        placeholder="Optional">
                </div>

                {{-- Notes --}}
                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold text-gray-700">Notes</label>
                    <textarea name="notes" rows="3"
                        class="mt-1 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:border-gray-900 focus:ring-gray-900/10"
                        placeholder="Optional notes for compliance team…"></textarea>
                </div>

                {{-- Footer row --}}
                <div class="sm:col-span-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-2">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_bonded" value="1" class="rounded border-gray-300">
                        Bonded
                    </label>

                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800"
                    >
                        Create draft
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')

/**
 * Create Clearance modal controller
 * - open: index page button uses #btnOpenCreateClearance
 * - close: click backdrop OR any [data-close-modal="create_clearance"] OR Esc
 */
document.addEventListener("DOMContentLoaded", () => {
  const modal = document.getElementById("createClearanceModal");
  const openBtn = document.getElementById("btnOpenCreateClearance");
  if (!modal) return;

  const open = () => { modal.classList.remove("hidden"); modal.classList.add("flex"); modal.setAttribute("aria-hidden","false"); };
  const close = () => { modal.classList.add("hidden"); modal.classList.remove("flex"); modal.setAttribute("aria-hidden","true"); };

  openBtn?.addEventListener("click", open);

  // Close buttons + backdrop
  modal.addEventListener("click", (e) => {
    if (e.target?.matches('[data-close-modal="create_clearance"]')) close();
  });

  // Esc
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !modal.classList.contains("hidden")) close();
  });

  // Expose if you ever need it elsewhere
  window.__createClearanceModal = { open, close };
});

@endpush