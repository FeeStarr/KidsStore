<?php

namespace Tests\Feature;

use App\Models\AgeRange;
use App\Models\Category;
use App\Models\Color;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminProductVariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_product_with_variants_and_images(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $category = Category::factory()->create();
        $color = Color::factory()->create(['name' => 'Blue']);
        $size4 = Size::factory()->create(['name' => '4']);
        $size5 = Size::factory()->create(['name' => '5']);
        $ageRange = AgeRange::factory()->create(['name' => '3-4 years']);

        $file1 = UploadedFile::fake()->image('blue1.jpg');
        $file2 = UploadedFile::fake()->image('blue2.jpg');
        $sizeFile = UploadedFile::fake()->image('blue-size.jpg');

        $payload = [
            'name' => 'Blue Shirt',
            'category_id' => $category->id,
            'selling_price' => 10,
            'variants' => [
                [
                    'name' => 'Blue Size 4',
                    'sku' => 'BLUE-4',
                    'color_id' => $color->id,
                    'size_id' => $size4->id,
                    'age_range_id' => $ageRange->id,
                    'quantity' => 2,
                    'images' => [$file1, $sizeFile],
                ],
                [
                    'name' => 'Blue Size 5',
                    'sku' => 'BLUE-5',
                    'color_id' => $color->id,
                    'size_id' => $size5->id,
                    'age_range_id' => $ageRange->id,
                    'quantity' => 3,
                    'images' => [$file2],
                ],
            ],
        ];

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.products.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('products', [
            'name' => 'Blue Shirt',
        ]);

        $this->assertDatabaseHas('product_variants', [
            'sku' => 'BLUE-4',
            'color_id' => $color->id,
            'size_id' => $size4->id,
        ]);

        $this->assertDatabaseHas('product_variants', [
            'sku' => 'BLUE-5',
            'color_id' => $color->id,
            'size_id' => $size5->id,
        ]);

        $this->assertDatabaseHas('inventories', [
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('product_images', [
            'original_name' => 'blue-size.jpg',
        ]);
    }

    public function test_admin_can_update_product_and_variants(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $category = Category::factory()->create();
        $color = Color::factory()->create(['name' => 'Red']);
        $size6 = Size::factory()->create(['name' => '6']);
        $ageRange = AgeRange::factory()->create(['name' => '5-6 years']);

        // create initial product
        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.products.store'), [
                'name' => 'TShirt',
                'category_id' => $category->id,
                'selling_price' => 15,
            ]);

        $productId = \DB::table('products')->where('name', 'TShirt')->value('id');

        $file = UploadedFile::fake()->image('size6.jpg');

        $payload = [
            'name' => 'TShirt Updated',
            'variants' => [
                [
                    'name' => 'Red Size 6',
                    'sku' => 'RED-6',
                    'color_id' => $color->id,
                    'size_id' => $size6->id,
                    'age_range_id' => $ageRange->id,
                    'quantity' => 4,
                    'images' => [$file],
                ],
            ],
        ];

        $response = $this->actingAs($admin, 'admin')
            ->put(route('admin.products.update', ['product' => $productId]), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'name' => 'TShirt Updated',
        ]);

        $this->assertDatabaseHas('product_variants', [
            'sku' => 'RED-6',
            'color_id' => $color->id,
            'size_id' => $size6->id,
        ]);

        $this->assertDatabaseHas('inventories', [
            'quantity' => 4,
        ]);
    }

    public function test_updating_variant_stock_syncs_inventory_columns_and_product_cache(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $category = Category::factory()->create();
        $color = Color::factory()->create(['name' => 'Green']);
        $size = Size::factory()->create(['name' => '8']);
        $ageRange = AgeRange::factory()->create(['name' => '7-8 years']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.products.store'), [
                'name' => 'Green Tee',
                'category_id' => $category->id,
                'selling_price' => 20,
                'variants' => [
                    [
                        'name' => 'Green Size 8',
                        'sku' => 'GRN-8',
                        'color_id' => $color->id,
                        'size_id' => $size->id,
                        'age_range_id' => $ageRange->id,
                        'quantity' => 4,
                        'images' => [UploadedFile::fake()->image('green8.jpg')],
                    ],
                ],
            ])->assertRedirect();

        $productId = \DB::table('products')->where('name', 'Green Tee')->value('id');
        $variantId = \DB::table('product_variants')->where('sku', 'GRN-8')->value('id');

        $this->actingAs($admin, 'admin')
            ->put(route('admin.products.update', ['product' => $productId]), [
                'name' => 'Green Tee',
                'category_id' => $category->id,
                'selling_price' => 20,
                'variants' => [
                    [
                        'id' => $variantId,
                        'name' => 'Green Size 8',
                        'sku' => 'GRN-8',
                        'color_id' => $color->id,
                        'size_id' => $size->id,
                        'age_range_id' => $ageRange->id,
                        'quantity' => 9,
                        'images' => [UploadedFile::fake()->image('green8b.jpg')],
                    ],
                ],
            ])->assertRedirect();

        $inventory = \DB::table('inventories')
            ->where('product_id', $productId)
            ->first();

        $this->assertNotNull($inventory);
        $this->assertSame(9, (int) $inventory->quantity);
        $this->assertSame(9, (int) $inventory->quantity_on_hand);

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'stock_quantity' => 9,
        ]);
    }
}
