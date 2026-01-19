<div id="issueTr8Modal" class="hidden fixed inset-0 z-50 items-center justify-center">
    <div class="absolute inset-0 bg-gray-900/40" data-close-modal="issue_tr8"></div>

    <div class="relative w-full max-w-2xl mx-4 rounded-2xl bg-white shadow-xl border border-gray-200 overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-start justify-between gap-3">
            <div>
                <div class="text-sm text-gray-500">TR8</div>
                <div class="text-lg font-semibold text-gray-900">Issue TR8</div>
                <div class="mt-1 text-xs text-gray-600">Add TR8 details and optionally attach the document.</div>
            </div>

            <button type="button"
                class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                data-close-modal="issue_tr8">
                Close
            </button>
        </div>

        <form id="issueTr8Form" method="POST" action="" enctype="multipart/form-data" class="p-5">
            @csrf

            <input type="hidden" id="issueTr8Action" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-medium text-gray-700">TR8 number</label>
                    <input id="issueTr8Number" name="tr8_number" required
                        class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                        placeholder="Enter TR8 number">
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-700">Reference (optional)</label>
                    <input id="issueTr8Reference" name="tr8_reference"
                        class="mt-1 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-gray-900 focus:ring-gray-900/10"
                        placeholder="Internal reference">
                </div>

                <div class="sm:col-span-2">
                    <label class="text-xs font-medium text-gray-700">TR8 document (optional)</label>
                    <input id="issueTr8Document" type="file" name="tr8_document" accept=".pdf,.jpg,.jpeg,.png"
                        class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-xl file:border-0 file:bg-gray-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-gray-800">
                    <div class="mt-1 text-xs text-gray-500">PDF or image. Keep it clean for audit.</div>
                </div>

                <div class="sm:col-span-2 flex items-center justify-end gap-2 pt-2">
                    <button type="button"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50"
                        data-close-modal="issue_tr8">
                        Cancel
                    </button>

                    <button type="button"
                        id="issueTr8Submit"
                        class="rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">
                        Save TR8
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>