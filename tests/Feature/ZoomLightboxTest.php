<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoomLightboxTest extends TestCase
{
    use RefreshDatabase;

    private function halamanProduk(): string
    {
        $produk = Product::factory()->create(['is_active' => true]);
        ProductImage::query()->create(['product_id' => $produk->id, 'path' => 'products/uji.webp', 'is_main' => true, 'sort_order' => 0]);

        return $this->get(route('products.show', $produk))->assertOk()->getContent();
    }

    public function test_lightbox_punya_tombol_perbesar_dan_perkecil(): void
    {
        $html = $this->halamanProduk();

        $this->assertMatchesRegularExpression('#<button[^>]*@click="zoomOut\(\)"[^>]*aria-label="Perkecil"#', $html);
        $this->assertMatchesRegularExpression('#<button[^>]*@click="zoomIn\(\)"[^>]*aria-label="Perbesar"#', $html);
    }

    public function test_lensa_pembesar_mengikuti_kursor_dihapus(): void
    {
        $html = $this->halamanProduk();

        $this->assertStringNotContainsString('moveLens', $html);
        $this->assertStringNotContainsString('lensStyle', $html);
    }

    public function test_foto_bisa_digeser_dengan_mouse_saat_diperbesar(): void
    {
        $this->assertStringContainsString('@pointerdown="onPointerDown($event)"', $this->halamanProduk());
    }
}
