@php
    $isEdit = isset($product);
    $action = $isEdit ? route('admin.products.update', $product) : route('admin.products.store');
@endphp
<form action="{{ $action }}" method="post" enctype="multipart/form-data">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header">Basic Info</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Name *</label>
                        <input name="name" class="form-control" value="{{ old('name', $product->name ?? '') }}" required></div>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">SKU</label>
                            <input name="sku" class="form-control" value="{{ old('sku', $product->sku ?? '') }}"></div>
                        @php
                            $selectedCat = old('category_id', $product->category_id ?? null);
                            $byParent    = $categories->groupBy('parent_id');
                            $catsById    = $categories->keyBy('id');
                            // Determine top-level + subcategory chain for the currently selected leaf
                            $selectedTop = null; $selectedSub = null;
                            if ($selectedCat && ($leaf = $catsById->get($selectedCat))) {
                                if ($leaf->parent_id) {
                                    $selectedTop = $leaf->parent_id;
                                    $selectedSub = $leaf->id;
                                } else {
                                    $selectedTop = $leaf->id;
                                }
                            }
                            // Build subcategory map keyed by parent id => [{id,name}, ...]
                            $subMap = [];
                            foreach ($byParent as $pid => $list) {
                                if ($pid === null || $pid === '') continue;
                                $subMap[$pid] = $list->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values();
                            }
                        @endphp
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select id="catTop" class="form-select">
                                <option value="">—</option>
                                @foreach($byParent->get(null, collect()) as $top)
                                    <option value="{{ $top->id }}" @selected((int) $selectedTop === $top->id)>{{ $top->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Subcategory <small class="text-muted">(optional)</small></label>
                            <select id="catSub" class="form-select">
                                <option value="">—</option>
                            </select>
                            <input type="hidden" name="category_id" id="categoryIdInput" value="{{ $selectedCat }}">
                        </div>
                    </div>
                    @push('scripts')
                    <script>
                        (function () {
                            const subMap = @json($subMap);
                            const top = document.getElementById('catTop');
                            const sub = document.getElementById('catSub');
                            const hidden = document.getElementById('categoryIdInput');
                            const initialSub = @json($selectedSub);

                            function populateSub(selectedSubId) {
                                sub.innerHTML = '<option value="">—</option>';
                                const list = subMap[top.value] || [];
                                if (!list.length) {
                                    sub.disabled = true;
                                    return;
                                }
                                sub.disabled = false;
                                list.forEach(function (c) {
                                    const opt = document.createElement('option');
                                    opt.value = c.id;
                                    opt.textContent = c.name;
                                    if (selectedSubId && String(selectedSubId) === String(c.id)) opt.selected = true;
                                    sub.appendChild(opt);
                                });
                            }

                            function syncHidden() {
                                hidden.value = sub.value || top.value || '';
                            }

                            top.addEventListener('change', function () {
                                populateSub(null);
                                syncHidden();
                            });
                            sub.addEventListener('change', syncHidden);

                            populateSub(initialSub);
                            syncHidden();
                        })();
                    </script>
                    @endpush
                    <div class="row g-3 mt-1">
                        <div class="col-md-4"><label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                @foreach(['' => '-','boy'=>'Boy','girl'=>'Girl','unisex'=>'Unisex'] as $k=>$v)
                                    <option value="{{ $k }}" @selected(old('gender', $product->gender ?? '') === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4"><label class="form-label">Brand (Catalog)</label>
                            <select name="brand_id" class="form-select">
                                <option value="">—</option>
                                @foreach(($brands ?? collect()) as $b)
                                    <option value="{{ $b->id }}" @selected((string) old('brand_id', $product->brand_id ?? '') === (string) $b->id)>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4"><label class="form-label">Brand Label</label>
                            <input name="brand" class="form-control" value="{{ old('brand', $product->brand ?? '') }}" placeholder="Optional display text"></div>
                    </div>
                    <div class="mt-3"><label class="form-label">Description</label>
                        <textarea name="description" rows="4" class="form-control">{{ old('description', $product->description ?? '') }}</textarea></div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Images (multiple)</div>
                <div class="card-body">
                    <input type="file" name="images[]" multiple accept="image/*" class="form-control mb-3">
                    <small class="text-muted">You can upload multiple images. The first one becomes the primary by default.</small>

                    @if($isEdit && $product->images->count())
                        <div class="row g-3 mt-3">
                            @foreach($product->images as $img)
                                <div class="col-md-3">
                                    <div class="card">
                                        <img src="{{ $img->url }}" class="card-img-top" style="height:120px;object-fit:cover">
                                        <div class="card-body p-2 text-center">
                                            @if($img->is_primary)
                                                <span class="badge text-bg-success">Primary</span>
                                            @else
                                                <button form="primary-{{ $img->id }}" class="btn btn-sm btn-outline-success">Set Primary</button>
                                            @endif
                                            <label class="d-block mt-2 small text-danger">
                                                <input type="checkbox" name="delete_images[]" value="{{ $img->id }}"> delete
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @foreach($product->images as $img)
                            @if(!$img->is_primary)
                                <form id="primary-{{ $img->id }}" action="{{ route('admin.products.images.primary', [$product, $img->id]) }}" method="post" class="d-none">@csrf</form>
                            @endif
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header">Pricing</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Selling Price <small class="text-muted">(can be set later from a Purchase)</small></label>
                        <input type="number" step="0.01" name="selling_price" class="form-control"
                               value="{{ old('selling_price', $product->selling_price ?? '') }}"
                               placeholder="Leave blank — auto-set when first purchase is received"></div>
                    <div class="mb-3"><label class="form-label">Cost Price <small class="text-muted">(auto-updated when a purchase is received)</small></label>
                        <input type="number" step="0.01" min="0" name="cost_price" class="form-control"
                               value="{{ old('cost_price', $product->cost_price ?? '') }}"
                               placeholder="Auto-set from purchase"></div>
                    <div class="mb-3"><label class="form-label">Discount (%) <small class="text-muted">(applies to all variants for this product)</small></label>
                        <input type="number" step="0.01" min="0" max="100" name="discount" class="form-control"
                               value="{{ old('discount', $product->discount ?? 0) }}"></div>
                    <div class="mb-3"><label class="form-label">Status</label>
                        <select name="status" id="productStatus" class="form-select">
                            @foreach(['active' => 'Active', 'inactive' => 'Inactive', 'draft' => 'Draft'] as $key => $label)
                                <option value="{{ $key }}" @selected(old('status', $product->status ?? (($product->is_active ?? true) ? 'active' : 'inactive')) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="is_active" id="productIsActive" value="{{ old('status', $product->status ?? (($product->is_active ?? true) ? 'active' : 'inactive')) === 'active' ? 1 : 0 }}">
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_returnable" value="1" class="form-check-input"
                                   role="switch" id="isReturnable"
                                   {{ old('is_returnable', $product->is_returnable ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isReturnable">Returnable</label>
                        </div>
                        <div class="form-text">Disable to prevent customers from requesting refunds on this product.</div>
                    </div>
                </div>
            </div>

            <button class="btn btn-primary w-100">{{ $isEdit ? 'Update Product' : 'Create Product' }}</button>
            <a href="{{ route('admin.products.index') }}" class="btn btn-link w-100">Cancel</a>
        </div>
    </div>
</form>

@if($isEdit)
    @include('admin.products._variants', ['product' => $product])
@endif

@push('scripts')
<script>
(function () {
    const status = document.getElementById('productStatus');
    const active = document.getElementById('productIsActive');
    if (!status || !active) return;
    const sync = () => { active.value = status.value === 'active' ? '1' : '0'; };
    status.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
