@php
    // Use old() for:
    //   • Add modal  ($variant === null): only when the failed submission was the Add form
    //     (detected by absence of _editing_variant_id in old() data).
    //   • Edit modal ($variant set): only when _this_ variant's edit form failed validation
    //     (detected by _editing_variant_id matching this variant's ID).
    if ($variant === null) {
        $useOld = is_null(old('_editing_variant_id'));
    } else {
        $useOld = (old('_editing_variant_id') == $variant->id);
    }

    if ($useOld && old('option_keys')) {
        $rawKeys = array_values(array_filter((array) old('option_keys'), fn($k) => trim($k) !== ''));
        $rawVals = array_values((array) old('option_values', []));
        while (count($rawVals) < count($rawKeys)) $rawVals[] = '';
        $allOpts = array_combine($rawKeys, array_slice($rawVals, 0, count($rawKeys)));
    } else {
        $allOpts = (array) ($variant?->options ?? []);
    }

    $fSku          = $useOld ? old('sku',          $variant?->sku           ?? '') : ($variant?->sku ?? '');
    $fName         = $useOld ? old('name',         $variant?->name          ?? '') : ($variant?->name ?? '');
    $fColorId      = $useOld ? old('color_id',     $variant?->color_id      ?? '') : ($variant?->color_id ?? '');
    $fSizeId       = $useOld ? old('size_id',      $variant?->size_id       ?? '') : ($variant?->size_id ?? '');
    $fColorText    = $useOld ? old('color_text',   $variant?->colorRef?->name ?? '') : ($variant?->colorRef?->name ?? '');
    $fSizeText     = $useOld ? old('size_text',    $variant?->sizeRef?->name  ?? '') : ($variant?->sizeRef?->name ?? '');
    $fAgeRangeId   = $useOld ? old('age_range_id', $variant?->age_range_id  ?? '') : ($variant?->age_range_id ?? '');
    $fAgeRangeIds  = $useOld ? old('age_range_ids', []) : [];
    $fSellingPrice = $useOld ? old('selling_price',$variant?->selling_price ?? '') : ($variant?->selling_price ?? '');
    $fDiscount     = $useOld ? old('discount',     $variant?->discount      ?? 0)  : ($variant?->discount ?? 0);
    $fImageId      = $useOld ? old('image_id',     $variant?->image_id)             : ($variant?->image_id ?? null);
    $fIsActive     = $useOld
        ? (old('is_active') !== null ? (bool) old('is_active') : (bool) ($variant?->is_active ?? true))
        : (bool) ($variant?->is_active ?? true);
    $assignedIds   = $useOld
        ? old('image_ids', $variant?->images?->pluck('id')->all() ?? [])
        : ($variant?->images?->pluck('id')->all() ?? []);

    $colorOptions = $colors ?? collect();
    $sizeOptions  = $sizes ?? collect();
    $ageOptions   = $ageRanges ?? collect();

    $isEdit = $variant !== null;

    // Extra options — exclude reserved keys
    $extraOpts = [];
    foreach ($allOpts as $k => $v) {
        $extraOpts[$k] = $v;
    }
