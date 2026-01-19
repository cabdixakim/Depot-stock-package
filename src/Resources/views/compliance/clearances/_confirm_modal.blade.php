<div id="confirmActionModal" class="hidden fixed inset-0 z-50 items-center justify-center">
    <div class="absolute inset-0 bg-gray-900/40" data-close-modal="confirm"></div>

    <div class="relative w-full max-w-md mx-4 rounded-2xl bg-white shadow-xl border border-gray-200 overflow-hidden">
        <div class="p-5 border-b border-gray-100">
            <div id="confirmActionTitle" class="text-base font-semibold text-gray-900">Confirm</div>
            <div id="confirmActionText" class="mt-1 text-sm text-gray-600">Are you sure?</div>
        </div>

        <div class="p-5 flex items-center justify-end gap-2">
            <button type="button"
                class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50"
                data-close-modal="confirm">
                Cancel
            </button>

            <button type="button"
                id="confirmActionBtn"
                class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                Confirm
            </button>
        </div>
    </div>
</div>