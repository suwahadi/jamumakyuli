<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\Product;

trait SeedsCatalog
{
    protected function seedCatalog(): Product
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => Category::PROMIL_SLUG],
            ['name' => 'Paket Promil Pasutri', 'is_active' => true],
        );

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Jamu Paket Promil Ideal',
            'slug' => 'jamu-paket-promil-ideal',
            'sku' => 'JMY-PROMIL-001',
            'original_price' => 380000,
            'promo_price' => 285000,
            'description' => 'Ramuan rempah kering untuk ikhtiar program hamil pasutri.',
            'weight' => 1200,
            'stock' => 18,
            'is_active' => true,
        ]);
    }
}
