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

        $response = $this->actingAs($admin)
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
        $response = $this->actingAs($admin)
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

        $response = $this->actingAs($admin)
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
}
