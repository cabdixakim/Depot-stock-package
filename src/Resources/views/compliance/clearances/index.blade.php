@extends('depot-stock::layouts.app')

@section('content')
<h3>Compliance – Clearances</h3>

<form class="row mb-3">
    <div class="col">
        <select name="client_id" class="form-control">
            <option value="">All Clients</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col">
        <select name="status" class="form-control">
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="submitted">Submitted</option>
            <option value="tr8_issued">TR8 Issued</option>
            <option value="arrived">Arrived</option>
            <option value="offloaded">Offloaded</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>

    <div class="col">
        <button class="btn btn-primary">Filter</button>
    </div>
</form>

<table class="table table-sm">
    <thead>
        <tr>
            <th>ID</th>
            <th>Client</th>
            <th>Truck</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach($clearances as $c)
            <tr>
                <td>{{ $c->id }}</td>
                <td>{{ $c->client->name }}</td>
                <td>{{ $c->truck_number }}</td>
                <td>{{ strtoupper($c->status) }}</td>
                <td>
                    <a href="{{ route('depot.compliance.clearances.show', $c) }}">View</a>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

{{ $clearances->links() }}
@endsection