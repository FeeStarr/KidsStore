@extends('layouts.admin')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Categories</h3>
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Category</a>
</div>

<div class="card">
    <div class="card-body">
        @if($categories->isEmpty())
            <p class="text-muted mb-0">No categories yet.</p>
        @else
            <ul class="list-group list-group-flush">
                @foreach($categories as $cat)
                    @include('admin.categories._row', ['cat' => $cat, 'depth' => 0])
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
