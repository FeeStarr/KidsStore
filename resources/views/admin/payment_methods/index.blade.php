@extends('layouts.admin', ['title' => 'Payment Methods'])
@section('content')
<h3 class="mb-3">Payment Methods</h3>

<div class="card"><div class="card-body">
    <table class="table table-sm">
        <thead><tr><th>Key</th><th>Label</th><th class="text-center">Active</th><th></th></tr></thead>
        <tbody>
        @foreach($methods as $m)
            <tr>
                <td class="font-monospace">{{ $m->key }}</td>
                <td>{{ $m->label }}</td>
                <td class="text-center">@if($m->is_active)<span class="badge bg-success">Yes</span>@else<span class="badge bg-secondary">No</span>@endif</td>
                <td class="text-end">
                    <form method="post" action="{{ route('admin.payment-methods.update', $m) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="is_active" value="{{ $m->is_active ? 0 : 1 }}">
                        <button class="btn btn-sm {{ $m->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $m->is_active ? 'Deactivate' : 'Activate' }}</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div></div>

@endsection
