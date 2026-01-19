@php
    $clients = $clients ?? collect();
@endphp

<div class="modal fade" id="createClearanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px; overflow:hidden;">
            <div class="modal-header" style="background:linear-gradient(135deg, rgba(0,0,0,.03), rgba(0,0,0,0));">
                <div>
                    <div style="font-weight:950;font-size:16px;">New Clearance</div>
                    <div class="text-muted" style="font-size:12px;">Create the truck movement record before offload (TR8 + docs + timeline).</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="{{ route('depot.compliance.clearances.store') }}">
                @csrf

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted fw-bold">Client</label>
                            <select name="client_id" class="form-select" required style="border-radius:12px;">
                                <option value="">Select client</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted fw-bold">Border point</label>
                            <input name="border_point" class="form-control" style="border-radius:12px;" placeholder="e.g. Kasumbalesa">
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted fw-bold">Truck number</label>
                            <input name="truck_number" class="form-control" style="border-radius:12px;" required placeholder="Truck plate">
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted fw-bold">Trailer number</label>
                            <input name="trailer_number" class="form-control" style="border-radius:12px;" placeholder="Trailer plate">
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted fw-bold">Loaded @20°C (L)</label>
                            <input name="loaded_20_l" type="number" step="0.001" class="form-control" style="border-radius:12px;" placeholder="0.000">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted fw-bold">Invoice number</label>
                            <input name="invoice_number" class="form-control" style="border-radius:12px;" placeholder="Supplier invoice">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted fw-bold">Delivery note number</label>
                            <input name="delivery_note_number" class="form-control" style="border-radius:12px;" placeholder="Delivery note">
                        </div>

                        <div class="col-12">
                            <div class="d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" value="1" name="is_bonded" id="isBonded">
                                <label class="form-check-label fw-bold" for="isBonded">Bonded truck</label>
                                <span class="text-muted" style="font-size:12px;">Enable if the truck is under bond and needs TR8 clearance.</span>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small text-muted fw-bold">Notes</label>
                            <textarea name="notes" rows="3" class="form-control" style="border-radius:12px;" placeholder="Any useful notes for compliance…"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="background:#fff;">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:12px;font-weight:800;">Close</button>
                    <button type="submit" class="btn btn-primary" style="border-radius:12px;font-weight:900;">Create Clearance</button>
                </div>
            </form>
        </div>
    </div>
</div>