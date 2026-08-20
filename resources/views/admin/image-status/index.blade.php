@extends('layouts.admin')

@section('title', 'Image Optimization Status')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-image"></i> Image Optimization Status</h4>
    <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">← Dashboard</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-2">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <h3 class="text-primary mb-0">{{ $stats['total'] }}</h3>
                <small class="text-muted">Total Images</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <h3 class="text-success mb-0">{{ $stats['compressed'] }}</h3>
                <small class="text-muted">Below Threshold</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <h3 class="text-info mb-0">{{ $stats['has_webp'] }}</h3>
                <small class="text-muted">Have WebP</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <h3 class="text-warning mb-0">{{ $stats['has_srcset'] }}</h3>
                <small class="text-muted">Have Srcset</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <h3 class="mb-0">{{ number_format($stats['total_size'] / 1024 / 1024, 1) }} MB</h3>
                <small class="text-muted">Total Size (PNG)</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Image</th>
                        <th>PNG Size</th>
                        <th>WebP Size</th>
                        <th>Savings</th>
                        <th>WebP</th>
                        <th>400px</th>
                        <th>800px</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stats['details'] as $i => $d)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            <code class="small">{{ basename($d['path']) }}</code>
                        </td>
                        <td>{{ number_format($d['size'] / 1024, 1) }} KB</td>
                        <td>{{ $d['has_webp'] ? number_format($d['webp_size'] / 1024, 1) . ' KB' : '—' }}</td>
                        <td>
                            @if ($d['has_webp'] && $d['size'] > 0)
                                @php $savings = round((1 - $d['webp_size'] / $d['size']) * 100); @endphp
                                <span class="badge {{ $savings > 50 ? 'bg-success' : ($savings > 20 ? 'bg-warning' : 'bg-secondary') }}">
                                    {{ $savings }}% smaller
                                </span>
                            @else
                                <span class="badge bg-danger">No WebP</span>
                            @endif
                        </td>
                        <td>
                            @if ($d['has_webp'])
                                <i class="bi bi-check-circle-fill text-success"></i>
                            @else
                                <i class="bi bi-x-circle-fill text-danger"></i>
                            @endif
                        </td>
                        <td>
                            @if ($d['has_400'])
                                <i class="bi bi-check-circle-fill text-success"></i>
                            @else
                                <i class="bi bi-x-circle-fill text-danger"></i>
                            @endif
                        </td>
                        <td>
                            @if ($d['has_800'])
                                <i class="bi bi-check-circle-fill text-success"></i>
                            @else
                                <i class="bi bi-x-circle-fill text-danger"></i>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No product images found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
