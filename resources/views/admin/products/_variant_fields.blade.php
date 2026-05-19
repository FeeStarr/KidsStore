@php
    // Use old() for:
    //   • Add modal  ($variant === null): only when the failed submission was the Add form
    //     (detected by absence of _editing_variant_id in old() data).
    //   • Edit modal ($variant set): only when _this_ variant's edit form failed validation
    //     (detected by _editing_variant_id matching this variant's ID).
    // This ensures no cross-modal contamination: Add ≠ Edit, and Edit A ≠ Edit B.
    if ($variant === null) {
        $useOld = is_null(old('_editing_variant_id'));  // null = came from Add form (or fresh)
    } else {
        $useOld = (old('_editing_variant_id') == $variant->id);
    }

    if ($useOld && old('option_keys')) {
        $rawKeys = array_values(array_filter((array) old('option_keys'), fn($k) => trim($k) !== ''));
        $rawVals = array_values((array) old('option_values', []));
        // Pad values to same length, then zip.
        while (count($rawVals) < count($rawKeys)) $rawVals[] = '';
        $allOpts = array_combine($rawKeys, array_slice($rawVals, 0, count($rawKeys)));
    } else {
        $allOpts = (array) ($variant?->options ?? []);
    }

    // Pre-compute all field values using $useOld to pick old() vs DB.
    $fSku          = $useOld ? old('sku',          $variant?->sku           ?? '') : ($variant->sku ?? '');
    $fName         = $useOld ? old('name',         $variant?->name          ?? '') : ($variant->name ?? '');
    $fSellingPrice = $useOld ? old('selling_price',$variant?->selling_price ?? '') : ($variant->selling_price ?? '');
    $fDiscount     = $useOld ? old('discount',     $variant?->discount      ?? 0)  : ($variant->discount ?? 0);
    $fImageId      = $useOld ? old('image_id',     $variant?->image_id)             : $variant->image_id;
    $fIsActive     = $useOld
        ? (old('is_active') !== null ? (bool) old('is_active') : (bool) ($variant?->is_active ?? true))
        : (bool) ($variant->is_active ?? true);
    $assignedIds   = $useOld
        ? old('image_ids', $variant?->images?->pluck('id')->all() ?? [])
        : ($variant?->images?->pluck('id')->all() ?? []);

    // Case-insensitive lookup for Color and Size so variants saved with any casing work.
    $knownStyleKey = 'Color';
    $knownSizeKey  = 'Size';

    $currentColor = '';
    $currentSize  = '';
    $extraOpts    = [];
    foreach ($allOpts as $k => $v) {
        $lk = strtolower((string) $k);
        if ($lk === 'color' || $lk === 'colour')  { $currentColor = (string) $v; }
        elseif ($lk === 'size')                    { $currentSize  = (string) $v; }
        else                                       { $extraOpts[$k] = $v; }
    }

    $sizeOptions = [
        ''        => '— None —',
        'Newborn' => 'Newborn',
        '0-3M'    => '0-3M',
        '3-6M'    => '3-6M',
        '6-9M'    => '6-9M',
        '9-12M'   => '9-12M',
        '12-18M'  => '12-18M',
        '18-24M'  => '18-24M',
        '2T'      => '2T',
        '3T'      => '3T',
        '4T'      => '4T',
        '5T'      => '5T',
        '4'       => '4',
        '5'       => '5',
        '6'       => '6',
        '6X'      => '6X',
        '7'       => '7',
        '8'       => '8',
        '10'      => '10',
        '12'      => '12',
        '14'      => '14',
        '16'      => '16',
    ];
    // If the variant has a saved size that isn't in the predefined list, add it so it stays selected.
    if ($currentSize !== '' && ! array_key_exists($currentSize, $sizeOptions)) {
        $sizeOptions[$currentSize] = $currentSize . ' (custom)';
    }
@endphp
<div class="row g-3">
    {{-- ── Identity ──────────────────────────────────────────────────────── --}}
    <div class="col-md-4">
        <label class="form-label">SKU *</label>
        <input name="sku" class="form-control" required value="{{ $fSku }}">
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
                {{-- Color / Style --}}
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="bi bi-palette2 me-1"></i>Color / Style
                        <small class="text-muted">(e.g. Red, Blue, Striped — groups thumbnails on product page)</small>
                    </label>
                    {{-- Hidden option_keys/values pair for Color --}}
                    <input type="hidden" name="option_keys[]" value="{{ $knownStyleKey }}">
                    <input type="text" name="option_values[]" class="form-control"
                           placeholder="e.g. Red, Blue, Polka-dot …"
                           value="{{ $currentColor }}"
                           id="colorInput_{{ $variant?->id ?? 'new' }}">                    <div class="d-flex flex-wrap gap-1 mt-2">
                        @foreach(['Red','Blue','Green','Yellow','Pink','Purple','White','Black','Orange','Grey','Navy','Brown','Multicolor'] as $c)
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill color-preset"
                                    data-target="colorInput_{{ $variant?->id ?? 'new' }}"
                                    data-val="{{ $c }}">{{ $c }}</button>
                        @endforeach
                    </div>
                </div>

                {{-- Size --}}
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="bi bi-rulers me-1"></i>Size
                        <small class="text-muted">(shown as selectable pills on product page)</small>
                    </label>
                    {{-- Hidden option_keys/values pair for Size --}}
                    <input type="hidden" name="option_keys[]" value="{{ $knownSizeKey }}">
                    @php $sizeId = 'sizeSelect_' . ($variant?->id ?? 'new'); @endphp
                    <select name="option_values[]" class="form-select" id="{{ $sizeId }}"
                            onchange="document.getElementById('customSize_{{ $variant?->id ?? 'new' }}').style.display = this.value === '__custom__' ? '' : 'none';">
                        @foreach($sizeOptions as $val => $label)
                            <option value="{{ $val }}" @selected($currentSize === $val)>
                                {{ $label }}
                            </option>
                        @endforeach
                        <option value="__custom__">Other (type below)…</option>
                    </select>
                    <input type="text" id="customSize_{{ $variant?->id ?? 'new' }}"
                           class="form-control mt-1" placeholder="Enter custom size"
                           style="display:none"
                           oninput="document.getElementById('{{ $sizeId }}').value = this.value || '__custom__'">
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
    // Color preset buttons — click to fill the color input.
    document.querySelectorAll('.color-preset').forEach(btn => {
        btn.addEventListener('click', () => {
            const inp = document.getElementById(btn.dataset.target);
            if (inp) inp.value = btn.dataset.val;
        });
    });

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
})();
</script>
@endpush
