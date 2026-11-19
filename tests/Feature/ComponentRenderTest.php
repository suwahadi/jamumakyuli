<?php

namespace Tests\Feature;

use Tests\TestCase;

class ComponentRenderTest extends TestCase
{
    public function test_btn_primary_memakai_merah_brand_dan_sudut_kotak(): void
    {
        $this->blade('<x-btn variant="primary">Beli</x-btn>')
            ->assertSee('bg-brand-green', false)
            ->assertSee('hover:bg-brand-green-dark', false)
            ->assertSee('rounded-md', false)
            ->assertSee('Beli');
    }

    public function test_btn_outline_terisi_merah_saat_hover(): void
    {
        $this->blade('<x-btn variant="outline">Reset</x-btn>')
            ->assertSee('border-2', false)
            ->assertSee('border-brand-green', false)
            ->assertSee('hover:bg-brand-green', false)
            ->assertSee('hover:text-white', false);
    }

    public function test_btn_varian_dark_tersedia_untuk_tombol_sekunder(): void
    {
        $this->blade('<x-btn variant="dark">Filter</x-btn>')
            ->assertSee('border-brand-dark', false)
            ->assertSee('hover:bg-brand-dark', false)
            ->assertSee('dark:border-zinc-600', false);
    }

    public function test_btn_dengan_href_dirender_sebagai_tautan(): void
    {
        $this->blade('<x-btn variant="primary" href="/katalog">Katalog</x-btn>')
            ->assertSee('<a href="/katalog"', false);
    }

    public function test_page_header_merender_banner_merah_bertekstur(): void
    {
        $this->blade(
            '<x-page-header title="Katalog Produk" :items="$items" />',
            ['items' => [
                ['label' => 'Beranda', 'url' => '/'],
                ['label' => 'Katalog'],
            ]]
        )
            ->assertSee('bg-brand-green', false)
            ->assertSee('grid-tex', false)
            ->assertSee('Katalog Produk')
            ->assertSee('Beranda')
            ->assertSee('<h1', false);
    }

    public function test_page_header_menandai_item_terakhir_sebagai_halaman_aktif(): void
    {
        $this->blade(
            '<x-page-header title="Keranjang" :items="$items" />',
            ['items' => [
                ['label' => 'Beranda', 'url' => '/'],
                ['label' => 'Keranjang'],
            ]]
        )->assertSee('aria-current="page"', false);
    }

    public function test_product_rail_tidak_dirender_saat_koleksi_kosong(): void
    {
        $html = (string) $this->blade(
            '<x-product-rail heading="PROMO" :products="$products" />',
            ['products' => collect()]
        );

        $this->assertStringNotContainsString('PROMO', $html);
    }

    public function test_trust_bar_menampilkan_empat_jaminan_layanan(): void
    {
        $this->blade('<x-trust-bar />')
            ->assertSee('Sejak 2020')
            ->assertSee('1000+ pelanggan')
            ->assertSee('100% herbal alami')
            ->assertSee('Kirim ke seluruh Indonesia');
    }

    public function test_wordmark_varian_teks_menonjolkan_mak_yuli(): void
    {
        $html = (string) $this->blade('<x-wordmark variant="text" />');

        $this->assertStringContainsString('Jamu', $html);
        $this->assertStringContainsString('Mak Yuli', $html);
        $this->assertStringContainsString('text-brand-green', $html);
    }
}
