@php
    $clients = $clients ?? collect();
@endphp

{{-- =========================
   CREATE CLEARANCE MODAL
   - Mobile scroll fixed (modal body scrolls)
   - Sticky footer actions
   - Premium header + better spacing
========================= --}}
<div id="createClearanceModal"
     class="hidden fixed inset-0 z-50 items-center justify-center"
     aria-hidden="true"
     role="dialog"
     aria-modal="true"
>
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]" data-close-modal="1"></div>

    {{-- Modal shell --}}
    <div class="relative w-full max-w-2xl mx-3 sm:mx-4 rounded-2xl bg-white shadow-2xl border border-gray-200 overflow-hidden
                max-h-[calc(100vh-1.25rem)] sm:max-h-[calc(100vh-3rem)] flex flex-col">

        {{-- Header (sticky) --}}
        <div class="sticky top-0 z-10 bg-white/90 backdrop-blur border-b border-gray-100">
            <div class="p-4 sm:p-5 flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="inline-flex items-center gap-2">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-slate-900 text-white shadow-sm">
                            {{-- Plus icon --}}
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <div>
                            <div class="text-xs font-semibold tracking-wide text-gray-500 uppercase">New clearance</div>
                            <div class="mt-0.5 text-lg font-semibold text-gray-900">Create draft</div>
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-gray-600">
                        Starts as <span class="font-semibold text-gray-900">Draft</span>. You can submit when ready.
                    </div>
                </div>

                <button type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        data-close-modal="1"
                        aria-label="Close create clearance modal"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    <span class="hidden sm:inline">Close</span>
                </button>
            </div>
        </div>

        {{-- Scrollable body --}}
        <form method="POST"
              action="{{ route('depot.compliance.clearances.store') }}"
              class="flex-1 min-h-0 overflow-y-auto px-4 sm:px-5 py-4 sm:py-5"
        >
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

                {{-- Bonded (kept in body so it scrolls with fields) --}}
                <div class="sm:col-span-2">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_bonded" value="1" class="rounded border-gray-300">
                        Bonded
                    </label>
                </div>
            </div>

            {{-- Sticky footer actions (always reachable on mobile) --}}
            <div class="sticky bottom-0 -mx-4 sm:-mx-5 mt-5 border-t border-gray-100 bg-white/95 backdrop-blur px-4 sm:px-5 py-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2">
                    <button type="button"
                            class="h-10 inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-800 hover:bg-gray-50"
                            data-close-modal="1">
                        Cancel
                    </button>
                    <button type="submit"
                            class="h-10 inline-flex items-center justify-center rounded-xl bg-gray-900 px-5 text-sm font-semibold text-white hover:bg-gray-800">
                        Create draft
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>


