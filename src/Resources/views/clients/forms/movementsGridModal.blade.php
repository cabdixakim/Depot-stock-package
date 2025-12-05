{{-- resources/views/depot-stock/clients/forms/movementsGridModal.blade.php --}}

@php
  $u = auth()->user();
  $roleNames = $u?->roles?->pluck('name')->map(fn ($r) => strtolower($r))->all() ?? [];
  $isAdmin = in_array('admin', $roleNames) || in_array('owner', $roleNames) || in_array('superadmin', $roleNames);
@endphp

<div id="movementsModal" class="fixed inset-0 z-[140] hidden">

  {{-- Backdrop --}}
  <button type="button" class="absolute inset-0 bg-black/50 backdrop-blur-sm" data-mvm-close aria-label="Close"></button>

  <div class="absolute inset-0 flex items-start justify-center p-3 sm:p-4 md:p-8 overflow-y-auto">
    <div class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl ring-1 ring-gray-200 text-[clamp(12px,1.05vw,14px)]">

      {{-- Header --}}
      <div class="flex items-center justify-between gap-3 px-4 sm:px-6 py-3 sm:py-4 border-b bg-gradient-to-b from-gray-50 to-white rounded-t-2xl">
        <div class="flex items-center gap-3">
          <div class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">🚚</div>
          <div class="flex items-baseline gap-2">
            <h3 class="text-[15px] sm:text-base md:text-lg font-semibold text-gray-900">Movements</h3>
            <span id="mvmCount"
                  class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">
              0 rows
            </span>
          </div>
        </div>
        <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm hover:bg-gray-50"
                data-mvm-close>Close</button>
      </div>

      {{-- Controls --}}
      <div class="px-4 sm:px-6 pt-3 sm:pt-4 pb-2">

        {{-- Tabs --}}
        <div class="flex flex-wrap items-center gap-2">
          <button type="button" data-mvm-kind="offloads"
                  class="mvm-tab active inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm text-emerald-700 shadow-sm">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Offloads
          </button>
          <button type="button" data-mvm-kind="loads"
                  class="mvm-tab inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-3 py-1.5 text-sm text-sky-700 shadow-sm">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-sky-500"></span> Loads
          </button>

          {{-- Search / Dates --}}
          <div class="ml-auto flex flex-wrap items-center gap-2">
            <div class="relative w-48 sm:w-56">
              <input id="mvmSearch" type="search" placeholder="Search…" autocomplete="off"
                     class="w-full rounded-xl border-gray-200 bg-white pl-3 pr-8 py-1.5 text-sm focus:ring-0">
              <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
                   viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="m21 21-4.3-4.3M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16z"/>
              </svg>
            </div>

            <input type="date" id="mvmFrom" class="w-32 sm:w-36 rounded-xl border-gray-200 focus:ring-0 text-sm">
            <span class="text-xs text-gray-500">→</span>
            <input type="date" id="mvmTo" class="w-32 sm:w-36 rounded-xl border-gray-200 focus:ring-0 text-sm">

            <button type="button" id="mvmBtnApply"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm hover:bg-gray-50">
              Apply
            </button>
            <button type="button" id="mvmBtnReset"
                    class="inline-flex items-center gap-2 rounded-lg bg-gray-900 text-white px-3 py-1.5 text-sm hover:bg-black">
              Reset
            </button>
          </div>
        </div>

        {{-- Action Bar --}}
        <div class="mt-3 flex flex-wrap items-center gap-2 rounded-xl border border-gray-100 bg-white/60 px-2.5 py-2 backdrop-blur relative z-[300]">

          {{-- Export pills (client-side, Tabulator) --}}
          <div class="inline-flex items-center gap-1">
            <span class="text-[10px] uppercase tracking-wide text-gray-400 mr-1">Export</span>

            <button type="button" id="mvmExpCopy"
                    class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-50">
              Copy
            </button>

            <button type="button" id="mvmExpCsv"
                    class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-50">
              CSV
            </button>

            <button type="button" id="mvmExpXlsx"
                    class="inline-flex items-center gap-1 rounded-full bg-emerald-600 text-white px-2.5 py-1 text-[11px] font-medium shadow-sm hover:bg-emerald-700">
              Excel
            </button>

            <button type="button" id="mvmExpPdf"
                    class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-50">
              PDF
            </button>
          </div>

          {{-- Save changes (ADMIN ONLY) --}}
          @if($isAdmin)
          <button type="button" id="mvmBtnSave"
                  class="ml-auto hidden inline-flex items-center gap-2 rounded-md bg-indigo-600 text-white px-3 py-1.5 text-xs hover:bg-indigo-700">
            Save changes
          </button>
          @endif

          <div id="mvmTotals" class="text-[12px] ml-3"></div>
        </div>
      </div>

      {{-- Table --}}
      <div class="relative p-3 sm:p-6 pt-2">
        <div id="mvmLoading"
             class="pointer-events-none absolute inset-3 sm:inset-6 z-[250] hidden items-center justify-center rounded-xl bg-white/60 backdrop-blur">
          <div class="flex items-center gap-2 text-sm text-gray-600">
            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <circle cx="12" cy="12" r="10" stroke-width="2" opacity=".25"/>
              <path d="M22 12a10 10 0 0 1-10 10" stroke-width="2"/>
            </svg>
            Loading…
          </div>
        </div>

        <div class="rounded-xl border border-gray-100 shadow-sm overflow-x-auto">
          <div id="mvmTable" style="height: 60vh; min-height: 420px;"></div>
        </div>
      </div>

    </div>
  </div>
