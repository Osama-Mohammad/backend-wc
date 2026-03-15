<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true); // "Wireless Gaming Mouse"
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;

        // keep slug unique (safe even if you later add a unique index)
        $i = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$i}";
            $i++;
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'description' => $this->faker->paragraph(2),
            'category_id' => Category::query()->inRandomOrder()->value('id') ?? Category::factory(),
            'price' => $this->faker->numberBetween(5, 5000), // integer
            'is_active' => $this->faker->boolean(85), // 85% active
        ];
    }
}
