<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KartuProdukHargaTest extends TestCase
{
    use RefreshDatabase;

    private function kartu(Product $product): string
    {
        return (string) $this->blade('<x-product-card :product="$p" />', ['p' => $product->load('variants', 'images')]);
    }

    public function test_urutan_harga_coret_lalu_harga_merah_lalu_judul(): void
    {
        $p = Product::factory()->create([
            'name' => 'Jamu Kunyit Uji', 'original_price' => 500000, 'promo_price' => 450000, 'is_active' => true,
        ]);

        $this->blade('<x-product-card :product="$p" />', ['p' => $p->load('variants', 'images')])
            ->assertSeeInOrder([rupiah(500000), rupiah(450000), 'Jamu Kunyit Uji']);
    }

    public function test_harga_coret_tidak_lagi_dipindah_ke_belakang_di_layar_lebar(): void
    {
        $p = Product::factory()->create(['original_price' => 500000, 'promo_price' => 450000, 'is_active' => true]);

        $this->assertStringNotContainsString('sm:order-last', $this->kartu($p));
    }

    public function test_harga_coret_dan_harga_merah_sebaris(): void
    {
        $p = Product::factory()->create(['original_price' => 500000, 'promo_price' => 450000, 'is_active' => true]);

        $this->assertMatchesRegularExpression(
            '#<div class="(?=[^"]*\bitems-baseline\b)(?![^"]*flex-col)[^"]*">\s*<span[^>]*\bwhitespace-nowrap\b[^>]*line-through[^>]*>'.preg_quote(rupiah(500000), '#').'</span>\s*<span[^>]*\bwhitespace-nowrap\b[^>]*>'.preg_quote(rupiah(450000), '#').'</span>#',
            $this->kartu($p),
        );
    }

    public function test_harga_coret_11px_di_semua_ukuran_layar(): void
    {
        $p = Product::factory()->create(['original_price' => 500000, 'promo_price' => 450000, 'is_active' => true]);

        $this->assertMatchesRegularExpression(
            '#<span class="(?=[^"]*\btext-\[11px\])(?![^"]*text-\[10px\])(?![^"]*sm:text-)[^"]*line-through[^"]*">'.preg_quote(rupiah(500000), '#').'</span>#',
            $this->kartu($p),
        );
    }

    public function test_harga_merah_tebal_dan_sedikit_lebih_kecil(): void
    {
        $p = Product::factory()->create(['original_price' => 500000, 'promo_price' => null, 'is_active' => true]);

        $this->assertMatchesRegularExpression(
            '#<span class="(?=[^"]*\btext-\[13px\])(?=[^"]*\bsm:text-sm\b)(?=[^"]*\bfont-bold\b)(?=[^"]*\btext-brand-green\b)[^"]*">'.preg_quote(rupiah(500000), '#').'</span>#',
            $this->kartu($p),
        );
    }
}
