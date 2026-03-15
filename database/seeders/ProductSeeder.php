<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure we have categories first (because products depend on category_id)
        if (Category::count() === 0) {
            Category::factory()->count(8)->create();
        }

        // Seed products
        Product::factory()->count(80)->create();

        // Optional: guarantee some inactive products
        // Product::factory()->count(10)->inactive()->create();
    }
}
