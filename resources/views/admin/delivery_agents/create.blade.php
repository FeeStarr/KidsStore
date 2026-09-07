@extends('layouts.admin', ['title' => 'Add Delivery Agent'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Add Delivery Agent</h3>
    <a href="{{ route('admin.delivery-agents.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
</div>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card" style="max-width:600px">
    <div class="card-body">
        <form action="{{ route('admin.delivery-agents.store') }}" method="post">
            @csrf
            @include('admin.delivery_agents._form')
            <button class="btn btn-primary mt-3">Save Agent</button>
        </form>
    </div>
</div>
@endsection
