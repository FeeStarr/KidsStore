@extends('layouts.admin', ['title' => 'Edit Product'])
@section('content')
    <h3 class="mb-3">Edit Product — {{ $product->name }}</h3>
    @include('admin.products._form')
@endsection
