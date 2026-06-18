<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Brand;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles product persistence and multi-image management.
 */
class ProductService
{
    private const IMAGE_DISK = 'public';
    private const IMAGE_DIR = 'products';

    /**
     * @param array<string,mixed>      $data
     * @param array<int,UploadedFile>  $images
     */
    public function create(array $data, array $images = []): Product
    {
        return DB::transaction(function () use ($data, $images) {
            $data['slug'] = $data['slug'] ?? Str::slug($data['name']).'-'.Str::random(5);
            $data['sku']  = $data['sku']  ?? strtoupper(Str::random(8));
            $data['brand_id'] = $data['brand_id'] ?? $this->resolveBrandId($data['brand'] ?? null);

            $product = Product::create($data);

            // Auto-create a default variant. Admin can add more later.
            $variant = ProductVariant::create([
                'product_id'    => $product->id,
                'sku'           => $product->sku,
                'name'          => 'Default',
                'options'       => null,
                'selling_price' => $data['selling_price'] ?? 0,
                'discount'      => 0,
                'is_active'     => true,
            ]);

            // One inventory row per variant.
            $variant->inventory()->create([
                'product_id'    => $product->id,
                'quantity'      => 0,
                'reorder_level' => $data['reorder_level'] ?? 5,
            ]);

            $this->attachImages($product, $images);

            // If variants provided at creation, persist them (including sizes/images)
            if (! empty($data['variants']) && is_array($data['variants'])) {
                $this->processVariants($product, $data['variants']);
            }

            return $product->fresh('images', 'variants.inventory');
        });
    }

