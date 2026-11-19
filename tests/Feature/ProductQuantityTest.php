<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductQuantityTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENT = 'pages::storefront.product';

    private function produk(int $stock = 40): Product
    {
        return Product::factory()->inStock($stock)->create([
            'category_id' => Category::factory()->create()->id,
        ]);
    }

    public function test_jumlah_dibatasi_maksimal_stok(): void
    {
        $product = $this->produk(40);

        Livewire::test(self::COMPONENT, ['product' => $product])
            ->set('quantity', 41)
            ->assertSet('quantity', 40)
            ->set('quantity', 999)
            ->assertSet('quantity', 40);
    }

    public function test_jumlah_minimal_satu(): void
    {
        $product = $this->produk(40);

        Livewire::test(self::COMPONENT, ['product' => $product])
            ->set('quantity', 0)
            ->assertSet('quantity', 1)
            ->set('quantity', -5)
            ->assertSet('quantity', 1);
    }

    public function test_jumlah_non_numerik_tidak_error_dan_jadi_satu(): void
    {
        $product = $this->produk(40);

        Livewire::test(self::COMPONENT, ['product' => $product])
            ->set('quantity', 'abc')
            ->assertSet('quantity', 1)
            ->set('quantity', '')
            ->assertSet('quantity', 1);
    }

    public function test_subtotal_ikut_jumlah(): void
    {
        $product = $this->produk(40);

        Livewire::test(self::COMPONENT, ['product' => $product])
            ->set('quantity', 3)
            ->assertSee(rupiah($product->effective_price * 3));
    }
}
