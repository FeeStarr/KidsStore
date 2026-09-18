@extends('layouts.admin')

@section('title', 'Edit Custom Creation')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Edit Custom Creation</h1>
    <a href="{{ route('admin.custom-creations.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <form method="POST" action="{{ route('admin.custom-creations.update', $creation) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0">Design Details</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $creation->title) }}" required maxlength="255">
                        @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Image</label>
                        @if ($creation->image_path)
                            <div class="mb-2">
                                <img src="{{ $creation->image_url }}" class="img-thumbnail" style="max-height:200px;" alt="{{ $creation->title }}">
                            </div>
                        @endif
                        <input type="file" name="image" class="form-control @error('image') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                        @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div id="image-preview" class="mt-2" style="display:none;">
                            <img id="preview-img" class="img-thumbnail" style="max-height:200px;">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Price</label>
                            <div class="input-group">
                                <span class="input-group-text">&#8358;</span>
                                <input type="number" name="price" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $creation->price) }}" step="0.01" min="0">
                            </div>
                            @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Category</label>
                            <select name="category" class="form-select @error('category') is-invalid @enderror">
                                <option value="">No category</option>
                                @foreach ($categories as $key => $label)
                                    <option value="{{ $key }}" {{ old('category', $creation->category) === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="is_price_from" value="1" id="isPriceFrom" {{ old('is_price_from', $creation->is_price_from) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isPriceFrom">Display as "From &#8358;X,XXX"</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" {{ old('is_active', $creation->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isActive">Active (visible publicly)</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3" maxlength="1000">{{ old('description', $creation->description) }}</textarea>
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $creation->sort_order) }}" min="0">
                        <small class="text-muted">Lower numbers appear first.</small>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Update Creation</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelector('[name="image"]')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            document.getElementById('preview-img').src = ev.target.result;
            document.getElementById('image-preview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});
</script>
@endpush
