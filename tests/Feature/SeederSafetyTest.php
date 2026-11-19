<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use App\Models\Slide;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Katalog produksi berasal dari paket impor Tokopedia, bukan dari seeder.
 * Berkas ini mengunci janji bahwa `php artisan db:seed` yang terlanjur
 * dijalankan di server berisi data tidak merusak apa pun.
 */
class SeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function katalog(): Product
    {
        $kategori = Category::query()->create(['name' => 'Ramuan Uji', 'slug' => 'ramuan-uji', 'is_active' => true]);

        $produk = Product::query()->create([
            'category_id' => $kategori->id,
            'name' => 'Jamu Uji Lama',
            'slug' => 'jamu-uji-lama',
            'sku' => 'TKP-123',
            'original_price' => 250000,
            'description' => 'Deskripsi.',
            'weight' => 500,
            'stock' => 99,
            'is_active' => true,
        ]);

        ProductImage::query()->create([
            'product_id' => $produk->id,
            'path' => 'products/contoh.webp',
            'is_main' => true,
            'sort_order' => 0,
        ]);

        return $produk;
    }

    public function test_seeder_tidak_menyentuh_katalog_sama_sekali(): void
    {
        $produk = $this->katalog();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Product::query()->count());
        $this->assertSame(1, Category::query()->count());
        $this->assertSame(1, ProductImage::query()->count());

        $segar = $produk->fresh();
        $this->assertSame(99, $segar->stock);
        $this->assertSame(250000, $segar->original_price);
        $this->assertSame('TKP-123', $segar->sku);
    }

    public function test_menjalankan_seeder_dua_kali_tidak_menggandakan_apa_pun(): void
    {
        $this->katalog();

        $this->seed(DatabaseSeeder::class);

        $jumlah = [
            'produk' => Product::query()->count(),
            'kategori' => Category::query()->count(),
            'pengguna' => User::query()->count(),
            'setting' => Setting::query()->count(),
            'halaman' => Page::query()->count(),
        ];

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($jumlah, [
            'produk' => Product::query()->count(),
            'kategori' => Category::query()->count(),
            'pengguna' => User::query()->count(),
            'setting' => Setting::query()->count(),
            'halaman' => Page::query()->count(),
        ]);
    }

    // Kata sandi admin yang sudah diganti lewat panel tidak boleh kembali ke
    // bawaan hanya karena seeder terlanjur dijalankan lagi.
    public function test_kata_sandi_admin_tidak_direset(): void
    {
        $this->seed(DatabaseSeeder::class);

        User::query()->where('email', 'admin@jamumakyuli.com')
            ->update(['password' => Hash::make('sandi-produksi-yang-kuat')]);

        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@jamumakyuli.com')->firstOrFail();
        $this->assertTrue(Hash::check('sandi-produksi-yang-kuat', $admin->password));
        $this->assertFalse(Hash::check('password', $admin->password));
    }

    public function test_pengaturan_yang_disunting_lewat_panel_tetap_utuh(): void
    {
        $this->seed(DatabaseSeeder::class);

        Setting::query()->where('key', 'site_phone')->update(['value' => '08999999999']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('08999999999', Setting::query()->where('key', 'site_phone')->value('value'));
    }

    public function test_isi_halaman_yang_disunting_tetap_utuh(): void
    {
        $this->seed(DatabaseSeeder::class);

        Page::query()->where('slug', 'about')->update(['content' => '<p>Profil terbaru toko.</p>']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('<p>Profil terbaru toko.</p>', Page::query()->where('slug', 'about')->value('content'));
    }

    // PageSeeder dulu menghapus halaman ber-slug tentang-kami setiap kali jalan.
    public function test_halaman_buatan_admin_tidak_dihapus(): void
    {
        Page::query()->create([
            'slug' => 'tentang-kami',
            'title' => 'Tentang Kami',
            'content' => '<p>Halaman buatan admin.</p>',
            'is_active' => true,
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertNotNull(Page::query()->where('slug', 'tentang-kami')->first());
    }

    public function test_fresh_seed_builds_the_store(): void
    {
        Storage::fake('public');

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(7, Product::query()->count());
        $this->assertSame(2, Category::query()->count());
        $this->assertSame(7, ProductImage::query()->count());
        $this->assertSame(
            ['about', 'faq', 'privacy-policy', 'reseller-terms', 'return-policy', 'shipping-policy'],
            Page::query()->orderBy('slug')->pluck('slug')->all()
        );
        $this->assertSame(3, Slide::query()->count());
        $this->assertSame(0, Testimonial::query()->count());
        $this->assertSame(250000, Product::query()->where('sku', 'JMY-PROMIL-GEMOY')->value('original_price'));
        $this->assertSame(70000, Product::query()->where('sku', 'JMY-TEMULAWAK')->value('original_price'));
        $this->assertSame(2, Product::query()->whereHas('category', fn ($q) => $q->where('slug', Category::PROMIL_SLUG))->count());
        $this->assertSame('Jamu Mak Yuli', setting('site_name'));
        $this->assertSame('087879865789', setting('site_whatsapp'));
        $this->assertSame('Senin sampai Sabtu, 08.00 sampai 17.00 WIB', setting('site_hours'));
        $this->assertTrue(User::query()->where('email', 'admin@jamumakyuli.com')->exists());
        Storage::disk('public')->assertExists(ProductImage::query()->value('path'));
        Storage::disk('public')->assertExists(Slide::query()->value('image'));
    }

    public function test_catalog_seeder_does_not_run_twice(): void
    {
        Storage::fake('public');

        $this->seed(DatabaseSeeder::class);
        Product::query()->where('sku', 'JMY-TEMULAWAK')->update(['original_price' => 75000]);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(7, Product::query()->count());
        $this->assertSame(75000, Product::query()->where('sku', 'JMY-TEMULAWAK')->value('original_price'));
    }
}
