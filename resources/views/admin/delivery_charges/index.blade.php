@extends('layouts.admin', ['title' => 'Delivery Charges'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Delivery Charges</h3>
    <a href="{{ route('admin.delivery-charges.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Add Charge
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card">
    <table class="table table-hover mb-0">
        <thead class="table-light">
            <tr>
                <th>Agent</th>
                <th>Location</th>
                <th>Charge</th>
                <th>Status</th>
                <th style="width:140px"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($charges as $charge)
                <tr>
                    <td class="fw-semibold">{{ $charge->agent->name ?? '-' }}</td>
                    <td>{{ $charge->location->name ?? '-' }}</td>
                    <td>&#8358;{{ number_format($charge->amount, 2) }}</td>
                    <td>
                        @if($charge->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('admin.delivery-charges.edit', $charge) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('admin.delivery-charges.destroy', $charge) }}" method="post" class="d-inline"
                              data-confirm="Delete this delivery charge?" data-confirm-title="Delete Charge?"
                              data-confirm-yes="Yes, delete">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted text-center py-4">No delivery charges yet. <a href="{{ route('admin.delivery-charges.create') }}">Add one</a>.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
