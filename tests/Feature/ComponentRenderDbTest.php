<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComponentRenderDbTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_rail_merender_judul_kartu_dan_tombol_navigasi(): void
    {
        $products = Product::factory()->count(3)->inStock()->create();

        $this->blade(
            '<x-product-rail heading="BEST SELLER" :products="$products" href="/products" />',
            ['products' => $products]
        )
            ->assertSee('BEST SELLER')
            ->assertSee('sec-tick', false)
            ->assertSee('x-data="productRail"', false)
            ->assertSee('Lihat Semua')
            ->assertSee('Sebelumnya', false)
            ->assertSee('Berikutnya', false)
            ->assertSee($products->first()->name);
    }

    public function test_wordmark_berupa_teks_dengan_ukuran_dari_prop(): void
    {
        $this->blade('<x-wordmark size="text-3xl" />')
            ->assertSee('Jamu <span class="text-brand-green">Mak Yuli</span>', false)
            ->assertSee('text-3xl', false)
            ->assertDontSee('<img', false);
    }

    public function test_komponen_section_meneruskan_atribut_dari_pemanggil(): void
    {
        $this->blade('<x-trust-bar class="mt-8" />')->assertSee('mt-8', false);
        $this->blade('<x-page-header title="Uji" class="mt-8" />')->assertSee('mt-8', false);
    }
}
