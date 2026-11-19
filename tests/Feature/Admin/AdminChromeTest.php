<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminChromeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    // Setiap kotak cari di bilah penyaring punya <span class="sr-only">Cari</span> di
    // dalam labelnya; select di sebelahnya tidak punya apa-apa, jadi pembaca layar
    // mengumumkannya sebagai combobox tanpa nama.
    public function test_setiap_select_penyaring_admin_punya_nama_untuk_pembaca_layar(): void
    {
        $tanpaNama = [];

        foreach (File::allFiles(resource_path('views/pages/admin')) as $berkas) {
            preg_match_all('/<select\b[^>]*>/', (string) file_get_contents($berkas->getPathname()), $cocok);

            foreach ($cocok[0] as $tag) {
                if (! str_contains($tag, 'aria-label')) {
                    $tanpaNama[] = $berkas->getFilename().': '.$tag;
                }
            }
        }

        $this->assertSame([], $tanpaNama);
    }

    public function test_sidebar_menampilkan_seluruh_label_menu(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();

        foreach (['Dashboard', 'Kategori', 'List Produk', 'List Pesanan',
            'List Pengguna', 'Halaman', 'Slide Hero', 'Testimoni',
            'Pengaturan Global'] as $label) {
            $response->assertSee($label);
        }

        // Stok Opname dan Laporan Penjualan dinonaktifkan lewat storefront.disabled_pages.
        $response->assertDontSee('Stok Opname');
        $response->assertDontSee('Laporan Penjualan');
    }

    public function test_sidebar_memakai_rel_hitam_dan_wordmark_panel_admin(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('bg-brand-dark', false)
            ->assertSee('Panel Admin', false)
            ->assertSee('Lihat Toko');
    }

    public function test_penanda_aktif_menyala_di_halaman_yang_tepat(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.products.index'))
            ->assertOk()
            ->getContent();

        preg_match_all('/<a[^>]*class="[^"]*nav-link[^"]*"[^>]*>/', $html, $tautan);

        $aktif = array_values(array_filter(
            $tautan[0],
            fn (string $tag): bool => str_contains($tag, 'active'),
        ));

        $this->assertCount(1, $aktif, 'Tepat satu tautan sidebar harus bertanda aktif.');
        $this->assertStringContainsString(route('admin.products.index'), $aktif[0]);
    }

    // Mockup dashboard-*.html menandai item aktif hanya dengan pil putih + bar merah di
    // sisi kiri. Aturan garis bawah bergaya storefront (.nav-link::after) pernah ikut
    // menempel di sidebar admin dan menggambar garis merah di bawah item aktif.
    public function test_penanda_aktif_sidebar_mengikuti_mockup(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertStringNotContainsString('.nav-link::after', $css);
        $this->assertStringContainsString('.nav-link.active .nav-bar', $css);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.products.index'))
            ->assertOk()
            ->getContent();

        preg_match_all('/<a[^>]*class="[^"]*nav-link[^"]*"[^>]*>/', $html, $tautan);

        $aktif = array_values(array_filter(
            $tautan[0],
            fn (string $tag): bool => str_contains($tag, 'active'),
        ));

        $this->assertCount(1, $aktif);
        $this->assertStringNotContainsString('font-semibold', $aktif[0], 'Mockup tidak menebalkan item aktif.');
    }

    // Ikon lup pernah dipasang absolute di left-3 di dalam kotak cari. Utility pl-9 yang
    // menyediakan ruangnya kalah dari class .field (spesifisitas sama, .field ditulis
    // belakangan), jadi ikonnya menutupi huruf pertama placeholder.
    public function test_kotak_cari_admin_tidak_memuat_ikon_di_dalam_field(): void
    {
        $pelanggar = [];

        foreach (File::allFiles(resource_path('views/pages/admin')) as $berkas) {
            if (str_contains((string) file_get_contents($berkas->getPathname()), '#i-search')) {
                $pelanggar[] = $berkas->getFilename();
            }
        }

        $this->assertSame([], $pelanggar);
    }

    public function test_topbar_menampilkan_breadcrumb_judul_halaman(): void
    {
        $this->actingAs($this->admin())->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Beranda')
            ->assertSee('List Produk');
    }

    public function test_topbar_menyediakan_pengalih_tema_dan_profil(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('$flux.appearance', false)
            ->assertSee('#i-sun', false)
            ->assertSee('#i-moon', false)
            ->assertSee('Keluar');
    }

    public function test_chrome_flux_tidak_dipakai_lagi_di_admin(): void
    {
        $isi = (string) file_get_contents(resource_path('views/layouts/admin.blade.php'));

        foreach (['<flux:sidebar', '<flux:header', '<flux:main'] as $tag) {
            $this->assertStringNotContainsString($tag, $isi);
        }
    }
}
