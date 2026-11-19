<?php

namespace Tests\Feature;

use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\SeedsCatalog;
use Tests\TestCase;

class BrandThemeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCatalog;

    public function test_storefront_tidak_menyisakan_class_merah_brand_lama(): void
    {
        $this->seedCatalog();

        $html = $this->get(route('home'))->assertOk()->getContent();

        preg_match_all('/\bred-\d{2,3}\b/', $html, $matches);

        $this->assertSame([], array_values(array_unique($matches[0])));
    }

    public function test_theme_uses_jamu_palette_and_fonts(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('--color-brand-green: #2F7D32', $css);
        $this->assertStringContainsString('--color-brand-turmeric: #E0A21B', $css);
        $this->assertStringContainsString('--color-brand-pink: #F6D6DC', $css);
        $this->assertStringContainsString("--font-display: 'Fraunces'", $css);
        $this->assertStringNotContainsString('brand-red', $css);
        $this->assertStringNotContainsString('brand-orange', $css);

        $this->seedCatalog();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('fonts.googleapis.com/css2?family=Figtree', false)
            ->assertSee('Jamu <span class="text-brand-green">Mak Yuli</span>', false);
    }

    public function test_footer_shows_operating_hours_address_and_hides_empty_socials(): void
    {
        $this->seed(SettingSeeder::class);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Senin sampai Sabtu, 08.00 sampai 17.00 WIB')
            ->assertSee('Dk. Pilangbangu, Ds. Munggur, Kec. Mojogedang, Kab. Karanganyar')
            ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=rumah+mak+yuli+jamu', false)
            ->assertDontSee('instagram.com', false)
            ->assertDontSee('shopee.co.id', false);
    }

    public function test_storefront_memakai_token_hijau_brand(): void
    {
        $this->seedCatalog();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('bg-brand-green', false);
    }

    public function test_meta_theme_color_memakai_hijau_brand(): void
    {
        $this->seedCatalog();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('content="#2F7D32"', false);
    }

    public function test_storefront_tidak_lagi_mengunci_mode_terang(): void
    {
        $this->seedCatalog();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('scheme-light', false);
    }

    public function test_sprite_menyediakan_semua_simbol_yang_dibutuhkan_komponen_baru(): void
    {
        $this->seedCatalog();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $wajib = [
            'i-menu', 'i-sun', 'i-moon', 'i-star', 'i-quote',
            'i-trash', 'i-share', 'i-arrow-down',
            'i-shield-check', 'i-wallet', 'i-gauge',
            'i-file-text', 'i-gavel', 'i-users', 'i-warehouse',
        ];

        preg_match_all('/<symbol id="([^"]+)"/', $html, $defined);

        $this->assertSame([], array_values(array_diff($wajib, $defined[1])));
    }

    public function test_kartu_produk_tidak_lagi_menggambar_rating_palsu(): void
    {
        $this->seedCatalog();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Rating 5 dari 5', false);
    }

    public function test_kartu_produk_memakai_badge_diskon_clip_path(): void
    {
        $this->seedCatalog();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('disc-badge', false)
            ->assertSee('25%');
    }

    public function test_kartu_produk_menampilkan_harga_promo_tanpa_sku(): void
    {
        $this->seedCatalog();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('JMY-PROMIL-001')
            ->assertSee(rupiah(285000))
            ->assertSee(rupiah(380000));
    }

    // Warna teks pekat memakai token --color-ink, bukan hitam murni. text-black pernah
    // dipakai di 32 tempat dan lolos karena tampak mirip.
    public function test_tidak_ada_text_black_di_view(): void
    {
        $pelanggar = [];

        foreach (File::allFiles(resource_path('views')) as $berkas) {
            if (str_contains((string) file_get_contents($berkas->getPathname()), 'text-black')) {
                $pelanggar[] = $berkas->getRelativePathname();
            }
        }

        $this->assertSame([], $pelanggar);
    }

    // .field ditulis sebagai CSS biasa di bawah utility Tailwind, jadi spesifisitasnya
    // seri dan .field selalu menang: pl-9 dan sm:w-auto pada elemen ber-class field
    // diabaikan diam-diam. Membungkusnya di @layer components membuat utility menang
    // lagi, terlepas dari urutan penulisan.
    public function test_field_hidup_di_layer_components(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('@layer components {', $css);
        $this->assertStringNotContainsString('
.field {', $css);
        $this->assertStringNotContainsString('
.dark .field {', $css);
    }
}
