@extends('layouts.admin', ['title' => 'Edit Deal'])
@section('content')
<h3 class="mb-3">Edit Deal</h3>
@include('admin.deals._form', ['deal' => $deal])
@endsection
