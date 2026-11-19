<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Page;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\Slide;
use App\Models\StockOpname;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Variant;
use App\Notifications\NewOrderNotification;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminListPagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_kategori_dapat_dicari(): void
    {
        Category::query()->create(['name' => 'Rem dan Kampas', 'slug' => 'rem', 'is_active' => true]);
        Category::query()->create(['name' => 'Kelistrikan', 'slug' => 'kelistrikan', 'is_active' => true]);

        Livewire::actingAs($this->admin())
            ->test('pages::admin.categories.index')
            ->set('cari', 'Rem')
            ->assertSee('Rem dan Kampas')
            ->assertDontSee('Kelistrikan');
    }

    public function test_kategori_dipaginasi(): void
    {
        Category::factory()->count(20)->create();

        $this->actingAs($this->admin())->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Menampilkan 15 dari 20');
    }

    public function test_mengubah_filter_mengembalikan_ke_halaman_satu(): void
    {
        $kategori = Category::query()->create(['name' => 'Rem', 'slug' => 'rem', 'is_active' => true]);
        Product::factory()->count(40)->create(['category_id' => $kategori->id, 'is_active' => true]);

        Livewire::actingAs($this->admin())
            ->test('pages::admin.products.index')
            ->call('gotoPage', 3)
            ->assertSet('paginators.page', 3)
            ->set('filterStatus', 'aktif')
            ->assertSet('paginators.page', 1);
    }

    public function test_pencarian_produk_tetap_menghormati_filter_kategori(): void
    {
        $kategoriA = Category::query()->create(['name' => 'Kategori A', 'slug' => 'kategori-a', 'is_active' => true]);
        $kategoriB = Category::query()->create(['name' => 'Kategori B', 'slug' => 'kategori-b', 'is_active' => true]);

        $cocok = Product::factory()->create(['name' => 'Kampas Rem Sakura', 'category_id' => $kategoriA->id]);
        $bedaKategori = Product::factory()->create(['name' => 'Kampas Rem Yamaha', 'category_id' => $kategoriB->id]);

        Livewire::actingAs($this->admin())
            ->test('pages::admin.products.index')
            ->set('cari', 'Kampas Rem')
            ->set('filterKategori', $kategoriA->id)
            ->assertSee($cocok->name)
            ->assertDontSee($bedaKategori->name);
    }

    // Kartu ringkasan harus memakai ambang, kebijakan is_active, dan kesadaran varian
    // yang sama dengan panel Stok Menipis di dasbor.
    public function test_kartu_stok_menipis_produk_sadar_varian_ambang_dan_status_aktif(): void
    {
        $kategori = Category::query()->create(['name' => 'Rem', 'slug' => 'rem', 'is_active' => true]);

        Product::factory()->create(['category_id' => $kategori->id, 'stock' => 3, 'is_active' => true]);
        Product::factory()->create(['category_id' => $kategori->id, 'stock' => 8, 'is_active' => true]);
        Product::factory()->create(['category_id' => $kategori->id, 'stock' => 1, 'is_active' => false]);

        $bervarian = Product::factory()->create(['category_id' => $kategori->id, 'stock' => 0, 'is_active' => true]);
        Variant::factory()->create(['product_id' => $bervarian->id, 'stock' => 50]);
        Variant::factory()->create(['product_id' => $bervarian->id, 'stock' => 80]);

        $komponen = Livewire::actingAs($this->admin())->test('pages::admin.products.index');

        $this->assertSame(1, $komponen->instance()->stokMenipis);
    }

    public function test_nilai_persediaan_ikut_menghitung_stok_varian(): void
    {
        $kategori = Category::query()->create(['name' => 'Rem', 'slug' => 'rem', 'is_active' => true]);

        Product::factory()->create([
            'category_id' => $kategori->id,
            'stock' => 2,
            'original_price' => 100_000,
            'promo_price' => null,
        ]);

        $bervarian = Product::factory()->create([
            'category_id' => $kategori->id,
            'stock' => 0,
            'original_price' => 999_000,
            'promo_price' => null,
        ]);
        Variant::factory()->create(['product_id' => $bervarian->id, 'stock' => 3, 'price' => 50_000, 'promo_price' => null]);
        Variant::factory()->create(['product_id' => $bervarian->id, 'stock' => 1, 'price' => 20_000, 'promo_price' => 10_000]);

        $komponen = Livewire::actingAs($this->admin())->test('pages::admin.products.index');

        $this->assertSame(360_000, $komponen->instance()->nilaiPersediaan);
    }

    // Stok negatif dari data lama dikalikan harga unsigned membuat MySQL menolak
    // dan halaman produk admin menjadi 500. Stok negatif dihitung nol.
    public function test_stok_negatif_tidak_membuat_halaman_produk_galat(): void
    {
        $kategori = Category::query()->create(['name' => 'Lampu', 'slug' => 'lampu', 'is_active' => true]);

        Product::factory()->create(['category_id' => $kategori->id, 'stock' => -2, 'original_price' => 100_000, 'promo_price' => null]);
        $bervarian = Product::factory()->create(['category_id' => $kategori->id, 'stock' => 0, 'original_price' => 9_240_000, 'promo_price' => null]);
        Variant::factory()->create(['product_id' => $bervarian->id, 'stock' => -1, 'price' => 9_240_000, 'promo_price' => 8_365_000]);
        Variant::factory()->create(['product_id' => $bervarian->id, 'stock' => 3, 'price' => 9_240_000, 'promo_price' => 8_365_000]);

        $this->actingAs($this->admin())->get(route('admin.products.index'))->assertOk();

        $komponen = Livewire::actingAs($this->admin())->test('pages::admin.products.index');
        $this->assertSame(3 * 8_365_000, $komponen->instance()->nilaiPersediaan);
    }

    public function test_kolom_turunan_pesanan_menampilkan_metode_dan_status_pembayaran(): void
    {
        Order::factory()->create([
            'status' => OrderStatus::SHIPPED,
            'order_number' => 'INV-UJI-1',
        ]);

        $this->actingAs($this->admin())->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSeeInOrder(['INV-UJI-1', 'Lunas'])
            ->assertDontSee('>Manual<', false);
    }

    // Dasbor, daftar, dan detail pesanan pernah punya tiga gaya lencana berbeda —
    // pesanan dibatalkan tampil merah brand di daftar tapi rose di dua halaman lain.
    public function test_lencana_status_pesanan_seragam_di_dasbor_daftar_dan_detail(): void
    {
        Order::factory()->create(['status' => OrderStatus::CANCELLED, 'order_number' => 'INV-BATAL']);

        $halaman = [
            route('admin.dashboard'),
            route('admin.orders.index'),
            route('admin.orders.show', 'INV-BATAL'),
        ];

        foreach ($halaman as $url) {
            $this->actingAs($this->admin())->get($url)
                ->assertOk()
                ->assertSee('rounded-full bg-rose-500"></span>Dibatalkan', false)
                ->assertDontSee('ring-1 ring-inset', false);
        }
    }

    public function test_kolom_status_pengguna_diturunkan_dari_verifikasi_email(): void
    {
        User::factory()->create(['name' => 'Sudah Verifikasi', 'email_verified_at' => now()]);
        User::factory()->unverified()->create(['name' => 'Belum Verifikasi']);

        $this->actingAs($this->admin())->get(route('admin.users.index'))
            ->assertOk()
            ->assertSeeInOrder(['Sudah Verifikasi', 'Terverifikasi'])
            ->assertSee('Belum verifikasi');
    }

    // Tautan lama seperti /admin/orders?status=dibatalkan harus tetap menyaring, dan
    // tombol maju-mundur peramban harus mengembalikan pilihan filternya.
    public function test_filter_status_pesanan_dapat_ditautkan_lewat_url(): void
    {
        Order::factory()->create(['status' => OrderStatus::CANCELLED, 'order_number' => 'INV-BATAL']);
        Order::factory()->create(['status' => OrderStatus::PAID, 'order_number' => 'INV-LUNAS']);

        $this->actingAs($this->admin())->get(route('admin.orders.index', ['status' => OrderStatus::CANCELLED->value]))
            ->assertOk()
            ->assertSee('INV-BATAL')
            ->assertDontSee('INV-LUNAS');
    }

    public function test_filter_peran_pengguna_dapat_ditautkan_lewat_url(): void
    {
        User::factory()->create(['name' => 'Pelanggan Biasa', 'role' => 'customer']);

        $this->actingAs($this->admin())->get(route('admin.users.index', ['role' => 'admin']))
            ->assertOk()
            ->assertDontSee('Pelanggan Biasa');
    }

    public function test_halaman_konten_dapat_dicari_dan_dipaginasi(): void
    {
        // Halaman statis kini dibawa migrasi data, jadi hitungannya dinolkan dulu
        // agar angka paginasi di bawah tetap pasti.
        Page::query()->delete();

        Page::query()->create(['title' => 'Syarat dan Ketentuan', 'slug' => 'syarat', 'content' => '<p>x</p>', 'is_active' => true]);
        Page::factory()->count(20)->create();

        Livewire::actingAs($this->admin())
            ->test('pages::admin.pages.index')
            ->set('cari', 'Syarat')
            ->assertSee('Syarat dan Ketentuan');

        $this->actingAs($this->admin())->get(route('admin.pages.index'))
            ->assertOk()
            ->assertSee('Menampilkan 15 dari 21');
    }

    public function test_slide_dipaginasi(): void
    {
        // Slide hero bawaan kini dibawa migrasi data.
        Slide::query()->delete();

        for ($i = 1; $i <= 20; $i++) {
            Slide::query()->create(['image' => 'slides/dummy.webp', 'is_active' => false, 'sort_order' => $i]);
        }

        $this->actingAs($this->admin())->get(route('admin.slides.index'))
            ->assertOk()
            ->assertSee('Menampilkan 15 dari 20');
    }

    public function test_notifikasi_dipaginasi(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create();

        for ($i = 0; $i < 20; $i++) {
            $admin->notify(new NewOrderNotification($order));
        }

        $this->actingAs($admin)->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('Menampilkan 15 dari 20');
    }

    public function test_pesanan_dipaginasi(): void
    {
        Order::factory()->count(20)->create();

        $this->actingAs($this->admin())->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('Menampilkan 15 dari 20');
    }

    public function test_produk_dipaginasi(): void
    {
        Product::factory()->count(20)->create();

        $this->actingAs($this->admin())->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Menampilkan 15 dari 20');
    }

    public function test_testimoni_dipaginasi(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            Testimonial::query()->create(['name' => "Pelanggan {$i}", 'rating' => 5]);
        }

        $this->actingAs($this->admin())->get(route('admin.testimonials.index'))
            ->assertOk()
            ->assertSee('Menampilkan 15 dari 20');
    }

    public function test_pengguna_dipaginasi(): void
    {
        $admin = $this->admin();
        User::factory()->count(19)->create();

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Menampilkan 15 dari 20');
    }

    public function test_stok_opname_dipaginasi(): void
    {
        $admin = $this->admin();
        $produk = Product::factory()->create();

        for ($i = 1; $i <= 20; $i++) {
            StockOpname::query()->create([
                'product_id' => $produk->id,
                'user_id' => $admin->id,
                'system_stock' => 10,
                'physical_stock' => 10,
            ]);
        }

        $this->actingAs($admin)->get(route('admin.stock-opname.index'))
            ->assertOk()
            ->assertSee('Menampilkan 15 dari 20');
    }

    public function test_pengaturan_tersimpan_dan_tampil_lagi_setelah_reload(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::admin.settings')
            ->set('values.site_name', 'Jamu Mak Yuli Uji')
            ->set('values.site_tagline', 'Tagline Uji Coba')
            ->call('save');

        $this->assertSame('Jamu Mak Yuli Uji', SettingService::get('site_name'));
        $this->assertSame('Tagline Uji Coba', SettingService::get('site_tagline'));

        Livewire::actingAs($this->admin())
            ->test('pages::admin.settings')
            ->assertSet('values.site_name', 'Jamu Mak Yuli Uji')
            ->assertSet('values.site_tagline', 'Tagline Uji Coba');
    }

    public function test_payment_method_column_never_says_manual(): void
    {
        $tanpaAttempt = Order::factory()->create();
        $snap = Order::factory()->create();
        PaymentAttempt::factory()->create(['order_id' => $snap->id, 'payment_method' => null, 'latest_notification_payload' => null]);
        $qris = Order::factory()->create();
        PaymentAttempt::factory()->create(['order_id' => $qris->id, 'payment_method' => null, 'latest_notification_payload' => ['payment_type' => 'qris']]);

        $this->actingAs($this->admin())->get(route('admin.orders.index'))
            ->assertOk()
            ->assertDontSee('>Manual<', false)
            ->assertSee('>Midtrans<', false)
            ->assertSee('>QRIS<', false);
    }
}
