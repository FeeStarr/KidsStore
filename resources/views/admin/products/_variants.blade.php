@php
    $product->loadMissing('variants.inventory', 'variants.image', 'variants.images', 'variants.ageRange', 'variants.sizeRef', 'variants.colorRef', 'images');
    $images = $product->images;
@endphp

<div class="card mt-3" id="variantsCard">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-tags"></i> Variations <small class="text-muted">({{ $product->variants->count() }})</small></span>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addVariantModal">
            <i class="bi bi-plus-lg"></i> Add Variant
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:52px"></th>
                        <th>SKU</th>
                        <th>Name</th>
                        <th>Color</th>
                        <th>Size</th>
                        <th>Age Group</th>
                        <th>Options</th>
                        <th class="text-end">Price (₦)</th>
                        <th class="text-end">Disc %</th>
                        <th class="text-end">Stock</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($product->variants as $v)
                    @php
                        $vImg = $v->image?->url
                             ?? $v->images->first()?->url
                             ?? $product->images->first()?->url
                             ?? null;
                    @endphp
                    <tr>
                        <td class="ps-2">
                            @if($vImg)
                                <img src="{{ $vImg }}" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px" loading="lazy" decoding="async">
                            @else
                                <div class="bg-light d-flex align-items-center justify-content-center rounded" style="width:40px;height:40px"><i class="bi bi-image text-muted"></i></div>
                            @endif
                        </td>
                        <td><code>{{ $v->sku }}</code></td>
                        <td>{{ $v->name ?: '-' }}</td>
                        @php
                            $vColor = $v->colorRef?->name ?: $v->color;
                            $vSize = $v->sizeRef?->name ?: ($v->size ?: '-');
                            $vAge = $v->ageRange?->name ?: (!empty($v->age_group) ? implode(', ', (array) $v->age_group) : null);
                        @endphp
                        <td>
                            @if($vColor)
                                <span class="badge text-bg-info">{{ $vColor }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>{{ $vSize }}</td>
                        <td>
                            @if($vAge)
                                {{ $vAge }}
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @php $opts = $v->options ?? []; @endphp
                            @if(empty($opts))
                                <span class="text-muted">-</span>
                            @else
                                @foreach($opts as $k => $val)
                                    <span class="badge text-bg-light">{{ $k }}: {{ $val }}</span>
                                @endforeach
                            @endif
                        </td>
                        <td class="text-end">{{ number_format((float) $v->selling_price, 2) }}</td>
                        <td class="text-end">
                            {{ rtrim(rtrim(number_format((float) $v->discount, 2), '0'), '.') }}
                            @if((float) $product->discount > 0)
                                <small class="text-muted d-block">+ {{ rtrim(rtrim(number_format((float) $product->discount, 2), '0'), '.') }} product</small>
                            @endif
                        </td>
                        <td class="text-end">{{ $v->inventory->current_quantity ?? 0 }}</td>
                        <td>
                            @if($v->is_active)
                                <span class="badge text-bg-success">Active</span>
                            @else
                                <span class="badge text-bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal" data-bs-target="#editVariantModal-{{ $v->id }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form action="{{ route('admin.variants.destroy', $v) }}" method="post"
                                  class="d-inline confirm-delete">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center text-muted py-3">No variants yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Add variant modal --}}
<div class="modal fade" id="addVariantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" action="{{ route('admin.products.variants.store', $product) }}" method="post">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Add Variant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @include('admin.products._variant_fields', ['variant' => null, 'images' => $images])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Save Variant</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit variant modals --}}
@foreach($product->variants as $v)
    <div class="modal fade" id="editVariantModal-{{ $v->id }}" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" action="{{ route('admin.variants.update', $v) }}" method="post">
                @csrf @method('PUT')
                {{-- Identifies which edit form failed validation on redirect back --}}
                <input type="hidden" name="_editing_variant_id" value="{{ $v->id }}">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Variant - {{ $v->sku }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('admin.products._variant_fields', ['variant' => $v, 'images' => $images])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Update Variant</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

@push('scripts')
<script>
(function () {
    document.querySelectorAll('#variantsCard .confirm-delete').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (window.Swal) {
                Swal.fire({
                    title: 'Delete this variant?',
                    text: 'This cannot be undone.',
                    icon: 'warning', showCancelButton: true,
                    confirmButtonText: 'Delete', confirmButtonColor: '#dc3545',
                }).then(r => { if (r.isConfirmed) form.submit(); });
            } else if (confirm('Delete this variant?')) {
                form.submit();
            }
        });
    });

    // Dynamic options key/value rows in variant modals
    document.querySelectorAll('.variant-options-builder').forEach(function (root) {
        const tbody  = root.querySelector('.opt-rows');
        const addBtn = root.querySelector('.opt-add');
        function addRow(k = '', v = '') {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input class="form-control form-control-sm" name="option_keys[]" placeholder="e.g. Size" value="${k}"></td>
                <td><input class="form-control form-control-sm" name="option_values[]" placeholder="e.g. Medium" value="${v}"></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger opt-remove"><i class="bi bi-x"></i></button></td>`;
            tbody.appendChild(tr);
            tr.querySelector('.opt-remove').addEventListener('click', () => tr.remove());
        }
        addBtn.addEventListener('click', () => addRow());
        // Wire up existing remove buttons rendered by Blade
        tbody.querySelectorAll('.opt-remove').forEach(btn => {
            btn.addEventListener('click', () => btn.closest('tr').remove());
        });
        if (!tbody.children.length) addRow();
    });
})();
</script>
@endpush
