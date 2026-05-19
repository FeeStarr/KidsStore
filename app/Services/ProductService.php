<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

            $product = Product::create($data);

            // Auto-create a default variant. Admin can add more later.
            $variant = ProductVariant::create([
                'product_id'    => $product->id,
                'sku'           => $product->sku,
                'name'          => 'Default',
                'options'       => null,
                'selling_price' => $data['selling_price'] ?? 0,
                'discount'      => $data['discount'] ?? 0,
                'is_active'     => true,
            ]);

            // One inventory row per variant.
            $variant->inventory()->create([
                'product_id'    => $product->id,
                'quantity'      => 0,
                'reorder_level' => $data['reorder_level'] ?? 5,
            ]);

            $this->attachImages($product, $images);

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
            $product->update($data);

            if (! empty($deleteImageIds)) {
                $this->deleteImages($product, $deleteImageIds);
            }

            $this->attachImages($product, $images);

            return $product->fresh('images', 'inventory');
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
            $first?->update(['is_primary' => true]);
        }
    }
}
