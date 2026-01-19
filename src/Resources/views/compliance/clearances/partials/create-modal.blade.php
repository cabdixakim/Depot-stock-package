@php
    $clients = $clients ?? collect();
@endphp

<div class="modal fade" id="createClearanceModal" tabindex="-1" aria-labelledby="createClearanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius:18px; overflow:hidden;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title fw-bold" id="createClearanceModalLabel">Create Clearance</h5>
          <div class="text-muted small">Capture truck details first — TR8 comes later after submission.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form method="POST" action="{{ route('depot.compliance.clearances.store') }}">
        @csrf

        <div class="modal-body">
          <div class="row g-3">

            <div class="col-12">
              <label class="form-label small text-muted mb-1">Client</label>
              <select name="client_id" class="form-select" required>
                <option value="">Select client…</option>
                @foreach($clients as $c)
                  <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label small text-muted mb-1">Truck number</label>
              <input name="truck_number" class="form-control" required placeholder="e.g. T123ABC">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label small text-muted mb-1">Trailer number (optional)</label>
              <input name="trailer_number" class="form-control" placeholder="e.g. TR456XYZ">
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label small text-muted mb-1">Loaded @20°C (L)</label>
              <input name="loaded_20_l" type="number" step="0.001" class="form-control" placeholder="e.g. 42000.000">
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label small text-muted mb-1">Invoice # (optional)</label>
              <input name="invoice_number" class="form-control" placeholder="Invoice number">
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label small text-muted mb-1">Delivery note # (optional)</label>
              <input name="delivery_note_number" class="form-control" placeholder="Delivery note">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label small text-muted mb-1">Border point (optional)</label>
              <input name="border_point" class="form-control" placeholder="e.g. Kasumbalesa">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label small text-muted mb-1">Bond status</label>
              <div class="p-3 border rounded-3 d-flex align-items-center justify-content-between">
                <div>
                  <div class="fw-bold">Bonded truck</div>
                  <div class="text-muted small">Turn on if this movement is under bond.</div>
                </div>
                <div class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" role="switch" name="is_bonded" value="1">
                </div>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label small text-muted mb-1">Notes (optional)</label>
              <textarea name="notes" class="form-control" rows="3" placeholder="Any extra context…"></textarea>
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            Create Clearance
          </button>
        </div>
      </form>
    </div>
  </div>
</div>