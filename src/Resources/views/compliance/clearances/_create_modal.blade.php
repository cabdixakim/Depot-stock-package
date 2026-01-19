@php
    $clients = $clients ?? collect();
@endphp

<div id="createClearanceModal" class="hidden fixed inset-0 z-50 items-center justify-center">
    {{-- backdrop --}}
    <div class="absolute inset-0 bg-gray-900/40" data-close-modal="1"></div>

    {{-- modal --}}
    <div class="relative w-full max-w-2xl mx-4 rounded-2xl bg-white shadow-xl border border-gray-200 overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-start justify-between gap-3">
            <div>
                <div class="text-sm text-gray-500">New clearance</div>
                <div class="text-lg font-semibold text-gray-900">Create clearance</div>
                <div class="mt-1 text-xs text-gray-600">Start as draft. You can submit when ready.</div>
            </div>

            <button type="button"
                class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                data-close-modal="1">
                Close
            </button>
        </div>

        <form method="POST" action="{{ route('depot.compliance.clearances.store') }}" class="p-5">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="text-xs font-medium text-gray-700">Client</label>
                    <select name="client_id" required
                        class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10">
                        <option value="" disabled selected>Select client…</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-700">Truck number</label>
                    <input name="truck_number" required
                        class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                        placeholder="e.g. KBT 123A">
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-700">Trailer number</label>
                    <input name="trailer_number"
                        class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                        placeholder="Optional">
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-700">Loaded @20°C (L)</label>
                    <input name="loaded_20_l" type="number" step="0.01"
                        class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                        placeholder="Optional">
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-700">Border point</label>
                    <input name="border_point"
                        class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                        placeholder="Optional">
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-700">Invoice number</label>
                    <input name="invoice_number"
                        class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                        placeholder="Optional">
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-700">Delivery note number</label>
                    <input name="delivery_note_number"
                        class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                        placeholder="Optional">
                </div>

                <div class="sm:col-span-2">
                    <label class="text-xs font-medium text-gray-700">Notes</label>
                    <textarea name="notes" rows="3"
                        class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                        placeholder="Optional notes for compliance team…"></textarea>
                </div>

                <div class="sm:col-span-2 flex items-center justify-between gap-3 pt-2">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_bonded" value="1" class="rounded border-gray-300">
                        Bonded
                    </label>

                    <button type="submit"
                        class="rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">
                        Create draft
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>