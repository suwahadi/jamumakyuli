<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::query()->exists()) {
            return;
        }

        $catalog = json_decode((string) file_get_contents(database_path('data/catalog.json')), true, flags: JSON_THROW_ON_ERROR);

        $categories = [];
        foreach ($catalog['categories'] as $category) {
            $categories[$category['slug']] = Category::query()->firstOrCreate(
                ['slug' => $category['slug']],
                ['name' => $category['name'], 'is_active' => true],
            );
        }

        foreach ($catalog['products'] as $item) {
            $product = Product::query()->create([
                'category_id' => $categories[$item['category']]->id,
                'name' => $item['name'],
                'slug' => Str::slug($item['name']),
                'sku' => $item['sku'],
                'original_price' => $item['price'],
                'promo_price' => null,
                'description' => $item['description'],
                'weight' => $item['weight'],
                'stock' => $item['stock'],
                'is_active' => true,
            ]);

            $path = 'products/'.$item['image'];
            Storage::disk('public')->put($path, (string) file_get_contents(database_path('data/images/products/'.$item['image'])));

            ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => $path,
                'is_main' => true,
                'sort_order' => 0,
            ]);

            $category = $categories[$item['category']];
            if (blank($category->thumbnail)) {
                $category->update(['thumbnail' => $path]);
            }
        }
    }
}
