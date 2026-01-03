@extends('depot-stock::layouts.app')
@section('title','Depots')

@section('content')
@php
    $activeName = $activeDepot?->name;

    // ---- Global depot policy values (safe defaults) ----
    use Optima\DepotStock\Models\DepotPolicy;

    $allowanceRate      = DepotPolicy::getNumeric('allowance_rate', 0.003);          // 0.3%
    $maxStorageDays     = DepotPolicy::getNumeric('max_storage_days', 30);           // idle after 30 days
    $zeroLoadLimit      = DepotPolicy::getNumeric('max_zero_physical_load_litres', 0);
    $unclearedThreshold = DepotPolicy::getNumeric('uncleared_flag_threshold', 200000);

    $policyAction = \Illuminate\Support\Facades\Route::has('depot.policies.save')
        ? route('depot.policies.save')
        : request()->url();
@endphp

<p> test </p>
@endsection
@endpush