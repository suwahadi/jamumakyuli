<?php

namespace Tests\Feature;

use App\Models\AboutImage;
use App\Models\Category;
use App\Models\Page;
use App\Models\Slide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCatalog;
use Tests\TestCase;

class StorefrontRenderTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCatalog;

    public function test_home_renders_every_mockup_section(): void
    {
        $this->seedCatalog();

        // Seksi Sekilas Tentang kini digerakkan data dan disembunyikan saat galerinya
        // kosong, jadi harus ada isinya agar penjaga di bawah tetap berarti.
        AboutImage::query()->create(['image' => 'about/sekilas.webp', 'sort_order' => 0]);

        $response = $this->get(route('home'));

        $response->assertOk();

        // Section anchors + headings dari index.html — kalau salah satu hilang,
        // sebuah section ikut hilang saat markup disunting.
        $response->assertSee('Paket Promil Pasutri');
        $response->assertSee('id="sorotan-promil"', false);
        $response->assertSee('Sekilas Tentang');
        $response->assertDontSee('id="kategori"', false);
        $response->assertDontSee('Pilih Paket Promil Ideal bila');
        $response->assertSee('id="produk"', false);
        $response->assertSee('id="sekilas-h"', false);

        // Dihapus saat halaman depan disamakan dengan index.html — kalau salah satu
        // muncul lagi, section yang sudah dibuang ikut kembali tanpa disengaja.
        $response->assertDontSee('Gratis Ongkir Area Jakarta');
        $response->assertDontSee('Hemat 15% untuk registrasi akun');
        $response->assertDontSee('id="tentang"', false);
        $response->assertDontSee('id="kenapa-h"', false);
        $response->assertDontSee('Kenapa Belanja di');
        // Grid kategori kini mengikuti index.html: hanya kartu, tanpa eyebrow dan judul.
        $response->assertDontSee('Jelajahi Berdasarkan Kebutuhan');

        // Section testimoni kini dinamis: tersembunyi selama belum ada testimoni berkonten.
        $response->assertDontSee('id="testimoni"', false);
        $response->assertDontSee('Testimoni Pelanggan', false);
    }

    public function test_home_shows_placeholder_banner_when_no_slides_exist(): void
    {
        $this->seedCatalog();

        // Aplikasi kini membawa slide hero bawaan lewat migrasi data, jadi keadaan
        // "belum ada slide" harus dibuat sendiri oleh tes ini.
        Slide::query()->delete();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('ph h-[262px]', false)
            ->assertSee('Ramuan rempah kering untuk ikhtiar program hamil pasutri.', false);
    }

    public function test_layout_keeps_its_dynamic_parts(): void
    {
        $this->seedCatalog();

        $response = $this->get(route('home'));

        // Bagian yang wajib selamat saat chrome ditulis ulang.
        $response->assertSee('name="q"', false);
        $response->assertSee(route('products.index'), false);
        $response->assertSee(route('cart.index'), false);
        $response->assertSee(route('wishlist.index'), false);
        $response->assertSee(route('kontak'), false);
        $response->assertSee(route('track'), false);
    }

    public function test_product_card_renders_promo_price_and_discount_badge(): void
    {
        $this->seedCatalog();

        $response = $this->get(route('home'));

        $response->assertOk();
        // SKU hanya tampil di halaman detail; di kartu ia hanya deretan angka bagi pembeli.
        $response->assertDontSee('JMY-PROMIL-001');
        $response->assertSee(rupiah(285000));
        $response->assertSee(rupiah(380000));
        $response->assertSee('25%');
    }

    public function test_storefront_pages_render(): void
    {
        $product = $this->seedCatalog();

        // Halaman statis kini dibawa migrasi data, jadi slug ini sudah ada.
        Page::query()->updateOrCreate(
            ['slug' => 'about'],
            ['title' => 'Tentang Kami', 'content' => 'Brand lokal Indonesia.', 'is_active' => true],
        );

        $routes = [
            route('home'),
            route('products.index'),
            route('products.show', $product->slug),
            route('cart.index'),
            route('wishlist.index'),
            route('kontak'),
            route('track'),
            route('pages.show', 'about'),
        ];

        foreach ($routes as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_catalog_filters_by_category_and_query(): void
    {
        $this->seedCatalog();

        $this->get(route('products.index', ['category' => Category::PROMIL_SLUG]))
            ->assertOk()
            ->assertSee('Jamu Paket Promil Ideal');

        $this->get(route('products.index', ['q' => 'zzz-tidak-ada-produk-ini']))
            ->assertOk()
            ->assertDontSee('Jamu Paket Promil Ideal');
    }

    public function test_halaman_produk_tidak_lagi_punya_bagian_tambahan(): void
    {
        $product = $this->seedCatalog();

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertDontSee('Tambahan (opsional)');
    }

    public function test_every_referenced_icon_symbol_is_defined_in_the_sprite(): void
    {
        $this->seedCatalog();

        $html = $this->get(route('home'))->assertOk()->getContent();

        preg_match_all('/<symbol id="([^"]+)"/', $html, $defined);
        preg_match_all('/<use href="#([^"]+)"/', $html, $referenced);

        $missing = array_diff(array_unique($referenced[1]), $defined[1]);

        // `<use href>` yang menunjuk simbol tak ada gagal DIAM-DIAM: ikon hilang, nol error.
        $this->assertSame([], array_values($missing));
        $this->assertNotEmpty($referenced[1]);
    }

    public function test_anchor_produk_tetap_ada_saat_katalog_kosong(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('id="produk"', $html);
    }
}
