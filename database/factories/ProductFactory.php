<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->randomElement([
            'Jamu Kunyit Asam', 'Jamu Beras Kencur', 'Jamu Temulawak', 'Jamu Jahe Merah',
            'Jamu Daun Sirih', 'Jamu Manjakani', 'Jamu Sehat Pria', 'Jamu Diet Herbal',
            'Ramuan Pegal Linu', 'Ramuan Kayu Manis', 'Ramuan Daun Kelor', 'Ramuan Sereh Wangi',
        ]).' '.strtoupper(fake()->bothify('##??'));

        $original = fake()->numberBetween(50, 300) * 1000;
        $onPromo = fake()->boolean(40);

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'sku' => 'JMY-'.strtoupper(fake()->unique()->bothify('???-#####')),
            'original_price' => $original,
            'promo_price' => $onPromo ? (int) ($original * fake()->randomFloat(2, 0.6, 0.9)) : null,
            'description' => fake()->paragraphs(3, true),
            'weight' => fake()->numberBetween(250, 2000),
            'stock' => fake()->numberBetween(0, 120),
            'is_active' => fake()->boolean(90),
        ];
    }

    public function inStock(int $stock = 50): static
    {
        return $this->state(fn (): array => ['stock' => $stock, 'is_active' => true]);
    }
}
