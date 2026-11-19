<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCatalog;
use Tests\TestCase;

class StorefrontChromeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCatalog;

    public function test_header_menampilkan_logo_pencarian_dan_kategori_dari_database(): void
    {
        $this->seedCatalog();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Mak Yuli</span>', false)
            ->assertSee('name="q"', false)
            ->assertSee(route('products.index'), false)
            ->assertSee('Paket Promil Pasutri');
    }

    public function test_header_menyediakan_pengalih_tema(): void
    {
        $this->seedCatalog();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('$flux.appearance', false)
            ->assertSee('#i-sun', false)
            ->assertSee('#i-moon', false);
    }

    public function test_header_menawarkan_daftar_untuk_tamu(): void
    {
        $this->seedCatalog();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Daftar')
            ->assertSee(route('register'), false);
    }

    public function test_header_menyembunyikan_bell_notifikasi_dari_tamu(): void
    {
        $this->seedCatalog();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('aria-label="Notifikasi"', false);
    }

    public function test_header_menampilkan_bell_notifikasi_untuk_pengguna_masuk(): void
    {
        $this->seedCatalog();

        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('aria-label="Notifikasi"', false);
    }

    public function test_header_mempertahankan_tautan_wishlist_dan_keranjang(): void
    {
        $this->seedCatalog();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('wishlist.index'), false)
            ->assertSee(route('cart.index'), false);
    }

    public function test_footer_menampilkan_lima_kolom_toko(): void
    {
        $this->seedCatalog();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('<footer', $html);
        $this->assertStringContainsString('<nav aria-label="Navigasi">', $html);
        $this->assertStringContainsString('<nav aria-label="Kategori">', $html);
        $this->assertStringContainsString('<nav aria-label="Bantuan">', $html);
        $this->assertStringContainsString('Customer Care', $html);
    }

    public function test_bottom_nav_tampil_di_beranda(): void
    {
        $this->seedCatalog();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('aria-label="Navigasi mobile"', false);
    }

    public function test_bottom_nav_disembunyikan_di_detail_produk_dan_keranjang(): void
    {
        $product = $this->seedCatalog();

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertDontSee('aria-label="Navigasi mobile"', false);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertDontSee('aria-label="Navigasi mobile"', false);
    }

    // Permintaan pemilik 2026-10-08: alamat tidak lagi ditampilkan di kolom
    // pertama footer. Halaman Kontak tetap menampilkannya.
    public function test_footer_menampilkan_alamat_toko_dengan_tautan_maps(): void
    {
        $this->seedCatalog();
        Setting::query()->updateOrCreate(['key' => 'site_address'], ['value' => 'Dk. Pilangbangu, Ds. Munggur, Kec. Mojogedang, Kab. Karanganyar']);
        cache()->flush();

        $html = $this->get(route('home'))->assertOk()->getContent();
        $footer = substr($html, strpos($html, '<footer'), strpos($html, '</footer>') - strpos($html, '<footer'));

        $this->assertStringContainsString('Dk. Pilangbangu', $footer);
        $this->assertStringContainsString('#i-pin', $footer);
    }

    // Permintaan pemilik 2026-10-08: halaman Kontak tidak lagi menampilkan alamat
    // dan tautan Google Maps; nomor WhatsApp tampil di atas nomor telepon.
    public function test_halaman_kontak_menampilkan_alamat_dan_whatsapp_di_atas_telepon(): void
    {
        $this->seedCatalog();
        foreach (['site_address' => 'Dk. Pilangbangu, Ds. Munggur, Kec. Mojogedang, Kab. Karanganyar', 'site_whatsapp' => '087879865789', 'site_phone' => '0215550199'] as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        cache()->flush();

        $html = $this->get(route('kontak'))->assertOk()->getContent();
        $isi = substr($html, strpos($html, 'Kunjungi atau Hubungi Kami'));

        $this->assertStringContainsString('Dk. Pilangbangu', $isi);
        $this->assertStringContainsString('#i-pin', $isi);

        $this->assertStringContainsString('href="'.e(wa_href()).'"', $isi);
        $whatsapp = strpos($isi, '>087879865789<');
        $telepon = strpos($isi, '>0215550199<');
        $this->assertNotFalse($whatsapp);
        $this->assertNotFalse($telepon);
        $this->assertLessThan($telepon, $whatsapp);
    }

    // Permintaan pemilik 2026-10-08: baris "Respon cepat setiap hari via WhatsApp"
    // beserta ikon jamnya dihapus dari halaman Kontak.
    public function test_halaman_kontak_tanpa_baris_respon_cepat(): void
    {
        $this->seedCatalog();

        $html = $this->get(route('kontak'))->assertOk()->getContent();
        $isi = substr($html, strpos($html, 'Kunjungi atau Hubungi Kami'));

        $this->assertStringNotContainsString('Respon cepat setiap hari via WhatsApp', $isi);
        $this->assertStringNotContainsString('#i-clock', $isi);
    }

    // Permintaan pemilik 2026-10-27: email toko tampil di bawah telepon dan
    // paragraf pembuka di bawah judul kontak dihapus.
    public function test_halaman_kontak_menampilkan_email_di_bawah_telepon(): void
    {
        $this->seedCatalog();
        foreach (['site_phone' => '0215550199', 'site_email' => 'toko@jamumakyuli.test'] as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        cache()->flush();

        $html = $this->get(route('kontak'))->assertOk()->getContent();
        $isi = substr($html, strpos($html, 'Kunjungi atau Hubungi Kami'));

        $this->assertStringNotContainsString('Punya pertanyaan seputar stok', $isi);
        $this->assertStringContainsString('href="mailto:toko@jamumakyuli.test"', $isi);
        $this->assertLessThan(strpos($isi, '>toko@jamumakyuli.test<'), strpos($isi, '>0215550199<'));
    }

    public function test_language_switch_is_gone(): void
    {
        $this->get('/lang/en')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('googtrans')->assertDontSee('translate.google');
    }
}
