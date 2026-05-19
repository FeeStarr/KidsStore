<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductVariantRequest;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    public function store(ProductVariantRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product) {
            $data = $this->prepareData($request, $product);
            $variant = $product->variants()->create($data);
            // Auto-create inventory row for the new variant.
            Inventory::create([
                'product_id'         => $product->id,
                'product_variant_id' => $variant->id,
                'quantity'           => 0,
                'reorder_level'      => 5,
            ]);
            $this->syncGallery($variant, $request->input('image_ids', []));
        });

        return back()->with('success', 'Variant added.');
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
        if ($product->variants()->count() <= 1) {
            return back()->with('error', 'A product must have at least one variant.');
        }
        if ($variant->purchaseItems()->exists() || $variant->orderItems()->exists()) {
            return back()->with('error', 'Cannot delete a variant that has purchase or order history.');
        }
        $variant->delete();

        return back()->with('success', 'Variant deleted.');
    }

    /**
     * Build clean payload from request (parses options keys/values).
     */
    private function prepareData(ProductVariantRequest $request, Product $product): array
    {
        $data = $request->validated();

        // Inherit selling price from parent product when blank.
        if ($data['selling_price'] === null || $data['selling_price'] === '') {
            $data['selling_price'] = $product->selling_price;
        }

        // Options come from form as parallel arrays of keys + values; convert to map.
        $options = [];
        $keys   = $request->input('option_keys', []);
        $values = $request->input('option_values', []);
        foreach ($keys as $i => $k) {
            $k = trim((string) $k);
            $v = trim((string) ($values[$i] ?? ''));
            if ($k !== '' && $v !== '') {
                $options[$k] = $v;
            }
        }
        $data['options'] = $options ?: null;

        // image_ids handled separately via syncGallery().
        unset($data['image_ids']);

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
