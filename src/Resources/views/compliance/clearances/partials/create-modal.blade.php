<div class="modal fade" id="createClearanceModal" tabindex="-1" aria-labelledby="createClearanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content modal-modern">
      <div class="modal-header modal-modern-h">
        <div>
          <div class="modal-title fw-black" id="createClearanceModalLabel">Create clearance</div>
          <div class="text-muted small">Record the truck movement before offload — invoice/DN, loaded @20°C, bonded status, border.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form method="POST" action="{{ route('depot.compliance.clearances.store') }}">
        @csrf

        <div class="modal-body modal-modern-b">
          <div class="row g-3">

            <div class="col-12">
              <label class="form-label small fw-bold text-muted">Client</label>
              <select name="client_id" class="form-select form-select-lg modern-input" required>
                <option value="">Select client…</option>
                @foreach(($clients ?? []) as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-bold text-muted">Truck number</label>
              <input name="truck_number" class="form-control form-control-lg modern-input" placeholder="e.g. KBY 123A" required>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-bold text-muted">Trailer number</label>
              <input name="trailer_number" class="form-control form-control-lg modern-input" placeholder="optional">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-bold text-muted">Loaded @ 20°C (L)</label>
              <input name="loaded_20_l" type="number" step="0.001" class="form-control form-control-lg modern-input" placeholder="e.g. 36000.000">
              <div class="form-text">This should auto-populate later when invoice/DN parsing is added.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-bold text-muted">Bonded?</label>
              <select name="is_bonded" class="form-select form-select-lg modern-input">
                <option value="0">No</option>
                <option value="1">Yes</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-bold text-muted">Invoice number</label>
              <input name="invoice_number" class="form-control form-control-lg modern-input" placeholder="optional">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-bold text-muted">Delivery note number</label>
              <input name="delivery_note_number" class="form-control form-control-lg modern-input" placeholder="optional">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-bold text-muted">Border point</label>
              <input name="border_point" class="form-control form-control-lg modern-input" placeholder="e.g. Kasumbalesa (optional)">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-bold text-muted">Notes</label>
              <input name="notes" class="form-control form-control-lg modern-input" placeholder="optional">
            </div>

          </div>
        </div>

        <div class="modal-footer modal-modern-f">
          <button type="button" class="btn btn-light btn-lg px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-lg px-4 fw-bold">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
  .modal-modern{ border:1px solid rgba(0,0,0,.10); border-radius:18px; overflow:hidden; }
  .modal-modern-h{ padding:16px 18px; background:linear-gradient(180deg, rgba(0,0,0,.02), rgba(0,0,0,0)); border-bottom:1px solid rgba(0,0,0,.06); }
  .modal-modern-b{ padding:18px; background:#fff; }
  .modal-modern-f{ padding:14px 18px; background:#fff; border-top:1px solid rgba(0,0,0,.06); }
  .modern-input{ border-radius:14px; border:1px solid rgba(0,0,0,.12); }
  .fw-black{ font-weight:900; }
</style>