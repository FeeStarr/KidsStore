@extends('layouts.admin', ['title' => 'Edit Product'])
@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h3 class="mb-0">Edit Product - {{ $product->name }}</h3>
        <a href="{{ route('admin.product-reviews.index', ['product_id' => $product->id]) }}" class="btn btn-sm btn-outline-warning">
            <i class="bi bi-star-fill"></i> Reviews ({{ $product->reviews()->count() }})
        </a>
    </div>
    @include('admin.products._form')
@endsection
