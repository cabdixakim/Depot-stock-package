{{-- Modal for depot pool adjustment (variance correction) --}}
<div id="depotPoolAdjustModal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-2xl border border-white/60 bg-white/95 shadow-2xl mx-auto my-16">
        <div class="border-b border-gray-100 px-5 py-4">
            <h2 class="text-base font-semibold text-gray-900">Adjust Depot Pool</h2>
            <p class="mt-1 text-xs text-gray-500">Variance detected. Do you want to create a depot pool entry for <span id="depotPoolAdjustVarianceAmount" class="font-bold"></span>?</p>
        </div>
        <div class="px-5 py-4 flex justify-end gap-2">
            <button type="button" onclick="closeDepotPoolAdjustModal()" class="px-3 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
            <button type="button" id="confirmDepotPoolAdjustBtn" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Confirm</button>
        </div>
    </div>
</div>
@push('scripts')
<script>
let depotPoolAdjustData = null;
function openDepotPoolAdjustModal(variance, rowData = null) {
    document.getElementById('depotPoolAdjustVarianceAmount').textContent = variance > 0 ? `+${variance} L` : `${variance} L`;
    depotPoolAdjustData = rowData;
    const modal = document.getElementById('depotPoolAdjustModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}
function closeDepotPoolAdjustModal() {
    const modal = document.getElementById('depotPoolAdjustModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    depotPoolAdjustData = null;
}
const confirmBtn = document.getElementById('confirmDepotPoolAdjustBtn');
if (confirmBtn) {
    confirmBtn.onclick = function() {
        if (!depotPoolAdjustData) return;
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Processing...';
        fetch('/depot/pool/adjust-variance', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                depot_id: depotPoolAdjustData.depot_id,
                tank_id: depotPoolAdjustData.tank_id,
                product_id: depotPoolAdjustData.product_id,
                date: depotPoolAdjustData.date,
                variance_l_20: depotPoolAdjustData.variance_l_20
            })
        })
        .then(r => r.json())
        .then(data => {
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Confirm';
            if (data.ok) {
                closeDepotPoolAdjustModal();
                // Replace adjust block with indication
                const block = document.getElementById('varianceAdjustBlock');
                if (block) {
                    block.innerHTML = `<span class='text-xs text-gray-700'>Depot pool adjusted for variance on ${depotPoolAdjustData.date} (Tank ${depotPoolAdjustData.tank_id})</span><span class='text-xs font-semibold ${depotPoolAdjustData.variance_l_20 > 0 ? 'text-emerald-600' : 'text-rose-600'}'>Variance: ${depotPoolAdjustData.variance_l_20 > 0 ? '+' : ''}${depotPoolAdjustData.variance_l_20} L</span><span class='text-xs text-gray-500'>by You</span>`;
                }
            } else {
                alert(data.message || 'Adjustment failed.');
            }
        })
        .catch(() => {
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Confirm';
            alert('Error: Could not adjust depot pool.');
        });
    };
}
</script>
@endpush
