@extends('depot-stock::layouts.app')

@section('title', 'Statement — ' . $client->name)

@section('content')
@php
  // Brand config (safe fallbacks)
  $brandName =
      config('depot-stock.brand.name')
      ?? config('depot-stock.company_name')
      ?? config('app.name')
      ?? 'Company';

  $logoCfg =
      config('depot-stock.brand.logo')
      ?? config('depot-stock.company_logo')
      ?? null;

  $logoUrl = null;
  if ($logoCfg) {
    if (preg_match('/^https?:\/\//i', $logoCfg)) {
      $logoUrl = $logoCfg;
    } else {
      $path = public_path(ltrim($logoCfg, '/'));
      if (is_file($path)) $logoUrl = asset(ltrim($logoCfg, '/'));
    }
  }
@endphp

<style>
  /* ——— Hide any global chrome on this page ——— */
  .sticky.top-0, header, nav, #sideDrawer, #drawerBackdrop { display:none !important; }

  body { background:#fff; }
  .wrap { max-width:900px; margin:32px auto; padding:0 20px; font-family: ui-sans-serif,system-ui,Segoe UI,Roboto,Helvetica,Arial; color:#0f172a; }

  .topbar { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; }
  .centerBrand { flex: 1; display:flex; justify-content:center; }

  .brandPill{
    display:inline-flex; align-items:center; gap:10px;
    padding:10px 14px;
    border-radius: 999px;
    background: linear-gradient(180deg, #ffffff 0%, #f7f8fc 100%);
    border: 1px solid #e9edf5;
    box-shadow: 0 14px 38px rgba(2,6,23,.08);
    color:#0f172a;
  }
  .brandLogo{
    height:30px; width:auto;
    border-radius: 12px;
    background:#fff;
    border:1px solid #eef2f7;
    padding:6px;
    box-shadow: 0 10px 24px rgba(2,6,23,.10);
  }
  .brandMark{
    width:34px; height:34px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(99,102,241,.20), rgba(16,185,129,.14));
    border: 1px solid rgba(148,163,184,.35);
    display:flex; align-items:center; justify-content:center;
  }
  .brandWord{
    font-weight: 800;
    letter-spacing: .10em;
    text-transform: uppercase;
    font-size: 11px;
    line-height: 1.1;
    color:#0f172a;
  }
  .brandSub{
    font-size: 11px;
    color:#64748b;
    margin-top:2px;
    letter-spacing:.02em;
  }

  h1 { margin:0 0 6px; font-size:18px; color:#0f172a; }
  .muted { color:#64748b; font-size:12px; }

  table { width:100%; border-collapse:collapse; margin-top:16px; }
  th, td { padding:8px; border-bottom:1px solid #eef2f7; font-size:12px; }
  th { text-align:left; color:#475569; font-weight:700; font-size:11px; text-transform:uppercase; letter-spacing:.06em; }
  td.r { text-align:right; }

  .totals { display:flex; flex-wrap:wrap; gap:16px; margin-top:14px; }
  .card {
    border: 1px solid #eef2f7;
    padding: 10px 12px;
    border-radius: 14px;
    flex: 1;
    min-width: 120px;
    background: linear-gradient(180deg, #ffffff 0%, #f7f8fc 100%);
    box-shadow: 0 10px 26px rgba(2,6,23,.06);
  }
  .big { font-weight:800; font-size:16px; color:#0f172a; }

  /* Pretty buttons */
  #printBtn {
    display:inline-flex; align-items:center; gap:8px;
    font-size:13px; color:#fff; text-decoration:none; font-weight:700;
    background:linear-gradient(135deg,#6366f1,#4f46e5);
    padding:8px 14px; border-radius:12px;
    box-shadow:0 12px 28px rgba(79,70,229,.20);
    transition:all .2s ease;
    margin-left:8px;
  }
  #printBtn:hover { background:linear-gradient(135deg,#4f46e5,#4338ca); box-shadow:0 16px 34px rgba(79,70,229,.26); transform:translateY(-1px); }
  #printBtn svg { width:14px; height:14px; stroke-width:2; stroke:#fff; }

  #backBtn {
    display:inline-flex; align-items:center; gap:8px;
    font-size:13px; color:#0f172a; text-decoration:none; font-weight:700;
    background:#f1f5f9;
    padding:8px 14px; border-radius:12px;
    border:1px solid #e2e8f0;
    transition:all .2s ease;
  }
  #backBtn:hover { background:#e2e8f0; border-color:#cbd5e1; transform:translateY(-1px); }
  #backBtn svg { width:14px; height:14px; stroke-width:2; stroke:#334155; }

  @media print { #printBtn, #backBtn { display:none; } }
</style>

<div class="wrap">
  <div class="topbar">
    <div style="min-width:140px;"></div>

    {{-- ✅ centred brand --}}
    <div class="centerBrand">
      <div class="brandPill" aria-label="Brand">
        @if($logoUrl)
          <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="brandLogo">
        @else
          <div class="brandMark">
            {{-- premium modern mark (subtle indigo/emerald) --}}
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M12 2.75c3.8 3 7.6 3.5 9 3.75v7.35c0 5.4-3.95 9-9 9.8-5.05-.8-9-4.4-9-9.8V6.5c1.4-.25 5.2-.75 9-3.75Z"
                    stroke="rgba(79,70,229,.85)" stroke-width="1.8" stroke-linejoin="round"/>
              <path d="M8.6 12.1l2.2 2.2 4.8-5"
                    stroke="rgba(16,185,129,.85)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        @endif

        <div>
          <div class="brandWord">{{ $brandName }}</div>
          <!-- <div class="brandSub">Statement</div> -->
        </div>
      </div>
    </div>

    {{-- actions --}}
    <div style="display:flex;justify-content:flex-end;min-width:140px;">
      <a id="backBtn"
         href="{{ url()->previous() }}"
         onclick="if (window.history.length > 1) { window.history.back(); return false; }">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M10 19l-7-7 7-7M3 12h18"/>
        </svg>
        Back
      </a>

      <a id="printBtn" href="#" onclick="window.print();return false;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6v-7z"/>
        </svg>
        Print
      </a>
    </div>
  </div>

  <div style="margin-top:12px;">
    <h1>Statement</h1>
    <div class="muted">{{ $client->name }}</div>
    <div class="muted">{{ $meta['from'] ?? '' }} → {{ $meta['to'] ?? '' }}</div>
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