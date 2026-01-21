{{-- =========================
   ISSUE TR8 MODAL
   - Mobile scroll fixed
   - Sticky footer actions
   - Premium file input section
========================= --}}
<div id="issueTr8Modal"
     class="hidden fixed inset-0 z-50 items-center justify-center"
     aria-hidden="true"
     role="dialog"
     aria-modal="true"
>
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]" data-close-modal="issue_tr8"></div>

    <div class="relative w-full max-w-2xl mx-3 sm:mx-4 rounded-2xl bg-white shadow-2xl border border-gray-200 overflow-hidden
                max-h-[calc(100vh-1.25rem)] sm:max-h-[calc(100vh-3rem)] flex flex-col">

        {{-- Header --}}
        <div class="sticky top-0 z-10 bg-white/90 backdrop-blur border-b border-gray-100">
            <div class="p-4 sm:p-5 flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="inline-flex items-center gap-2">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500 text-white shadow-sm">
                            {{-- Document/check icon --}}
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M8 4h6l4 4v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                <path d="M14 4v4h4" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                <path d="M9 14l2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <div>
                            <div class="text-xs font-semibold tracking-wide text-gray-500 uppercase">TR8</div>
                            <div class="mt-0.5 text-lg font-semibold text-gray-900">Issue TR8</div>
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-gray-600">Add TR8 details and optionally attach the document.</div>
                </div>

                <button type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        data-close-modal="issue_tr8"
                        aria-label="Close issue TR8 modal"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    <span class="hidden sm:inline">Close</span>
                </button>
            </div>
        </div>

        {{-- Scrollable body --}}
        <form id="issueTr8Form"
              method="POST"
              action=""
              enctype="multipart/form-data"
              class="flex-1 min-h-0 overflow-y-auto px-4 sm:px-5 py-4 sm:py-5"
        >
            @csrf
            <input type="hidden" id="issueTr8Action" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-semibold text-gray-700">TR8 number</label>
                    <input id="issueTr8Number" name="tr8_number" required
                           class="mt-1 h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10"
                           placeholder="Enter TR8 number">
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-700">Reference (optional)</label>
                    <input id="issueTr8Reference" name="tr8_reference"
                           class="mt-1 h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm focus:border-gray-900 focus:ring-gray-900/10"
                           placeholder="Internal reference">
                </div>

                <div class="sm:col-span-2">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-xs font-semibold text-gray-700">TR8 document (optional)</div>
                                <div class="mt-1 text-xs text-gray-500">PDF or image. Keep it clean for audit.</div>
                            </div>
                            <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-gray-700">
                                Max 10MB
                            </span>
                        </div>

                        <input id="issueTr8Document" type="file" name="tr8_documents[]"  accept=".pdf,.jpg,.jpeg,.png" multiple
                               class="mt-3 block w-full text-sm text-gray-700
                                      file:mr-3 file:rounded-xl file:border-0 file:bg-gray-900 file:px-4 file:py-2
                                      file:text-sm file:font-semibold file:text-white hover:file:bg-gray-800">
                           <div id="tr8FilePreview" class="mt-3 space-y-2 hidden"></div>            
                    </div>
                </div>
            </div>

            {{-- Sticky footer actions --}}
            <div class="sticky bottom-0 -mx-4 sm:-mx-5 mt-5 border-t border-gray-100 bg-white/95 backdrop-blur px-4 sm:px-5 py-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2">
                    <button type="button"
                            class="h-10 inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-800 hover:bg-gray-50"
                            data-close-modal="issue_tr8">
                        Cancel
                    </button>

                    <button type="button"
                            id="issueTr8Submit"
                            class="h-10 inline-flex items-center justify-center rounded-xl bg-gray-900 px-5 text-sm font-semibold text-white hover:bg-gray-800">
                        Save TR8
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>