    /**
     * @param array<string,mixed>      $data
     * @param array<int,UploadedFile>  $images
     * @param array<int,int>           $deleteImageIds
     */
    public function update(Product $product, array $data, array $images = [], array $deleteImageIds = []): Product
    {
        return DB::transaction(function () use ($product, $data, $images, $deleteImageIds) {
            $data['brand_id'] = $data['brand_id'] ?? $this->resolveBrandId($data['brand'] ?? null);
            $product->update($data);

            if (! empty($deleteImageIds)) {
                $this->deleteImages($product, $deleteImageIds);
            }

            $this->attachImages($product, $images);

            // If variants are provided, create or update them and ensure inventory quantities.
            if (! empty($data['variants']) && is_array($data['variants'])) {
                $this->processVariants($product, $data['variants']);
            }

            return $product->fresh('images', 'variants.inventory');
        });
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            foreach ($product->images as $image) {
                Storage::disk(self::IMAGE_DISK)->delete($image->path);
            }
            $product->delete();
        });
    }

    public function setPrimaryImage(Product $product, int $imageId): void
    {
        DB::transaction(function () use ($product, $imageId) {
            $product->images()->update(['is_primary' => false]);
            $product->images()->whereKey($imageId)->update(['is_primary' => true]);
        });
    }

    /**
     * @param array<int,UploadedFile> $images
     */
    private function attachImages(Product $product, array $images): void
    {
        if (empty($images)) {
            return;
        }

        $existingCount = $product->images()->count();
        $hasPrimary    = $product->images()->where('is_primary', true)->exists();

        foreach (array_values($images) as $i => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $path = $file->store(self::IMAGE_DIR, self::IMAGE_DISK);

            ProductImage::create([
                'product_id'    => $product->id,
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'is_primary'    => ! $hasPrimary && $i === 0,
                'sort_order'    => $existingCount + $i,
            ]);
        }
    }

    /**
     * Attach images to a specific variant and return created ProductImage models.
     *
     * @param array<int,UploadedFile> $images
     * @return array<int,ProductImage>
     */
    private function attachVariantImages(ProductVariant $variant, array $images): array
    {
        if (empty($images)) {
            return [];
        }
        $created = [];
        $existingCount = $variant->images()->count();

        foreach (array_values($images) as $i => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $path = $file->store(self::IMAGE_DIR, self::IMAGE_DISK);

            $img = ProductImage::create([
                'product_id'    => $variant->product_id,
                'product_variant_id' => $variant->id,
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'is_primary'    => $existingCount === 0 && $i === 0,
                'sort_order'    => $existingCount + $i,
            ]);

            $created[] = $img;
        }

        return $created;
    }

    /**
     * Process an array of flat variants for a product.
     * Each entry in $variants produces exactly one ProductVariant row identified
     * by (color_id, age_range_id, size_id). Stock is tracked via Inventory directly.
     *
     * @param Product $product
     * @param array<int,mixed> $variants
     */
    private function processVariants(Product $product, array $variants): void
    {
        foreach ($variants as $v) {
            /** @var ProductVariant|null $variant */
            $variant = null;

            if (! empty($v['id'])) {
                $variant = $product->variants()->whereKey($v['id'])->first();
            }

            if ($variant) {
                $variant->update($this->filterVariantColumns([
                    'sku'           => $v['sku'] ?? $variant->sku,
                    'name'          => $v['name'] ?? $variant->name,
                    'color_id'      => $v['color_id'] ?? $variant->color_id,
                    'size_id'       => $v['size_id'] ?? $variant->size_id,
                    'age_range_id'  => $v['age_range_id'] ?? $variant->age_range_id,
                    'options'       => $v['options'] ?? $variant->options,
                    'selling_price' => $v['selling_price'] ?? $variant->selling_price,
                    'discount'      => $v['discount'] ?? $variant->discount,
                    'is_active'     => $v['is_active'] ?? $variant->is_active,
                ]));
            } else {
                $skuCandidate = $v['sku'] ?? ($product->sku ?? null);
                $variant      = ProductVariant::create($this->filterVariantColumns([
                    'product_id'    => $product->id,
                    'sku'           => $this->ensureUniqueSku($skuCandidate),
                    'name'          => $v['name'] ?? 'Variant',
                    'color_id'      => $v['color_id'] ?? null,
                    'size_id'       => $v['size_id'] ?? null,
                    'age_range_id'  => $v['age_range_id'] ?? null,
                    'options'       => $v['options'] ?? null,
                    'selling_price' => $v['selling_price'] ?? ($product->selling_price ?? 0),
                    'discount'      => $v['discount'] ?? 0,
                    'is_active'     => $v['is_active'] ?? true,
                ]));
            }

            $inventoryData = [
                'product_id'    => $product->id,
                'quantity'      => isset($v['quantity']) ? (int) $v['quantity'] : 0,
                'reorder_level' => $v['reorder_level'] ?? 5,
            ];

            if ($variant->inventory) {
                $variant->inventory()->update($inventoryData);
            } else {
                $variant->inventory()->create($inventoryData);
            }

            if (! empty($v['images']) && is_array($v['images'])) {
                $this->attachVariantImages($variant, $v['images']);
            }
        }
    }
    /**
     * Ensure the given SKU is unique among product_variants. If empty, generate one.
     */
    private function ensureUniqueSku(?string $sku): string
    {
        $sku = trim((string) ($sku ?? ''));
        if ($sku === '') {
            $sku = strtoupper(Str::random(8));
        }

        $base = $sku;
        $i = 0;
        while (ProductVariant::where('sku', $sku)->exists()) {
            $i++;
            $sku = $base . '-' . $i;
        }

        return $sku;
    }

    /**
     * @param array<int,int> $imageIds
     */
    private function deleteImages(Product $product, array $imageIds): void
    {
        $images = $product->images()->whereIn('id', $imageIds)->get();
        foreach ($images as $image) {
            Storage::disk(self::IMAGE_DISK)->delete($image->path);
            $image->delete();
        }

        // Ensure there's still a primary image.
        if (! $product->images()->where('is_primary', true)->exists()) {
            $first = $product->images()->orderBy('sort_order')->first();
            if ($first) {
                $product->images()->whereKey($first->id)->update(['is_primary' => true]);
            }
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function filterVariantColumns(array $payload): array
    {
        if (! Schema::hasColumn('product_variants', 'age_range_id')) {
            unset($payload['age_range_id']);
        }
        if (! Schema::hasColumn('product_variants', 'size_id')) {
            unset($payload['size_id']);
        }
        if (! Schema::hasColumn('product_variants', 'color_id')) {
            unset($payload['color_id']);
        }

        return $payload;
    }

    private function resolveBrandId(?string $brandName): ?int
    {
        $name = trim((string) ($brandName ?? ''));
        if ($name === '') {
            return null;
        }

        return (int) Brand::query()->firstOrCreate(['name' => $name], ['is_active' => true])->id;
    }
}
