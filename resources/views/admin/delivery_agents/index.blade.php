@extends('layouts.admin', ['title' => 'Delivery Agents'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Delivery Agents</h3>
    <a href="{{ route('admin.delivery-agents.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Add Agent
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
                <th>Name</th>
                <th>Contact</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Charges</th>
                <th>Status</th>
                <th style="width:140px"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($agents as $agent)
                <tr>
                    <td class="fw-semibold">{{ $agent->name }}</td>
                    <td>{{ $agent->contact_name ?: '-' }}</td>
                    <td>{{ $agent->phone ?: '-' }}</td>
                    <td>{{ $agent->email ?: '-' }}</td>
                    <td><span class="badge bg-info">{{ $agent->charges_count }}</span></td>
                    <td>
                        @if($agent->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('admin.delivery-agents.edit', $agent) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('admin.delivery-agents.destroy', $agent) }}" method="post" class="d-inline"
                              data-confirm="Delete this delivery agent?" data-confirm-title="Delete Agent?"
                              data-confirm-yes="Yes, delete">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted text-center py-4">No delivery agents yet. <a href="{{ route('admin.delivery-agents.create') }}">Add one</a>.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
