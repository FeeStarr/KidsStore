<?php

namespace App\Services;

use App\Models\AgeRange;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Product;
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

    private ImageOptimizationService $optimizer;

    public function __construct(ImageOptimizationService $optimizer)
    {
        $this->optimizer = $optimizer;
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  array<int,UploadedFile>  $images
     */
    public function create(array $data, array $images = []): Product
    {
        return DB::transaction(function () use ($data, $images) {
            $data['slug'] = $data['slug'] ?? Str::slug($data['name']).'-'.Str::random(5);
            // Reserve a production-safe product code from the `sequences` table and set final SKU before insert.
            $prefix = trim((string) (env('STORE_PREFIX', 'KF')));

            // Lock and increment sequence row for product_code atomically.
            $seqRow = DB::table('sequences')->where('name', 'product_code')->lockForUpdate()->first();
            if (! $seqRow) {
                // create sequence row starting at 1
                DB::table('sequences')->insert([
                    'name' => 'product_code',
                    'value' => 2, // next value after reserving 1
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $nextId = 1;
            } else {
                $nextId = (int) $seqRow->value;
                DB::table('sequences')->where('name', 'product_code')->update([
                    'value' => $nextId + 1,
                    'updated_at' => now(),
                ]);
            }

            $productCode = 'P'.str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
            $data['sku'] = strtoupper(($prefix !== '' ? $prefix.'-' : '').$productCode);
            $data['brand_id'] = $data['brand_id'] ?? $this->resolveBrandId($data['brand'] ?? null);

            $product = Product::create($data);

            $this->attachImages($product, $images);

            // If variants provided at creation, persist them (including sizes/images)
            if (! empty($data['variants']) && is_array($data['variants'])) {
                $this->processVariants($product, $data['variants']);
            }

            return $product->fresh('images', 'variants.inventory');
        });
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  array<int,UploadedFile>  $images
     * @param  array<int,int>  $deleteImageIds
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
     * @param  array<int,UploadedFile>  $images
     */
    private function attachImages(Product $product, array $images): void
    {
        if (empty($images)) {
            return;
        }

        $existingCount = $product->images()->count();
        $hasPrimary = $product->images()->where('is_primary', true)->exists();

        foreach (array_values($images) as $i => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $result = $this->optimizer->optimizeUploadedFile($file, self::IMAGE_DISK);

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $result['path'],
                'original_name' => $file->getClientOriginalName(),
                'is_primary' => ! $hasPrimary && $i === 0,
                'sort_order' => $existingCount + $i,
            ]);
        }
    }

    /**
     * Attach images to a specific variant and return created ProductImage models.
     *
     * @param  array<int,UploadedFile>  $images
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

            $result = $this->optimizer->optimizeUploadedFile($file, self::IMAGE_DISK);

            $img = ProductImage::create([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'path' => $result['path'],
                'original_name' => $file->getClientOriginalName(),
                'is_primary' => $existingCount === 0 && $i === 0,
                'sort_order' => $existingCount + $i,
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
     * @param  array<int,mixed>  $variants
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
                    'sku' => $v['sku'] ?? $variant->sku,
                    'name' => $v['name'] ?? $variant->name,
                    'color_id' => $v['color_id'] ?? $variant->color_id,
                    'size_id' => $v['size_id'] ?? $variant->size_id,
                    'age_range_id' => $v['age_range_id'] ?? $variant->age_range_id,
                    'options' => $v['options'] ?? $variant->options,
                    'selling_price' => $v['selling_price'] ?? $variant->selling_price,
                    'discount' => $v['discount'] ?? $variant->discount,
                    'is_active' => $v['is_active'] ?? $variant->is_active,
                ]));
            } else {
                $skuCandidate = $v['sku'] ?? $this->generateVariantSku($product, $v);
                $variant = ProductVariant::create($this->filterVariantColumns([
                    'product_id' => $product->id,
                    'sku' => $this->ensureUniqueSku($skuCandidate),
                    'name' => $v['name'] ?? 'Variant',
                    'color_id' => $v['color_id'] ?? null,
                    'size_id' => $v['size_id'] ?? null,
                    'age_range_id' => $v['age_range_id'] ?? null,
                    'options' => $v['options'] ?? null,
                    'selling_price' => $v['selling_price'] ?? ($product->selling_price ?? 0),
                    'discount' => $v['discount'] ?? 0,
                    'is_active' => $v['is_active'] ?? true,
                ]));
            }

            $inventoryData = [
                'product_id' => $product->id,
                'quantity' => isset($v['quantity']) ? (int) $v['quantity'] : 0,
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
    public function ensureUniqueSku(?string $sku): string
    {
        $sku = trim((string) ($sku ?? ''));
        if ($sku === '') {
            $sku = strtoupper(Str::random(8));
        }

        $base = $sku;
        $i = 0;
        while (ProductVariant::where('sku', $sku)->exists()) {
            $i++;
            $sku = $base.'-'.$i;
        }

        return $sku;
    }

    /**
     * Generate a SKU using store prefix, product SKU/code, color code and age suffix.
     * Format: {PREFIX}-{PRODUCTCODE}-{COLORCODE}-{AGESUFFIX}
     * Falls back gracefully if parts are missing.
     *
     * @param  array<string,mixed>  $v
     */
    public function generateVariantSku(Product $product, array $v): string
    {
        $prefix = trim((string) (env('STORE_PREFIX', 'KF')));

        $productSku = strtoupper(trim((string) ($product->sku ?? '')));
        if ($productSku !== '') {
            if ($prefix !== '' && str_starts_with($productSku, $prefix.'-')) {
                // remove leading prefix so we don't duplicate it in the final SKU
                $productCode = substr($productSku, strlen($prefix) + 1);
            } else {
                $productCode = $productSku;
            }
        } else {
            $productCode = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        }

        $colorCode = '';
        if (! empty($v['color_id'])) {
            $c = Color::find($v['color_id']);
            if ($c && isset($c->code) && $c->code !== null) {
                $colorCode = strtoupper($c->code);
            } else {
                $colorCode = strtoupper(substr(preg_replace('/[^A-Z]/i', '', ($c->name ?? '')), 0, 3));
            }
        }

        $ageSuffix = '';
        if (! empty($v['age_range_id'])) {
            $a = AgeRange::find($v['age_range_id']);
            if ($a && $a->name) {
                $s = $a->name;
                $s = str_ireplace(['years', 'year', 'yrs', 'yr'], 'Y', $s);
                $s = str_ireplace(['months', 'month', 'mos', 'mo'], 'M', $s);
                $s = strtoupper(str_replace(' ', '', $s));
                // keep only alnum and dash
                $s = preg_replace('/[^A-Z0-9\-]/', '', $s);
                $ageSuffix = $s;
            }
        }

        $parts = array_filter([$prefix, $productCode, $colorCode, $ageSuffix], fn ($p) => $p !== '');
        $candidate = implode('-', $parts);

        return $candidate ?: strtoupper(Str::random(8));
    }

    /**
     * @param  array<int,int>  $imageIds
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
     * @param  array<string,mixed>  $payload
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
