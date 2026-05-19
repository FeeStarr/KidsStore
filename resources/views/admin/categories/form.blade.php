@extends('layouts.admin')
@section('content')
@php
    $isEdit = $category->exists;
    $action = $isEdit ? route('admin.categories.update', $category) : route('admin.categories.store');

    // Build hierarchical options from $parents (already excludes self+descendants on edit).
    $byParent = $parents->groupBy('parent_id');
    $renderOptions = function ($parentId, $depth) use (&$renderOptions, $byParent, $category) {
        $list = $byParent->get($parentId, collect());
        $html = '';
        foreach ($list as $opt) {
            $prefix = str_repeat('— ', $depth);
            $selected = (string) old('parent_id', $category->parent_id) === (string) $opt->id ? 'selected' : '';
            $html .= '<option value="'.$opt->id.'" '.$selected.'>'.e($prefix.$opt->name).'</option>';
            $html .= $renderOptions($opt->id, $depth + 1);
        }
        return $html;
    };
@endphp

<h3>{{ $isEdit ? 'Edit Category' : 'New Category' }}</h3>

<form method="post" action="{{ $action }}" class="card">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Name *</label>
                <input name="name" class="form-control" required value="{{ old('name', $category->name) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Slug</label>
                <input name="slug" class="form-control" value="{{ old('slug', $category->slug) }}" placeholder="auto from name">
            </div>
            <div class="col-md-6">
                <label class="form-label">Parent Category</label>
                <select name="parent_id" class="form-select">
                    <option value="">— Top level —</option>
                    {!! $renderOptions(null, 0) !!}
                </select>
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                           @checked(old('is_active', $category->is_active ?? true))>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description', $category->description) }}</textarea>
            </div>
        </div>
    </div>
    <div class="card-footer text-end">
        <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary">{{ $isEdit ? 'Update' : 'Create' }}</button>
    </div>
</form>
@endsection
