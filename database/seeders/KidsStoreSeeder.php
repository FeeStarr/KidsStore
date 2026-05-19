<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KidsStoreSeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['Toys', 'Clothing', 'Books', 'Shoes', 'Accessories'];
        foreach ($categories as $name) {
            Category::firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
            ]);
        }

        $subcategories = [
            'Clothing'   => ['Dresses', 'T-Shirts', 'Trousers', 'Jackets', 'Underwear'],
            'Toys'       => ['Plush', 'Building Blocks', 'Puzzles', 'Educational'],
            'Books'      => ['Picture Books', 'Storybooks', 'Activity Books'],
            'Shoes'      => ['Sneakers', 'Sandals', 'Boots'],
            'Accessories'=> ['Hats', 'Bags', 'Socks'],
        ];
        foreach ($subcategories as $parentName => $children) {
            $parent = Category::where('slug', Str::slug($parentName))->first();
            if (!$parent) continue;
            foreach ($children as $childName) {
                Category::firstOrCreate(
                    ['slug' => Str::slug($parentName . '-' . $childName)],
                    ['name' => $childName, 'parent_id' => $parent->id]
                );
            }
        }

        Supplier::firstOrCreate(
            ['name' => 'Happy Kids Supplier Co.'],
            ['contact_name' => 'Sarah', 'email' => 'sales@happykids.example', 'phone' => '+1-555-0100']
        );

        // Create a walk-in customer user
        User::firstOrCreate(
            ['email' => 'walkin@kidsstore.local'],
            [
                'name' => 'Walk-in Customer',
                'password' => bcrypt('password'),
                'role' => User::ROLE_CUSTOMER,
            ]
        );

        $samples = [
            ['Plush Teddy Bear', 'Toys',     '1-2', 'unisex', 19.99],
            ['Wooden Building Blocks', 'Toys', '3-4', 'unisex', 29.99],
            ['Princess Dress', 'Clothing',  '4-5', 'girl',  24.99],
            ['Superhero T-Shirt', 'Clothing','6-7', 'boy',   14.99],
            ['Storybook Collection', 'Books','3-4', 'unisex', 39.99],
            ['Light-up Sneakers', 'Shoes',  '5-6', 'unisex', 34.99],
        ];

        foreach ($samples as [$name, $cat, $age, $gender, $price]) {
            $category = Category::where('name', $cat)->first();
            $product = Product::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'category_id' => $category?->id,
                    'sku' => strtoupper(Str::random(8)),
                    'name' => $name,
                    'age_group' => is_array($age) ? $age : [$age],
                    'gender' => $gender,
                    'selling_price' => $price,
                    'discount' => 0,
                    'is_active' => true,
                ]
            );
            Inventory::firstOrCreate(
                ['product_id' => $product->id],
                ['quantity' => 0, 'reorder_level' => 5]
            );
        }
    }
}
