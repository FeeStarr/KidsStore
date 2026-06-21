@extends('layouts.admin', ['title' => 'Bank Accounts'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Bank Accounts</h3>
    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#new-account">Add Account</button>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="collapse mb-3" id="new-account">
    <div class="card card-body">
        <form method="post" action="{{ route('admin.bank-accounts.store') }}">
            @csrf
            <div class="row g-2">
                <div class="col-md-3"><input name="bank_name" placeholder="Bank" class="form-control"></div>
                <div class="col-md-3"><input name="bank_account_name" placeholder="Account name" class="form-control"></div>
                <div class="col-md-3"><input name="bank_account_number" placeholder="Account number" class="form-control"></div>
                <div class="col-md-2"><input name="instructions" placeholder="Instructions" class="form-control"></div>
                <div class="col-md-1 text-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default">
                        <label class="form-check-label small" for="is_default">Default</label>
                    </div>
                </div>
            </div>
            <div class="mt-2 text-end">
                <button class="btn btn-primary btn-sm">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="card"><div class="card-body">
    <table class="table table-sm align-middle">
        <thead><tr><th>Bank</th><th>Account Name</th><th>Account No</th><th>Instructions</th><th class="text-center">Default</th><th class="text-center">Active</th><th></th></tr></thead>
        <tbody>
        @foreach($accounts as $a)
            <tr>
                <td>{{ $a->bank_name }}</td>
                <td>{{ $a->bank_account_name }}</td>
                <td class="font-monospace">{{ $a->bank_account_number }}</td>
                <td class="small text-muted">{{ $a->instructions }}</td>
                <td class="text-center">@if($a->is_default)<span class="badge bg-primary">Default</span>@endif</td>
                <td class="text-center">@if($a->is_active)<span class="badge bg-success">Yes</span>@else<span class="badge bg-secondary">No</span>@endif</td>
                <td class="text-end">
                    <form method="post" action="{{ route('admin.bank-accounts.update', $a) }}" class="d-inline">
                        @csrf @method('PATCH')
                        <input type="hidden" name="set_default" value="1">
                        <button class="btn btn-sm btn-outline-primary">Make default</button>
                    </form>
                    <form method="post" action="{{ route('admin.bank-accounts.update', $a) }}" class="d-inline">
                        @csrf @method('PATCH')
                        <input type="hidden" name="is_active" value="{{ $a->is_active ? 0 : 1 }}">
                        <button class="btn btn-sm {{ $a->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $a->is_active ? 'Deactivate' : 'Activate' }}</button>
                    </form>
                    <form method="post" action="{{ route('admin.bank-accounts.destroy', $a) }}" class="d-inline" onsubmit="return confirm('Remove this bank account?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div></div>

@endsection
