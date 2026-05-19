@extends('layouts.admin', ['title' => 'New Product'])
@section('content')
    <h3 class="mb-3">New Product</h3>
    @include('admin.products._form')
@endsection
