<?php

namespace Tests\Feature\Admin;

use App\Support\AdminMenu;
use Tests\TestCase;

class AdminMenuTest extends TestCase
{
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
        config(['storefront.hidden.admin_menu' => []]);

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
        config(['storefront.hidden.admin_menu' => ['admin.transfer.products']]);

        $this->get(route('admin.transfer.products'));

        $this->assertSame('Export / Import Data', AdminMenu::currentLabel());
    }

    // Permintaan pemilik 2026-10-26: ekspor/impor produk dibuka lagi di
    // sidebar, ekspor/impor pengguna tetap tersembunyi.
    public function test_konfigurasi_bawaan_menampilkan_ekspor_impor_produk_saja(): void
    {
        $rute = $this->rute(AdminMenu::items());

        $this->assertContains('admin.transfer.products', $rute);
        $this->assertNotContains('admin.transfer.users', $rute);
    }
}
