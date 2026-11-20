<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\AdminMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMenuTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function rute(array $menu): array
    {
        $rute = [];

        foreach ($menu as $entri) {
            foreach ($entri['items'] ?? [$entri] as $item) {
                $rute[] = $item['route'];
            }
        }

        return $rute;
    }

    public function test_tanpa_konfigurasi_seluruh_entri_tampil(): void
    {
        config(['storefront.hidden.admin_menu' => [], 'storefront.disabled_pages' => []]);

        $rute = $this->rute(AdminMenu::items());

        $this->assertContains('admin.transfer.products', $rute);
        $this->assertContains('admin.transfer.users', $rute);
    }

    public function test_entri_yang_disembunyikan_hilang_dari_sidebar(): void
    {
        config(['storefront.hidden.admin_menu' => ['admin.transfer.products', 'admin.transfer.users']]);

        $rute = $this->rute(AdminMenu::items());

        $this->assertNotContains('admin.transfer.products', $rute);
        $this->assertNotContains('admin.transfer.users', $rute);
        $this->assertContains('admin.products.index', $rute);
        $this->assertContains('admin.users.index', $rute);
    }

    // Menyembunyikan seluruh isi satu grup tidak boleh menyisakan akordion kosong
    // di sidebar.
    public function test_grup_yang_habis_isinya_ikut_dibuang(): void
    {
        config(['storefront.hidden.admin_menu' => ['admin.stock-opname.index']]);

        $grup = array_column(AdminMenu::items(), 'group');

        $this->assertNotContains('Stok', $grup);
    }

    // Halamannya masih bisa dibuka lewat URL, jadi judul di topbar harus tetap
    // benar meski entrinya tidak ada di sidebar.
    public function test_judul_halaman_tersembunyi_tetap_terbaca(): void
    {
        config(['storefront.hidden.admin_menu' => ['admin.transfer.products'], 'storefront.disabled_pages' => []]);

        $this->get(route('admin.transfer.products'));

        $this->assertSame('Export / Import Data', AdminMenu::currentLabel());
    }

    // Halaman turunan yang tidak punya entri sendiri di sidebar tidak boleh jatuh
    // ke judul "Dashboard" di remah navigasi.
    public function test_judul_halaman_turunan_tidak_jatuh_ke_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create();
        $order = Order::factory()->create();

        $this->actingAs($admin)->get(route('admin.products.create'));
        $this->assertSame('Produk Baru', AdminMenu::currentLabel());

        $this->actingAs($admin)->get(route('admin.products.edit', $product));
        $this->assertSame('Ubah Produk', AdminMenu::currentLabel());

        $this->actingAs($admin)->get(route('admin.orders.show', $order));
        $this->assertSame('Detail Pesanan', AdminMenu::currentLabel());
    }

    // Permintaan pemilik 2026-11-19: ekspor/impor produk dinonaktifkan sementara
    // lewat storefront.disabled_pages; sejak 2026-11-20 ekspor/impor pengguna juga.
    public function test_konfigurasi_bawaan_menyembunyikan_seluruh_ekspor_impor(): void
    {
        $rute = $this->rute(AdminMenu::items());

        $this->assertNotContains('admin.transfer.products', $rute);
        $this->assertNotContains('admin.transfer.users', $rute);
    }
}
