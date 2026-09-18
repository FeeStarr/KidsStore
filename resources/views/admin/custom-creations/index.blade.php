@extends('layouts.admin')

@section('title', 'Custom Creations')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Custom Creations</h1>
    <a href="{{ route('admin.custom-creations.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add Design
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        @if ($creations->isEmpty())
            <div class="text-center py-5">
                <i class="bi bi-stars display-1 text-muted"></i>
                <h4 class="mt-3 text-muted">No custom creations yet.</h4>
                <p class="text-muted">Add your first design to the public gallery.</p>
                <a href="{{ route('admin.custom-creations.create') }}" class="btn btn-primary mt-2">
                    <i class="bi bi-plus-lg me-1"></i> Add Design
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width:60px"></th>
                            <th>Title</th>
                            <th>Price</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($creations as $creation)
                            <tr>
                                <td>
                                    @if ($creation->image_path)
                                        <img src="{{ $creation->image_url }}" alt="{{ $creation->title }}" class="rounded" style="width:50px;height:50px;object-fit:cover;">
                                    @else
                                        <div class="bg-secondary rounded d-flex align-items-center justify-content-center" style="width:50px;height:50px;">
                                            <i class="bi bi-image text-white"></i>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $creation->title }}</strong>
                                    @if ($creation->description)
                                        <br><small class="text-muted">{{ Str::limit($creation->description, 60) }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if ($creation->price)
                                        @if ($creation->is_price_from)From @endif&#8358;{{ number_format($creation->price, 2) }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($creation->category && isset(\App\Models\CustomCreation::CATEGORIES[$creation->category]))
                                        <span class="badge bg-light text-dark">{{ \App\Models\CustomCreation::CATEGORIES[$creation->category] }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($creation->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('admin.custom-creations.edit', $creation) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form method="POST" action="{{ route('admin.custom-creations.toggle-active', $creation) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-{{ $creation->is_active ? 'warning' : 'success' }}">
                                                {{ $creation->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.custom-creations.destroy', $creation) }}" class="d-inline"
                                              data-confirm="Are you sure you want to delete this creation?" data-confirm-title="Delete Creation" data-confirm-yes="Delete">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $creations->links() }}
        @endif
    </div>
</div>
@endsection
