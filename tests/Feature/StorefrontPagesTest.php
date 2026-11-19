<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Testimonial;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SeedsCatalog;
use Tests\TestCase;

class StorefrontPagesTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCatalog;

    public function test_sembilan_halaman_memakai_banner_judul_yang_sama(): void
    {
        $product = $this->seedCatalog();
        $order = Order::factory()->create();

        // Halaman statis kini dibawa migrasi data, jadi tes cukup memastikan
        // keberadaannya alih-alih membuat ulang dan bentrok pada slug unik.
        Page::query()->updateOrCreate(
            ['slug' => 'about'],
            ['title' => 'Tentang Kami', 'content' => 'Toko jamu ramuan rempah.', 'is_active' => true],
        );

        $halaman = [
            route('products.index') => 'Katalog Produk',
            route('products.show', $product->slug) => $product->name,
            route('cart.index') => 'Keranjang Belanja',
            route('pages.show', 'about') => 'Tentang Kami',
            route('categories') => 'Kategori Produk',
            route('wishlist.index') => 'Favorit Saya',
            route('track') => 'Lacak Pesanan',
            route('kontak') => 'Kontak',
            route('checkout.success', $order) => 'Selesaikan Pembayaran',
        ];

        foreach ($halaman as $url => $judul) {
            $response = $this->get($url)->assertOk();
            $response->assertSee('grid-tex', false);
            $response->assertSee($judul, false);
        }
    }

    public function test_halaman_tidak_lagi_memanggil_breadcrumb_polos(): void
    {
        $this->seedCatalog();

        $berkas = glob(resource_path('views/pages/storefront/*.blade.php'));
        $pelanggar = [];

        foreach ($berkas as $path) {
            if (str_contains((string) file_get_contents($path), '<x-breadcrumb')) {
                $pelanggar[] = basename($path);
            }
        }

        $this->assertSame([], $pelanggar);
    }

    public function test_dua_halaman_pendukung_bergaya_toko(): void
    {
        $this->seedCatalog();

        foreach ([route('track'), route('kontak')] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_home_shows_promil_highlight_and_herbal_rail(): void
    {
        $promil = Category::factory()->create(['slug' => Category::PROMIL_SLUG, 'name' => 'Paket Promil Pasutri']);
        $herbal = Category::factory()->create(['slug' => Category::HERBAL_SLUG, 'name' => 'Jamu Ramuan Rempah']);
        Product::factory()->create(['category_id' => $promil->id, 'name' => 'Jamu Paket Promil Ideal', 'is_active' => true]);
        Product::factory()->create(['category_id' => $herbal->id, 'name' => 'Jamu Temulawak', 'is_active' => true]);

        $this->get(route('home'))->assertOk()
            ->assertSee('Sejak 2020')
            ->assertSee('1000+ pelanggan')
            ->assertSee('id="sorotan-promil"', false)
            ->assertSeeInOrder(['Jamu Paket Promil Ideal', 'Jamu Temulawak'])
            ->assertDontSee('BEST SELLER')
            ->assertDontSee('NEW ARRIVAL')
            ->assertDontSee('terjual');
    }

    public function test_home_hides_empty_sections(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('id="sorotan-promil"', false);
    }

    public function test_beranda_menampilkan_testimoni_sebagai_kartu_teks(): void
    {
        $this->seedCatalog();

        Testimonial::query()->create([
            'name' => 'Budi Santoso',
            'role' => 'Pelanggan Setia',
            'rating' => 5,
            'content' => 'Partnya sesuai dan sampai cepat.',
        ]);

        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('Partnya sesuai dan sampai cepat.');
        $response->assertSee('Budi Santoso');
        $response->assertSee('Pelanggan Setia');

        $this->assertMatchesRegularExpression(
            '/<span class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-brand-dark[^"]*">BS<\/span>/',
            $response->getContent(),
        );
    }

    public function test_beranda_menampilkan_gambar_testimoni_sebagai_lampiran_yang_bisa_diperbesar(): void
    {
        $this->seedCatalog();

        $testimoni = Testimonial::query()->create([
            'name' => 'Budi Santoso',
            'rating' => 5,
            'content' => 'Partnya sesuai dan sampai cepat.',
            'image' => 'testimonials/bukti-chat.webp',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.$testimoni->image_url.'"', false)
            ->assertSee('src="'.$testimoni->image_url.'"', false)
            ->assertSee('Perbesar gambar testimoni dari Budi Santoso');
    }

    public function test_gambar_testimoni_selebar_kartu_dengan_tinggi_seragam(): void
    {
        $this->seedCatalog();

        Testimonial::query()->create([
            'name' => 'Budi Santoso',
            'rating' => 5,
            'content' => 'Partnya sesuai dan sampai cepat.',
            'image' => 'testimonials/bukti-chat.webp',
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a [^>]*aria-label="Perbesar gambar testimoni dari Budi Santoso"[^>]*class="(?=[^"]*-mx-6)(?=[^"]*aspect-\[4/3\])[^"]*"#',
            $html,
        );
    }

    public function test_testimoni_tanpa_gambar_tidak_punya_lampiran(): void
    {
        $this->seedCatalog();

        Testimonial::query()->create(['name' => 'Rina', 'rating' => 5, 'content' => 'Mantap.']);

        $this->get(route('home'))->assertOk()->assertDontSee('Perbesar gambar testimoni');
    }

    public function test_beranda_tidak_memakai_kosakata_katalog_lama(): void
    {
        $this->seedCatalog();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Kesehatan, Pendidikan, Industri', false);
    }

    public function test_katalog_memuat_dua_belas_produk_lalu_menambah_lewat_load_more(): void
    {
        $kategori = Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);

        Product::factory()->count(20)->inStock()->create(['category_id' => $kategori->id]);

        $component = Livewire::test('pages::storefront.catalog');

        $this->assertSame(12, substr_count($component->html(), 'wire:key="p-'));

        $component->call('loadMore');

        $this->assertSame(20, substr_count($component->html(), 'wire:key="p-'));
    }

    public function test_katalog_menyembunyikan_load_more_saat_semua_produk_tampil(): void
    {
        $kategori = Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);

        Product::factory()->count(3)->inStock()->create(['category_id' => $kategori->id]);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertDontSee('Lebih banyak');
    }

    public function test_katalog_menampilkan_load_more_saat_masih_ada_produk_tersisa(): void
    {
        $kategori = Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);

        Product::factory()->count(20)->inStock()->create(['category_id' => $kategori->id]);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('Lebih banyak');
    }

    public function test_tombol_lebih_banyak_tanpa_panah_dan_bergaris_tipis(): void
    {
        $kategori = Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);
        Product::factory()->count(20)->inStock()->create(['category_id' => $kategori->id]);

        $html = $this->get(route('products.index'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<button[^>]*wire:click="loadMore"[^>]*>.*?</button>#s', $html, $tombol));
        $this->assertStringNotContainsString('i-arrow-down', $tombol[0]);
        $this->assertStringNotContainsString('border-2', $tombol[0]);
        $this->assertMatchesRegularExpression('#class="[^"]*\bborder\b[^"]*\bborder-gray-300\b#', $tombol[0]);
    }

    // Bintang lima yang digambar mati untuk semua produk tetap tidak boleh
    // kembali. Rating kini berasal dari kolom products.rating, membalik
    // keputusan #10 spec revamp 2026-08-11 atas keputusan pemilik proyek
    // pada 2026-10-03.
    public function test_detail_produk_punya_bar_aksi_mobile(): void
    {
        $product = $this->seedCatalog();

        $response = $this->get(route('products.show', $product->slug))->assertOk();

        $response->assertSee('id="mobile-buy-bar"', false);
        $response->assertDontSee('Rating 5 dari 5', false);
    }

    public function test_detail_produk_memakai_rail_untuk_produk_terkait(): void
    {
        $product = $this->seedCatalog();

        Product::factory()->count(3)->inStock()->create(['category_id' => $product->category_id]);

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('PRODUK REKOMENDASI')
            ->assertSee('x-data="productRail"', false);
    }

    public function test_keranjang_dapat_dikosongkan_sekaligus(): void
    {
        $product = $this->seedCatalog();

        app(CartService::class)->add($product->id, 2);

        Livewire::test('pages::storefront.cart')
            ->call('clearAll')
            ->assertOk();

        $this->assertSame(0, app(CartService::class)->count());
    }

    public function test_keranjang_kosong_menampilkan_ajakan_belanja(): void
    {
        $this->seedCatalog();

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Keranjang Kamu Kosong')
            ->assertSee('Mulai Belanja');
    }

    public function test_halaman_konten_punya_baris_bagikan(): void
    {
        $this->seedCatalog();

        Page::query()->create([
            'title' => 'Syarat dan Ketentuan',
            'slug' => 'syarat',
            'content' => '<p>Isi halaman.</p>',
            'is_active' => true,
        ]);

        $this->get(route('pages.show', 'syarat'))
            ->assertOk()
            ->assertSee('Bagikan')
            ->assertSee('#i-share', false);
    }

    public function test_unknown_product_slug_is_404(): void
    {
        $this->get('/product/tidak-pernah-ada')->assertNotFound();
    }
}
