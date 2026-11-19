<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;

class VariantFactory extends Factory
{
    protected $model = Variant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->randomElement([
                '250 gram', '500 gram', '1 kg', 'Paket 1 Bulan', 'Paket 2 Bulan',
                'BB di bawah 60 kg', 'BB di atas 60 kg', 'Kemasan Zip', 'Kemasan Kotak', 'Isi Ulang',
            ]),
            'sku' => 'JMY-'.strtoupper(fake()->unique()->bothify('???-#####')),
            'price' => fake()->numberBetween(50, 300) * 1000,
            'promo_price' => null,
            'stock' => fake()->numberBetween(0, 60),
            'weight' => fake()->numberBetween(250, 2000),
        ];
    }
}
