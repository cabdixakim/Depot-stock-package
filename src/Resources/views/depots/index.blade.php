@extends('depot-stock::layouts.app')
@section('title','Depots')

@section('content')
@php
  use Optima\DepotStock\Models\DepotPolicy;

  $allowanceRate      = DepotPolicy::getNumeric('allowance_rate', 0.003);
  $maxStorageDays     = DepotPolicy::getNumeric('max_storage_days', 30);
  $zeroLoadLimit      = DepotPolicy::getNumeric('max_zero_physical_load_litres', 0);
  $unclearedThreshold = DepotPolicy::getNumeric('uncleared_flag_threshold', 200000);

  $policyAction = \Illuminate\Support\Facades\Route::has('depot.policies.save')
      ? route('depot.policies.save')
      : request()->url();
@endphp

<div class="max-w-5xl mx-auto space-y-6">

  {{-- Header --}}
  <div class="flex justify-between items-center">
    <div>
      <h1 class="text-xl font-semibold">Depots</h1>
      <p class="text-sm text-gray-500">Manage depots, tanks and products.</p>
    </div>

    <div class="flex gap-2">
      <button id="btnProducts"
        class="rounded-xl border px-3 py-2 text-xs bg-white hover:bg-gray-100">
        🧪 Products
      </button>

      <button id="btnAddDepot"
        class="rounded-xl bg-gray-900 text-white px-4 py-2 text-sm">
        + Add Depot
      </button>
    </div>
  </div>

  {{-- Depots --}}
  <div class="grid md:grid-cols-2 gap-4">
    @foreach($depots as $d)
      <div class="rounded-2xl border bg-white p-4">
        <div class="flex justify-between">
          <h2 class="font-semibold">{{ $d->name }}</h2>
          <span class="text-xs text-gray-500">{{ $d->tanks->count() }} tanks</span>
        </div>

        <div class="mt-3 flex gap-2">
          <button
            class="text-xs px-3 py-1.5 rounded bg-indigo-50 text-indigo-700"
            data-manage-tanks
            data-depot-id="{{ $d->id }}"
            data-depot-name="{{ $d->name }}">
            Manage tanks
          </button>
        </div>
      </div>
    @endforeach
  </div>
</div>

{{-- PRODUCTS MODAL --}}
<div id="productModal" class="fixed inset-0 z-[150] hidden">
  <button class="absolute inset-0 bg-black/40" data-close-products></button>

  <div class="absolute inset-0 flex justify-center items-start p-6">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl">

      <div class="px-5 py-3 border-b flex justify-between items-center">
        <div>
          <div class="text-xs uppercase text-gray-400">Products</div>
          <div class="text-sm font-semibold">Create once • reuse in tanks</div>
        </div>
        <button data-close-products>✕</button>
      </div>

      <form id="productForm"
            action="{{ route('depot.products.store') }}"
            method="POST"
            class="p-5 space-y-3">
        @csrf

        <label class="block text-xs text-gray-500">New product name</label>
        <div class="flex gap-2">
          <input id="productName"
                 type="text"
                 name="name"
                 required
                 class="flex-1 rounded-xl border px-3 py-2 text-sm"
                 placeholder="AGO, PMS, JET A1">

          <button type="submit"
                  class="rounded-xl bg-gray-900 text-white px-4 text-sm">
            + Add
          </button>
        </div>

        <p class="text-[11px] text-gray-400">
          Density is assigned automatically by backend (default).
        </p>

        <div id="productError"
             class="hidden text-[12px] text-rose-600"></div>
      </form>

      <div class="border-t px-5 py-4">
        <div class="text-xs text-gray-500 mb-2">
          Existing products ({{ $products->count() }})
        </div>

        <div id="productList" class="space-y-1">
          @foreach($products as $p)
            <div class="flex items-center gap-2 text-sm">
              <span class="inline-flex w-6 h-6 rounded-full bg-gray-100 items-center justify-center text-xs">
                {{ strtoupper(substr($p->name,0,1)) }}
              </span>
              {{ $p->name }}
            </div>
          @endforeach
        </div>
      </div>

      <div class="px-5 py-3 border-t text-right">
        <button data-close-products
          class="text-sm px-4 py-2 rounded bg-gray-100">
          Close
        </button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {

  // ---------- Products modal ----------
  const productModal = document.getElementById('productModal');
  const productForm  = document.getElementById('productForm');
  const productName  = document.getElementById('productName');
  const productError = document.getElementById('productError');
  const productList  = document.getElementById('productList');

  document.getElementById('btnProducts')?.addEventListener('click', () => {
    productModal.classList.remove('hidden');
    productName.focus();
  });

  document.querySelectorAll('[data-close-products]').forEach(b =>
    b.addEventListener('click', () => productModal.classList.add('hidden'))
  );

  function showProductError(msg) {
    productError.textContent = msg;
    productError.classList.remove('hidden');
  }

  function addProductToList(id, name) {
    const row = document.createElement('div');
    row.className = 'flex items-center gap-2 text-sm';
    row.innerHTML = `
      <span class="inline-flex w-6 h-6 rounded-full bg-gray-100 items-center justify-center text-xs">
        ${name.charAt(0).toUpperCase()}
      </span>
      ${name}
    `;
    productList.appendChild(row);
  }

  productForm?.addEventListener('submit', async (e) => {
    e.preventDefault();

    productError.classList.add('hidden');

    const fd = new FormData(productForm);

    const btn = productForm.querySelector('button[type="submit"]');
    const prev = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Adding…';

    try {
      const token = document.querySelector('meta[name="csrf-token"]').content;

      const res = await fetch(productForm.action, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': token,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: fd,
        credentials: 'same-origin',
      });

      const data = await res.json().catch(() => null);

      if (!res.ok || !data?.ok) {
        showProductError(
          data?.message ||
          data?.errors?.name?.[0] ||
          'Failed to add product.'
        );
        return;
      }

      addProductToList(data.product.id, data.product.name);
      productName.value = '';
    } finally {
      btn.disabled = false;
      btn.textContent = prev;
    }
  });
});
</script>
@endpush