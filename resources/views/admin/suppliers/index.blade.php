@extends('layouts.admin', ['title' => 'Suppliers'])
@section('content')
<h3 class="mb-3">Suppliers</h3>

<a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary mb-3"><i class="bi bi-plus-lg"></i> New Supplier</a>

<div class="card">
    <div class="card-body">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th class="text-end">Active</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($suppliers as $s)
                    <tr>
                        <td>{{ $s->name }}</td>
                        <td>{{ $s->contact_name }}</td>
                        <td>{{ $s->phone }}</td>
                        <td>{{ $s->email }}</td>
                        <td class="text-end">{{ $s->is_active ? 'Yes' : 'No' }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.suppliers.edit', $s) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form method="post" action="{{ route('admin.suppliers.destroy', $s) }}" style="display:inline" onsubmit="return confirm('Delete supplier?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
