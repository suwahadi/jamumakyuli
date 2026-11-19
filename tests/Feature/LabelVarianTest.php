<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Variant;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LabelVarianTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilihan_varian_berlabel_varian_bukan_warna(): void
    {
        $produk = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'is_active' => true]);
        Variant::factory()->create(['product_id' => $produk->id, 'name' => 'KANAN']);
        Variant::factory()->create(['product_id' => $produk->id, 'name' => 'KIRI']);

        $this->get(route('products.show', $produk->slug))
            ->assertOk()
            ->assertSee('aria-label="Pilih varian"', false)
            ->assertDontSee('Warna');
    }

    public function test_ringkasan_checkout_menampilkan_varian_yang_dibeli(): void
    {
        $produk = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'is_active' => true]);
        Variant::factory()->create(['product_id' => $produk->id, 'name' => 'KANAN', 'stock' => 5]);
        $kiri = Variant::factory()->create(['product_id' => $produk->id, 'name' => 'KIRI', 'stock' => 5]);
        app(CartService::class)->add($produk->id, 1, $kiri->id);

        Livewire::test('pages::storefront.checkout')
            ->assertSee('Varian: KIRI')
            ->assertDontSee('Varian: KANAN');
    }
}
