{{-- resources/views/vendor/depot-stock/clients/statement/print.blade.php --}}
@extends('depot-stock::layouts.app')

@section('title', 'Statement — ' . $client->name)

@section('content')
<style>
  /* ——— Hide any global chrome on this page ——— */
  .sticky.top-0, header, nav, #sideDrawer, #drawerBackdrop { display:none !important; }

  /* Page scaffold (keeps your original design) */
  body { background:#fff; }
  .wrap { max-width:900px; margin:32px auto; padding:0 20px; font-family: ui-sans-serif,system-ui,Segoe UI,Roboto,Helvetica,Arial; color:#111; }
  h1 { margin:0 0 6px; font-size:18px; }
  .muted { color:#666; font-size:12px; }

  table { width:100%; border-collapse:collapse; margin-top:16px; }
  th, td { padding:8px; border-bottom:1px solid #eee; font-size:12px; }
  th { text-align:left; color:#555; font-weight:600; }
  td.r { text-align:right; }

  .totals { display:flex; flex-wrap:wrap; gap:16px; margin-top:12px; }
  .card {
    border: 1px solid #eee;
    padding: 10px 12px;
    border-radius: 10px;
    flex: 1;
    min-width: 120px;
    background: linear-gradient(180deg, #ffffff 0%, #f7f7fb 100%);
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  }

  .big { font-weight:600; font-size:16px; }

  /* Pretty buttons */
  #printBtn {
    display:inline-flex; align-items:center; gap:8px;
    font-size:13px; color:#fff; text-decoration:none; font-weight:600;
    background:linear-gradient(135deg,#6366f1,#4f46e5);
    padding:8px 14px; border-radius:8px;
    box-shadow:0 2px 6px rgba(79,70,229,.2);
    transition:all .2s ease;
    margin-left:8px;
  }
  #printBtn:hover { background:linear-gradient(135deg,#4f46e5,#4338ca); box-shadow:0 4px 12px rgba(79,70,229,.3); transform:translateY(-1px); }
  #printBtn svg { width:14px; height:14px; stroke-width:2; stroke:#fff; }

  #backBtn {
    display:inline-flex; align-items:center; gap:8px;
    font-size:13px; color:#374151; text-decoration:none; font-weight:600;
    background:#f3f4f6;
    padding:8px 14px; border-radius:8px;
    border:1px solid #e5e7eb;
    transition:all .2s ease;
  }
  #backBtn:hover {
    background:#e5e7eb;
    border-color:#d1d5db;
    transform:translateY(-1px);
  }
  #backBtn svg { width:14px; height:14px; stroke-width:2; stroke:#4b5563; }

  @media print {
    #printBtn,
    #backBtn {
      display:none;
    }
  }
</style>

<div class="wrap">
  <div style="display:flex;justify-content:space-between;align-items:flex-end;">
    <div>
      <h1>Statement</h1>
      <div class="muted">{{ $client->name }}</div>
      <div class="muted">{{ $meta['from'] ?? '' }} → {{ $meta['to'] ?? '' }}</div>
    </div>

    <div>
      <a id="backBtn" href="#" onclick="window.history.back();return false;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M10 19l-7-7 7-7M3 12h18"/>
        </svg>
        Back
      </a>

      <a id="printBtn" href="#" onclick="window.print();return false;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6v-7z"/>
        </svg>
        Print
      </a>
    </div>
  </div>

  <div class="totals">
    <div class="card">
      <div class="muted">Opening</div>
      <div class="big">{{ number_format($meta['opening'] ?? 0, 2) }}</div>
    </div>
    <div class="card">
      <div class="muted">Charges</div>
      <div class="big">{{ number_format($meta['charges'] ?? 0, 2) }}</div>
    </div>
    <div class="card">
      <div class="muted">Credits</div>
      <div class="big">{{ number_format($meta['credits'] ?? 0, 2) }}</div>
    </div>
    <div class="card">
      <div class="muted">Closing</div>
      <div class="big">{{ number_format($meta['closing'] ?? 0, 2) }}</div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Date</th><th>Type</th><th>Document</th><th>Description</th>
        <th class="r">Debit</th><th class="r">Credit</th><th class="r">Balance</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
        <tr>
          <td>{{ $r['date'] ?? '' }}</td>
          <td>{{ $r['type'] ?? '' }}</td>
          <td>{{ $r['doc_no'] ?? '' }}</td>
          <td>{{ $r['description'] ?? '' }}</td>
          <td class="r">{{ number_format((float)($r['debit'] ?? 0), 2) }}</td>
          <td class="r">{{ number_format((float)($r['credit'] ?? 0), 2) }}</td>
          <td class="r">{{ number_format((float)($r['balance'] ?? 0), 2) }}</td>
        </tr>
      @empty
        <tr><td colspan="7" class="muted">No activity in this period.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection