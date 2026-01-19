@extends('depot-stock::layouts.app')

@section('content')
<h3>Clearance #{{ $clearance->id }}</h3>

<p><b>Status:</b> {{ strtoupper($clearance->status) }}</p>
<p><b>Truck:</b> {{ $clearance->truck_number }}</p>
<p><b>Loaded @20°C:</b> {{ $clearance->loaded_20_l }}</p>

@if(auth()->user()->hasRole('admin|compliance'))
    <form method="POST" action="{{ route('depot.compliance.clearances.submit', $clearance) }}">
        @csrf
        <button class="btn btn-warning btn-sm">Submit</button>
    </form>

    <form method="POST" action="{{ route('depot.compliance.clearances.issue_tr8', $clearance) }}">
        @csrf
        <input name="tr8_number" placeholder="TR8 Number" required>
        <button class="btn btn-success btn-sm">Issue TR8</button>
    </form>
@endif

<hr>
<h5>Activity</h5>
<ul>
@foreach($events as $e)
    <li>{{ $e->created_at }} — {{ $e->event }} by user {{ $e->user_id }}</li>
@endforeach
</ul>
@endsection