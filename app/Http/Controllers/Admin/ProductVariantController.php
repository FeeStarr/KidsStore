<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductVariantRequest;
use App\Models\Inventory;
use App\Models\AgeRange;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductService;
use App\Models\Size;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    public function store(ProductVariantRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product) {
            $data    = $this->prepareData($request, $product);

            // Ensure SKU exists: allow blank in form and auto-generate here for admin-created variants
            if (empty($data['sku'])) {
                $svc = new ProductService();
                $candidate = $svc->generateVariantSku($product, $data);
                $data['sku'] = $svc->ensureUniqueSku($candidate);
            }

            $variant = $product->variants()->create($data);

            Inventory::create([
                'product_id'         => $product->id,
                'product_variant_id' => $variant->id,
                'quantity'           => 0,
                'reorder_level'      => 5,
            ]);

            $this->syncGallery($variant, $request->input('image_ids', []));

            $product->refreshStock();
        });

        return back()->with('success', 'Variant added.');
    }

    private function ensureUniqueSku(?string $sku): string
    {
        $sku = trim((string) ($sku ?? ''));
        if ($sku === '') {
            $sku = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        }

        $base = $sku;
        $i    = 0;
        while (ProductVariant::where('sku', $sku)->exists()) {
            $i++;
            $sku = $base . '-' . $i;
        }

        return $sku;
    }

    public function update(ProductVariantRequest $request, ProductVariant $variant): RedirectResponse
    {
        DB::transaction(function () use ($request, $variant) {
            $variant->update($this->prepareData($request, $variant->product));
            $this->syncGallery($variant, $request->input('image_ids', []));
        });

        return back()->with('success', 'Variant updated.');
    }

    public function destroy(ProductVariant $variant): RedirectResponse
    {
        $product = $variant->product;
        if ($variant->purchaseItems()->exists() || $variant->orderItems()->exists()) {
            return back()->with('error', 'Cannot delete a variant that has purchase or order history.');
        }
        $variant->delete();
        $product->refreshStock();

        return back()->with('success', 'Variant deleted.');
    }

    public function show(ProductVariant $variant): RedirectResponse
    {
        // Redirect to the parent product's edit page — variants are managed from there.
        return redirect()->route('admin.products.edit', $variant->product);
    }

    /**
     * Build clean payload from the request.
     * FKs (color_id, size_id, age_range_id) are the canonical source of truth.
     * If only the FK is supplied the name is resolved for any legacy display fields;
     * if a free-text name arrives without a FK we auto-create the lookup record.
     */
    private function prepareData(ProductVariantRequest $request, Product $product): array
    {
        $data = $request->validated();

        // Inherit selling price from parent product when blank.
        if ($data['selling_price'] === null || $data['selling_price'] === '') {
            $data['selling_price'] = $product->selling_price;
        }

        // Resolve color FK from free-text fallback if needed.
        $colorText = $data['color_name'] ?? $data['color_text'] ?? null;
        if (empty($data['color_id']) && ! empty($colorText)) {
            $data['color_id'] = Color::query()
                ->firstOrCreate(['name' => trim((string) $colorText)], ['is_active' => true])
                ->id;
        }

        // Resolve size FK from free-text fallback if needed.
        $sizeText = $data['size_name'] ?? $data['size_text'] ?? null;
        if (empty($data['size_id']) && ! empty($sizeText)) {
            $data['size_id'] = Size::query()
                ->firstOrCreate(['name' => trim((string) $sizeText)], ['is_active' => true])
                ->id;
        }

        // Options come from form as parallel arrays of keys + values; convert to map.
        $options = [];
        $keys    = $request->input('option_keys', []);
        $values  = $request->input('option_values', []);
        foreach ($keys as $i => $k) {
            $k = trim((string) $k);
            $v = trim((string) ($values[$i] ?? ''));
            if ($k !== '' && $v !== '') {
                $options[$k] = $v;
            }
        }
        $data['options'] = $options ?: null;

        // image_ids handled separately via syncGallery().
        unset($data['image_ids'], $data['color_name'], $data['size_name']);

        return $data;
    }

    /**
     * Tag the given product images as belonging to this variant; un-tag any image
     * previously owned by this variant that wasn't selected.
     *
     * @param  array<int,int>  $imageIds
     */
    private function syncGallery(ProductVariant $variant, array $imageIds): void
    {
        $imageIds = array_filter(array_map('intval', $imageIds));
        $product  = $variant->product;

        // Clear ownership on images previously owned by this variant.
        $product->images()
            ->where('product_variant_id', $variant->id)
            ->whereNotIn('id', $imageIds ?: [0])
            ->update(['product_variant_id' => null]);

        // Tag selected images to this variant (only those that belong to the parent product).
        if ($imageIds) {
            $product->images()
                ->whereIn('id', $imageIds)
                ->update(['product_variant_id' => $variant->id]);
        }
    }
}