</div>

{{-- Confirm dialog --}}
<div id="mvmConfirm" class="fixed inset-0 z-[150] hidden">
  <button type="button" class="absolute inset-0 bg-black/40 backdrop-blur-sm" data-cf-cancel></button>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl ring-1 ring-gray-200">
      <div class="px-5 py-4 border-b">
        <h4 class="font-semibold text-gray-900">Confirm</h4>
      </div>
      <div class="px-5 py-4 space-y-3 text-sm">
        <div id="cfMessage" class="text-gray-700"></div>
        <div id="cfAlert" class="hidden rounded-md border border-amber-200 bg-amber-50 text-amber-800 px-3 py-2 text-xs"></div>
      </div>
      <div class="px-5 py-3 border-t flex justify-end gap-2">
        <button type="button" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm" data-cf-cancel>Cancel</button>
        <button type="button" class="rounded-lg bg-indigo-600 text-white px-3 py-1.5 text-sm" data-cf-ok>Save</button>
      </div>
    </div>
  </div>
</div>

@push('styles')
<style>
  .tabulator { border-radius: 10px; }
  .tabulator .tabulator-header {
    background: linear-gradient(180deg,#f7fafc 0%,#eef2f7 100%);
    border-bottom: 1px solid #e5e7eb;
  }
  .tabulator .tabulator-row:nth-child(even) { background: #f8fafc; }
  .tabulator .tabulator-row:hover { background: #fff8e1; }
  .tabulator .tabulator-cell, .tabulator .tabulator-col { border: 1px solid #e5e7eb !important; }

  /* Lock styling for billed rows */
  .row-locked { opacity:.65; }
  .badge-lock {
    display:inline-flex;align-items:center;gap:.35rem;
    background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;
    padding:.1rem .4rem;border-radius:.5rem;font-size:10px;font-weight:600;
  }
</style>
@endpush

@push('scripts')
<script>
(function(){
  const IS_ADMIN = @json($isAdmin);

  // Routes
  const dataUrl = @json(route('depot.clients.movements.data', $client));
  const saveUrl = @json(route('depot.clients.movements.save', $client));

  // DOM
  const modal = document.getElementById('movementsModal');
  const loader = document.getElementById('mvmLoading');
  const countEl = document.getElementById('mvmCount');
  const elSearch = document.getElementById('mvmSearch');
  const btnSave = document.getElementById('mvmBtnSave');
  const tabs = modal.querySelectorAll('.mvm-tab');

  // Export buttons (Tabulator client-side)
  const btnCopy = document.getElementById('mvmExpCopy');
  const btnCsv  = document.getElementById('mvmExpCsv');
  const btnXlsx = document.getElementById('mvmExpXlsx');
  const btnPdf  = document.getElementById('mvmExpPdf');

  // Confirm modal
  const cf = document.getElementById('mvmConfirm');
  const cfMsg = document.getElementById('cfMessage');
  const cfAlert = document.getElementById('cfAlert');
  const cfOk = cf.querySelector('[data-cf-ok]');
  const cfCancelEls = cf.querySelectorAll('[data-cf-cancel]');

  // State
  let currentKind = 'offloads';
  let table = null;
  let tableBuilt = false;
  let dirtyMap = new Map();  // id -> rowData snapshot
  let currentRows = [];      // last loaded dataset for quick lookups

  // Formatters
  const nf = new Intl.NumberFormat('en-US',{maximumFractionDigits:3});
  const fmt = v => (v==null || v==='') ? '' : nf.format(+v);

  // simple helper to build export filename
  function buildExportName(ext){
    const from = document.getElementById('mvmFrom')?.value || 'all';
    const to   = document.getElementById('mvmTo')?.value || 'all';
    const base = `movements_${currentKind}_${from}_${to}`.replace(/[^0-9A-Za-z_-]+/g,'-');
    return `${base}.${ext}`;
  }

  // Created By chip formatter (defensive on field names)
  function createdByFormatter(cell){
    const d = cell.getRow().getData() || {};

    const name =
      d.created_by_name ||
      d.user_name ||
      d.user ||
      d.created_by ||
      '';

    const email =
      d.created_by_email ||
      d.user_email ||
      '';

    const id =
      d.created_by_id ||
      d.user_id ||
      '';

    let label = name;
    if (!label && email) {
      label = email.split('@')[0];
    }
    if (!label && id) {
      label = `User #${id}`;
    }
    if (!label) {
      return '—';
    }

    const initials = label
      .trim()
      .split(/\s+/)
      .map(p => p[0])
      .join('')
      .slice(0, 2)
      .toUpperCase();

    const createdAt = d.created_at || d.created_at_formatted || '';
    const tooltipParts = [];
    if (name) tooltipParts.push(name);
    if (email) tooltipParts.push(`<${email}>`);
    if (createdAt) tooltipParts.push(`on ${createdAt}`);
    const tooltip = tooltipParts.join(' ');

    const cellEl = cell.getElement();
    if (tooltip) {
      cellEl.setAttribute('title', tooltip);
    }

    const wrap = document.createElement('div');
    wrap.className = 'inline-flex items-center gap-2';

    const badge = document.createElement('span');
    badge.className = 'inline-flex items-center justify-center h-6 w-6 rounded-full bg-gray-900/5 text-[11px] font-semibold text-gray-700 border border-gray-200';
    badge.textContent = initials || '•';

    const text = document.createElement('span');
    text.className = 'text-[12px] text-gray-800';
    text.textContent = label;

    wrap.appendChild(badge);
    wrap.appendChild(text);

    return wrap;
  }

  // ===== Modal open/close =====
  document.addEventListener('click', e=>{
    const t = e.target.closest('[data-open-movements]');
    if(!t) return;
    e.preventDefault();
    // honor explicit kind on the button
    const desired = t.getAttribute('data-kind');
    if (desired === 'loads' || desired === 'offloads') currentKind = desired;

    modal.classList.remove('hidden');
    ensureTable();
    // switch visual active tab
    tabs.forEach(b=>{
      b.classList.toggle('active', b.dataset.mvmKind === currentKind);
    });
    refreshColumns(true);   // true = initial build (no setColumns)
    loadData();             // fetch immediately
  });
  modal.querySelectorAll('[data-mvm-close]').forEach(b=>b.onclick=()=>modal.classList.add('hidden'));

  // ===== Tabs =====
  tabs.forEach(b=>b.addEventListener('click', ()=>{
    if (b.classList.contains('active')) return;
    tabs.forEach(x=>x.classList.remove('active'));
    b.classList.add('active');
    currentKind = b.dataset.mvmKind;
    refreshColumns(false);
    loadData();
  }));

  // ===== Table =====
  function ensureTable(){
    if(table) return;
    table = new Tabulator('#mvmTable',{
      layout:'fitDataStretch',
      placeholder:'No rows.',
      reactiveData:true,
      index: 'id',
      columnHeaderVertAlign:"middle",
      columnDefaults:{headerHozAlign:"left",vertAlign:"middle"},
      columns: buildColumns(),   // initial columns to avoid setColumns before build
      rowFormatter:function(row){
        const d = row.getData();
        if (currentKind==='offloads' && d.billed_invoice_id){
          row.getElement().classList.add('row-locked');
          // add lock chip in first cell
          const el = row.getElement();
          const first = el.querySelector('.tabulator-cell');
          if (first && !first.querySelector('.badge-lock')) {
            const chip = document.createElement('span');
            chip.className='badge-lock';
            chip.innerHTML='🔒 Billed';
            first.prepend(chip);
          }
        }
      },
      tableBuilt:function(){ tableBuilt = true; }
    });

    // live search
    elSearch.addEventListener('input', ()=>{
      const q = elSearch.value.trim().toLowerCase();
      if(!q){ table.clearFilter(true); return; }
      table.setFilter(function(data){
        for(const k in data){
          const v=data[k];
          if(v!=null && String(v).toLowerCase().includes(q)) return true;
        }
        return false;
      });
    });

    // track edits
    table.on('cellEdited', cell=>{
      const row = cell.getRow();
      const d = {...row.getData()};

      // non-admins: immediately revert any edit
      if (!IS_ADMIN) {
        row.update({ [cell.getField()]: cell.getOldValue() });
        return;
      }

      // Prevent edits to billed offloads (hard guard)
      if(currentKind==='offloads' && d.billed_invoice_id){
        row.reformat(); // will repaint lock
        row.update({[cell.getField()]: cell.getOldValue()}); // revert
        return;
      }

      dirtyMap.set(d.id, d);
      if (IS_ADMIN && btnSave) {
        btnSave.classList.remove('hidden');
      }
    });
  }

  function buildColumns(){
    const canEdit = IS_ADMIN;

    const base = [
      {title:"ID", field:"id", width:70, hozAlign:"right"},
      {title:"Date", field:"date", editor: canEdit ? "input" : false, width:120,
        editable: cell => canEdit && !(currentKind==='offloads' && cell.getRow().getData().billed_invoice_id)
      },
      {title:"Depot", field:"depot", width:140, editor:false},
      {title:"Product", field:"product", width:130, editor:false},
      {title:"Tank", field:"tank", width:90, editor:false},
      {
        title:"Created By",
        field:"created_by_name",
        width:170,
        hozAlign:"left",
        headerSort:false,
        formatter: createdByFormatter,
        editor:false
      },
    ];

    if(currentKind==='loads'){
      return [
        ...base,
        {title:"Loaded (L @20°C)", field:"loaded_20_l", width:160, hozAlign:"right",
          formatter:c=>fmt(c.getValue()),
          editor: canEdit ? "number" : false,
          editable: () => canEdit,
        },
        {title:"Temp (°C)", field:"temperature_c", width:110, hozAlign:"right",
          formatter:c=>fmt(c.getValue()),
          editor: canEdit ? "number" : false,
          editable: () => canEdit,
        },
        {title:"Density", field:"density_kg_l", width:110, hozAlign:"right",
          formatter:c=>fmt(c.getValue()),
          editor: canEdit ? "number" : false,
          editable: () => canEdit,
        },
        {title:"Truck", field:"truck_plate", width:120,
          editor: canEdit ? "input" : false,
          editable: () => canEdit,
        },
        {title:"Trailer", field:"trailer_plate", width:120,
          editor: canEdit ? "input" : false,
          editable: () => canEdit,
        },
        {title:"Reference", field:"reference", width:160,
          editor: canEdit ? "input" : false,
          editable: () => canEdit,
        },
        {title:"Note", field:"note", width:200,
          editor: canEdit ? "input" : false,
          editable: () => canEdit,
        },
      ];
    }

    // offloads
    return [
      ...base,
      {title:"Loaded (paper)", field:"loaded_observed_l", width:130, hozAlign:"right",
        formatter:c=>fmt(c.getValue()),
        editor: canEdit ? "number" : false,
        editable: cell => canEdit && !cell.getRow().getData().billed_invoice_id
      },
      {title:"Observed (meter)", field:"delivered_observed_l", width:150, hozAlign:"right",
        formatter:c=>fmt(c.getValue()),
        editor: canEdit ? "number" : false,
        editable: cell => canEdit && !cell.getRow().getData().billed_invoice_id
      },
      {title:"Delivered (L @20°C)", field:"delivered_20_l", width:170, hozAlign:"right",
        formatter:c=>fmt(c.getValue()),
        editor: canEdit ? "number" : false,
        editable: cell => canEdit && !cell.getRow().getData().billed_invoice_id
      },
      {title:"Shortfall @20", field:"shortfall_20_l", width:130, hozAlign:"right",
        formatter:c=>fmt(c.getValue()), editor:false},
      {title:"Allowance @20", field:"depot_allowance_20_l", width:140, hozAlign:"right",
        formatter:c=>fmt(c.getValue()), editor:false},
      {title:"Temp (°C)", field:"temperature_c", width:110, hozAlign:"right",
        formatter:c=>fmt(c.getValue()),
        editor: canEdit ? "number" : false,
        editable: cell => canEdit && !cell.getRow().getData().billed_invoice_id
      },
      {title:"Density", field:"density_kg_l", width:110, hozAlign:"right",
        formatter:c=>fmt(c.getValue()),
        editor: canEdit ? "number" : false,
        editable: cell => canEdit && !cell.getRow().getData().billed_invoice_id
      },
      {title:"Truck", field:"truck_plate", width:120,
        editor: canEdit ? "input" : false,
        editable: cell => canEdit && !cell.getRow().getData().billed_invoice_id
      },
      {title:"Trailer", field:"trailer_plate", width:120,
        editor: canEdit ? "input" : false,
        editable: cell => canEdit && !cell.getRow().getData().billed_invoice_id
      },
      {title:"Reference", field:"reference", width:160,
        editor: canEdit ? "input" : false,
        editable: cell => canEdit && !cell.getRow().getData().billed_invoice_id
      },
      {title:"Note", field:"note", width:200,
        editor: canEdit ? "input" : false,
        editable: cell => canEdit && !cell.getRow().getData().billed_invoice_id
      },
    ];
  }

  function refreshColumns(initial=false){
    if(initial){ return; }
    if(!tableBuilt){ return; }
    table.setColumns(buildColumns());
  }

  // ===== Data loading =====
  async function loadData(){
    if(!table) return;
    loader.classList.remove('hidden');

    const qs = new URLSearchParams({ kind: currentKind });
    const elFrom=document.getElementById('mvmFrom');
    const elTo=document.getElementById('mvmTo');
    if(elFrom?.value) qs.set('from',elFrom.value);
    if(elTo?.value) qs.set('to',elTo.value);

    try{
      const res = await fetch(`${dataUrl}?${qs.toString()}`, { headers:{'Accept':'application/json'} });
      const payload = await res.json();
      const rows = Array.isArray(payload) ? payload : (payload.rows || []);
      currentRows = rows;
      table.replaceData(rows);
      countEl.textContent = `${rows.length} row${rows.length===1?'':'s'}`;
    }catch(err){
      console.error(err);
      currentRows = [];
      table.replaceData([]);
      countEl.textContent='0 rows';
    }finally{
      loader.classList.add('hidden');
    }
  }

  // Filters
  document.getElementById('mvmBtnApply')?.addEventListener('click', loadData);
  document.getElementById('mvmBtnReset')?.addEventListener('click', ()=>{
    ['mvmFrom','mvmTo','mvmSearch'].forEach(id=>{ const el=document.getElementById(id); if(el) el.value=''; });
    table?.clearFilter(true);
    loadData();
  });

  // ===== Export buttons (Tabulator client-side) =====
  btnCopy?.addEventListener('click', ()=>{
    if (!table) return;
    table.copyToClipboard();
  });

  btnCsv?.addEventListener('click', ()=>{
    if (!table) return;
    table.download("csv", buildExportName('csv'));
  });

  btnXlsx?.addEventListener('click', ()=>{
    if (!table) return;
    table.download("xlsx", buildExportName('xlsx'));
  });

  btnPdf?.addEventListener('click', ()=>{
    if (!table) return;
    table.download("pdf", buildExportName('pdf'), {
      orientation: "landscape",
      title: currentKind === 'offloads' ? "Client offloads" : "Client loads",
    });
  });

  // ===== Save flow (ADMIN ONLY) =====
  if (btnSave && IS_ADMIN) {
    btnSave.addEventListener('click', ()=>{
      // prune invalid/billed rows before confirming
      sanitizeDirty();
      const changes = Array.from(dirtyMap.values());
      if(!changes.length){
        // nothing left to save
        btnSave.classList.add('hidden');
        return;
      }
      showConfirm(`You're about to update <strong>${changes.length}</strong> row(s) in <strong>${currentKind}</strong>.`);
    });
  }

  function sanitizeDirty(){
    for(const [id, data] of Array.from(dirtyMap.entries())){
      if(currentKind==='offloads' && data.billed_invoice_id){
        dirtyMap.delete(id);
      }
    }
  }

  function showConfirm(html){
    cfMsg.innerHTML = html;
    cfAlert.classList.add('hidden');
    cf.classList.remove('hidden');
  }
  cfCancelEls.forEach(b=>b.addEventListener('click', ()=> cf.classList.add('hidden')));

  cfOk.addEventListener('click', async ()=>{
    if (!IS_ADMIN) {  // double-guard
      cf.classList.add('hidden');
      return;
    }

    cfOk.disabled = true;
    try{
      const rows = Array.from(dirtyMap.values());
      const res = await fetch(saveUrl, {
        method:'POST',
        headers:{
          'Content-Type':'application/json',
          'X-Requested-With':'XMLHttpRequest',
          'Accept':'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}',
        },
        body: JSON.stringify({ kind: currentKind, rows })
      });

      if(res.status === 422){
        const data = await res.json().catch(()=>({}));
        const msg = (data && data.message) ? data.message : 'Some rows could not be saved.';
        // try remove offending id if present in message (e.g., "Offload #123 ...")
        const m = msg.match(/#(\d+)/);
        if(m){
          const badId = parseInt(m[1],10);
          dirtyMap.delete(badId);
        }else{
          // if we can spot billed rows in current snapshot, drop them
          for(const [id, d] of Array.from(dirtyMap.entries())){
            if(d.billed_invoice_id) dirtyMap.delete(id);
          }
        }
        cfAlert.textContent = msg + ' The locked row was removed from the pending changes.';
        cfAlert.classList.remove('hidden');
        return; // keep confirm open so user can Save again
      }

      if(!res.ok){
        const t = await res.text();
        cfAlert.textContent = t || 'Failed to save changes.';
        cfAlert.classList.remove('hidden');
        return;
      }

      // Success
      cf.classList.add('hidden');
      dirtyMap.clear();
      if (btnSave) btnSave.classList.add('hidden');
      await loadData(); // refresh latest
    }catch(err){
      cfAlert.textContent = 'Network error. Please try again.';
      cfAlert.classList.remove('hidden');
    }finally{
      cfOk.disabled = false;
    }
  });

})();
</script>
@endpush