<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SeedsCatalog;
use Tests\TestCase;

/**
 * Tombol WA mengambang selalu ada di pojok kanan bawah. Saat halaman punya bar
 * bawah (nav bawah atau bar aksi), CSS mengangkatnya ke atas bar agar tidak
 * bertabrakan, dan footer diberi ruang setinggi bar plus tombol agar baris
 * copyright tidak tertutup keduanya.
 */
class BarBawahMobileTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->updateOrCreate(['key' => 'site_whatsapp'], ['value' => '087879865789']);
        Setting::query()->updateOrCreate(['key' => 'site_email'], ['value' => 'jamumakyulionline@gmail.com']);
        cache()->flush();
    }

    public function test_layout_menandai_tombol_wa_dan_footer_untuk_aturan_css(): void
    {
        $this->seedCatalog();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('wa-fab', false)
            ->assertSee('footer-site', false)
            ->assertDontSee('bottom-[74px]', false)
            ->assertDontSee('h-[64px] md:hidden', false);
    }

    // Email panjang di kolom footer selebar 148px dulu membuat seluruh halaman
    // bisa digeser ke samping sejauh 54px di ponsel.
    public function test_email_footer_boleh_patah_baris(): void
    {
        $this->seedCatalog();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<span class="min-w-0 [overflow-wrap:anywhere]">jamumakyulionline@gmail.com</span>', false);
    }

    public function test_bar_beli_produk_ditandai_tanpa_spacer_di_tengah_halaman(): void
    {
        $product = $this->seedCatalog();

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('bar-aksi', false)
            ->assertDontSee('h-[76px] lg:hidden', false);
    }

    // WhatsApp sudah dilayani tombol mengambang, jadi bar tidak membawa tombol WA kedua.
    public function test_bar_keranjang_tanpa_tombol_whatsapp_kedua(): void
    {
        $product = $this->seedCatalog();
        app(CartService::class)->add($product->id, 1);

        Livewire::test('pages::storefront.cart')
            ->assertSeeHtml('bar-aksi')
            ->assertDontSeeHtml('aria-label="Chat WhatsApp"')
            ->assertDontSeeHtml('h-[76px] lg:hidden');
    }

    public function test_bar_checkout_seragam_dan_tanpa_tombol_whatsapp_kedua(): void
    {
        $product = $this->seedCatalog();
        app(CartService::class)->add($product->id, 1);

        Livewire::test('pages::storefront.checkout')
            ->assertSeeHtml('bar-aksi')
            ->assertDontSeeHtml('aria-label="Chat WhatsApp"')
            ->assertDontSeeHtml('text-[9px]');
    }

    public function test_nav_bawah_menampilkan_lacak_menggantikan_whatsapp(): void
    {
        $this->seedCatalog();

        $html = $this->get(route('home'))->assertOk()->getContent();
        $mulai = strpos($html, 'aria-label="Navigasi mobile"');
        $nav = substr($html, $mulai, strpos($html, '</nav>', $mulai) - $mulai);

        $this->assertStringContainsString('href="'.route('track').'"', $nav);
        $this->assertStringContainsString('#i-track', $nav);
        $this->assertStringContainsString('Lacak', $nav);
        $this->assertStringNotContainsString('wa.me', $nav);
    }

    public function test_tombol_wa_mengambang_tetap_dirender_di_halaman_tanpa_nav_bawah(): void
    {
        $this->seedCatalog();

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertDontSee('aria-label="Navigasi mobile"', false)
            ->assertSee('wa-fab', false);
    }

    public function test_css_mengangkat_wa_mengambang_di_atas_bar_bukan_menyembunyikannya(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression('/body:has\(\.bnav-safe\) \.wa-fab \{ bottom:/', $css);
        $this->assertMatchesRegularExpression('/body:has\(\.bar-aksi\) \.wa-fab \{ bottom:/', $css);
        $this->assertStringNotContainsString('.wa-fab { display: none; }', $css);
        $this->assertStringContainsString('body:has(.bnav-safe) .footer-site', $css);
        $this->assertStringContainsString('body:has(.bar-aksi) .footer-site', $css);
    }
}