@endphp
<div class="row g-3">
    {{-- ── Identity ──────────────────────────────────────────────────────── --}}
    <div class="col-md-4">
        <label class="form-label">SKU <small class="text-muted">(leave blank to auto-generate)</small></label>
        <input name="sku" class="form-control" value="{{ $fSku }}" placeholder="Auto-generated if blank">
    </div>
    <div class="col-md-8">
        <label class="form-label">Variant Name <small class="text-muted">(optional — auto-built from options if blank)</small></label>
        <input name="name" class="form-control" value="{{ $fName }}">
    </div>

    {{-- ── Pricing ────────────────────────────────────────────────────────── --}}
    <div class="col-md-4">
        <label class="form-label">Selling Price (₦) <small class="text-muted">(blank = inherit ₦{{ number_format($product->selling_price ?? 0, 2) }})</small></label>
        <input type="number" step="0.01" min="0" name="selling_price" class="form-control"
               placeholder="{{ number_format($product->selling_price ?? 0, 2) }}"
               value="{{ $fSellingPrice }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Discount (%)</label>
        <input type="number" step="0.01" min="0" max="100" name="discount" class="form-control"
               value="{{ $fDiscount }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Primary Image <small class="text-muted">(thumbnail in picker)</small></label>
        <select name="image_id" class="form-select">
            <option value="">— Use product image —</option>
            @foreach($images as $img)
                <option value="{{ $img->id }}" @selected($fImageId == $img->id)>
                    {{ $img->original_name ?? ('Image #'.$img->id) }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- ── Variant attributes ─────────────────────────────────────────────── --}}
    <div class="col-12">
        <label class="form-label fw-semibold">Variant Attributes</label>
        <div class="border rounded p-3 bg-light">
            <div class="row g-3">
                {{-- Color --}}
                <div class="col-md-4">
                    <label class="form-label"><i class="bi bi-palette2 me-1"></i>Color</label>
                    <select name="color_id" class="form-select">
                        <option value="">— Select Color —</option>
                        @foreach($colorOptions as $opt)
                            <option value="{{ $opt->id }}" @selected((string) $fColorId === (string) $opt->id)>{{ $opt->name }}</option>
                        @endforeach
                    </select>
                    <input name="color_text" class="form-control form-control-sm mt-1" placeholder="Other colour (free text)" value="{{ $fColorText }}">
                </div>

                {{-- Age Range --}}
                <div class="col-md-4">
                    @if($isEdit)
                        <label class="form-label"><i class="bi bi-people me-1"></i>Age Range</label>
                        <select name="age_range_id" class="form-select">
                            <option value="">— Select Age Range —</option>
                            @foreach($ageOptions as $age)
                                <option value="{{ $age->id }}" @selected((string) $fAgeRangeId === (string) $age->id)>{{ $age->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <label class="form-label"><i class="bi bi-people me-1"></i>Age Range <small class="text-muted">(select multiple to create one variant per age)</small></label>
                        <select name="age_range_ids[]" class="form-select" multiple size="4">
                            @foreach($ageOptions as $age)
                                <option value="{{ $age->id }}" @selected(in_array((string) $age->id, $fAgeRangeIds))>{{ $age->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Hold Ctrl/Cmd to select multiple. One variant will be created per age range (same price, color, size).</div>
                    @endif
                </div>

                {{-- Size --}}
                <div class="col-md-4">
                    <label class="form-label"><i class="bi bi-rulers me-1"></i>Size</label>
                    <select name="size_id" class="form-select">
                        <option value="">— Select Size —</option>
                        @foreach($sizeOptions as $opt)
                            <option value="{{ $opt->id }}" @selected((string) $fSizeId === (string) $opt->id)>{{ $opt->name }}</option>
                        @endforeach
                    </select>
                    <input name="size_text" class="form-control form-control-sm mt-1" placeholder="Other size (free text)" value="{{ $fSizeText }}">
                    <div class="form-text">e.g. S, M, L, XL or 28, 30, 32</div>
                </div>
            </div>

            {{-- Extra / custom options ──────────────────────────────────── --}}
            <div class="mt-3">
                <label class="form-label small text-muted">Additional options (optional)</label>
                <table class="table table-sm mb-2" id="extraOptTable_{{ $variant?->id ?? 'new' }}">
                    <thead class="table-light"><tr>
                        <th style="width:42%">Attribute</th>
                        <th style="width:50%">Value</th>
                        <th></th>
                    </tr></thead>
                    <tbody class="opt-rows">
                        @foreach($extraOpts as $k => $v)
                            <tr>
                                <td><input class="form-control form-control-sm" name="option_keys[]" value="{{ $k }}" placeholder="e.g. Material"></td>
                                <td><input class="form-control form-control-sm" name="option_values[]" value="{{ $v }}" placeholder="e.g. Cotton"></td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger opt-remove"><i class="bi bi-x"></i></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <button type="button" class="btn btn-sm btn-outline-secondary opt-add"
                        data-table="extraOptTable_{{ $variant?->id ?? 'new' }}">
                    <i class="bi bi-plus-lg"></i> Add custom option
                </button>
            </div>
        </div>
    </div>

    {{-- ── Gallery ─────────────────────────────────────────────────────────── --}}
    <div class="col-12">
        <label class="form-label d-block">Variant Gallery <small class="text-muted">(tick all images showing this colour/style — they'll swap in the carousel when this variant is selected)</small></label>
        @if($images->isEmpty())
            <div class="text-muted small">Upload product images first, then assign them here.</div>
        @else
            <div class="row g-2">
                @foreach($images as $img)
                    @php
                        $checked      = in_array($img->id, (array) $assignedIds);
                        $ownerId      = $img->product_variant_id;
                        $ownedByOther = $ownerId && (! $variant || $ownerId !== $variant->id);
                    @endphp
                    <div class="col-6 col-md-3">
                        <label class="d-block border rounded p-1 position-relative {{ $checked ? 'border-primary' : '' }}" style="cursor:pointer">
                            <input type="checkbox" name="image_ids[]" value="{{ $img->id }}"
                                   class="form-check-input position-absolute top-0 start-0 m-1"
                                   @checked($checked)>
                            <img src="{{ $img->url }}" class="img-fluid rounded" style="aspect-ratio:1/1;object-fit:cover">
                            @if($ownedByOther)
                                <small class="d-block text-muted text-truncate">used by another variant</small>
                            @endif
                        </label>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ── Status ──────────────────────────────────────────────────────────── --}}
    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                   @checked($fIsActive)>
            <label class="form-check-label">Active</label>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    // "Add custom option" — inserts a new row into the extra-options table.
    document.querySelectorAll('.opt-add').forEach(addBtn => {
        addBtn.addEventListener('click', () => {
            const tableId = addBtn.dataset.table || null;
            const tbody = tableId
                ? document.querySelector('#' + tableId + ' .opt-rows')
                : addBtn.closest('.variant-options-builder')?.querySelector('.opt-rows');
            if (!tbody) return;
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td><input class="form-control form-control-sm" name="option_keys[]" placeholder="e.g. Material"></td>' +
                '<td><input class="form-control form-control-sm" name="option_values[]" placeholder="e.g. Cotton"></td>' +
                '<td><button type="button" class="btn btn-sm btn-outline-danger opt-remove"><i class="bi bi-x"></i></button></td>';
            tbody.appendChild(tr);
        });
    });

    // Remove row — delegated on the whole document to cover dynamically added rows.
    document.addEventListener('click', e => {
        if (e.target.closest('.opt-remove')) {
            e.target.closest('tr').remove();
        }
    });

        // Sync selects with free-text inputs: typing clears select, choosing select clears text
        document.querySelectorAll('select[name="color_id"]').forEach(sel => {
            sel.addEventListener('change', () => {
                const txt = sel.closest('.row')?.querySelector('input[name="color_text"]');
                if (txt) txt.value = '';
            });
        });
        document.querySelectorAll('input[name="color_text"]').forEach(inp => {
            inp.addEventListener('input', () => {
                const sel = inp.closest('.row')?.querySelector('select[name="color_id"]');
                if (sel && inp.value.trim() !== '') sel.value = '';
            });
        });

        document.querySelectorAll('select[name="size_id"]').forEach(sel => {
            sel.addEventListener('change', () => {
                const txt = sel.closest('.row')?.querySelector('input[name="size_text"]');
                if (txt) txt.value = '';
            });
        });
        document.querySelectorAll('input[name="size_text"]').forEach(inp => {
            inp.addEventListener('input', () => {
                const sel = inp.closest('.row')?.querySelector('select[name="size_id"]');
                if (sel && inp.value.trim() !== '') sel.value = '';
            });
        });
})();
</script>
@endpush
