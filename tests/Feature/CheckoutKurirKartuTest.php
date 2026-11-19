<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kurir dipilih lewat kartu radio berlogo, lalu layanan lewat kartu radio yang
 * memuat harga dan estimasi. Properti courier dan aksi selectShipping tetap
 * sama, jadi alur pemesanan di CheckoutPengirimanTest berlaku tanpa perubahan.
 */
class CheckoutKurirKartuTest extends TestCase
{
    use RefreshDatabase;

    private function checkoutDenganKeranjang(): Testable
    {
        $product = Product::factory()->create([
            'is_active' => true, 'stock' => 10, 'original_price' => 100000,
            'promo_price' => null, 'weight' => 2000,
        ]);

        app(CartService::class)->add($product->id);

        return Livewire::test('pages::storefront.checkout');
    }

    private function tujuanLengkap(Testable $checkout): Testable
    {
        return $checkout
            ->set('provinceId', 10)
            ->set('cityId', 138)
            ->set('districtId', 1348)
            ->set('subDistrictId', 17641);
    }

    public function test_kartu_kurir_tersembunyi_sampai_kelurahan_dipilih(): void
    {
        $petunjuk = 'Silakan lengkapi dulu alamat pengiriman.';

        $checkout = $this->checkoutDenganKeranjang()
            ->assertSee('Metode Pengiriman')
            ->assertSee($petunjuk)
            ->assertDontSeeHtml('name="kurir"');

        $checkout->set('provinceId', 10)->set('cityId', 138)->set('districtId', 1348)
            ->assertSee($petunjuk)
            ->assertDontSeeHtml('name="kurir"');

        $checkout->set('subDistrictId', 17641)
            ->assertDontSee($petunjuk)
            ->assertSeeHtml('name="kurir"');
    }

    public function test_mengganti_provinsi_menyembunyikan_lagi_kurir_dan_mereset_ongkir(): void
    {
        $this->tujuanLengkap($this->checkoutDenganKeranjang())
            ->set('courier', 'jne')
            ->call('selectShipping', 0)
            ->assertSet('shipping_cost', 19000)
            ->set('provinceId', 11)
            ->assertSet('courier', '')
            ->assertSet('shipping_service', null)
            ->assertSet('shipping_cost', 0)
            ->assertDontSeeHtml('name="kurir"')
            ->assertSee('Silakan lengkapi dulu alamat pengiriman.');
    }

    // "Rp 0" di baris ongkir terbaca seperti gratis ongkir.
    public function test_ringkasan_ongkir_menampilkan_ajakan_sebelum_layanan_dipilih(): void
    {
        $checkout = $this->tujuanLengkap($this->checkoutDenganKeranjang())
            ->assertSeeHtml('data-ringkasan-ongkir>'.__('Pilih kurir').'<');

        $checkout->set('courier', 'jne')
            ->assertSeeHtml('data-ringkasan-ongkir>'.__('Pilih layanan').'<');

        $checkout->call('selectShipping', 0)
            ->assertSeeHtml('data-ringkasan-ongkir>'.rupiah(19000).'<');
    }

    // Selama server menghitung ulang, tingkat di bawah yang berubah langsung
    // nonaktif agar nilai lama tidak bisa dipilih.
    public function test_dropdown_tingkat_bawah_nonaktif_selama_tingkat_atas_dimuat_ulang(): void
    {
        $this->checkoutDenganKeranjang()
            ->assertSeeHtml('wire:loading.attr="disabled" wire:target="provinceId"')
            ->assertSeeHtml('wire:loading.attr="disabled" wire:target="provinceId, cityId"')
            ->assertSeeHtml('wire:loading.attr="disabled" wire:target="provinceId, cityId, districtId"');
    }

    // Galat di bawah kartu layanan tertutup bar Buat Pesanan di ponsel. Pesan harus
    // berada sebelum pilihan yang perlu diisi, tempat scrollToError() berhenti.
    public function test_galat_layanan_tampil_sebelum_pilihan_layanan(): void
    {
        $html = $this->tujuanLengkap($this->checkoutDenganKeranjang())
            ->set('courier', 'jne')
            ->call('placeOrder')
            ->assertHasErrors('shipping_service')
            ->html();

        $galat = strpos($html, 'Silakan pilih layanan pengiriman.');
        $this->assertNotFalse($galat);
        $this->assertLessThan(strpos($html, 'wire:click="selectShipping(0)"'), $galat);
    }

    public function test_galat_kurir_tampil_sebelum_pilihan_kurir(): void
    {
        $html = $this->tujuanLengkap($this->checkoutDenganKeranjang())
            ->call('placeOrder')
            ->assertHasErrors('courier')
            ->html();

        $galat = strpos($html, 'Silakan pilih kurir pengiriman.');
        $this->assertNotFalse($galat);
        $this->assertLessThan(strpos($html, 'name="kurir"'), $galat);
    }

    public function test_galat_hilang_begitu_kurir_dan_layanan_dipilih(): void
    {
        $this->tujuanLengkap($this->checkoutDenganKeranjang())
            ->call('placeOrder')
            ->assertHasErrors(['courier', 'shipping_service'])
            ->set('courier', 'jne')
            ->assertHasNoErrors('courier')
            ->assertDontSee('Silakan pilih kurir pengiriman.')
            ->assertSee('Silakan pilih layanan pengiriman.')
            ->call('selectShipping', 0)
            ->assertHasNoErrors('shipping_service')
            ->assertDontSee('Silakan pilih layanan pengiriman.');
    }

    public function test_kurir_tampil_sebagai_kartu_radio_berlogo(): void
    {
        $checkout = $this->tujuanLengkap($this->checkoutDenganKeranjang());

        foreach (['jne' => 'jne.svg', 'jnt' => 'jnt.svg'] as $kode => $logo) {
            $checkout->assertSeeHtml('wire:model.live="courier" value="'.$kode.'"')
                ->assertSeeHtml('assets/images/ekspedisi/'.$logo);
        }

        $checkout->assertDontSeeHtml(__('Pilih kurir…'))
            ->assertDontSeeHtml('value="sicepat"');
    }

    public function test_logo_kurir_tersedia_sebagai_aset_publik(): void
    {
        foreach (['jne', 'jnt'] as $kode) {
            $this->assertFileExists(public_path("assets/images/ekspedisi/{$kode}.svg"));
        }
    }

    public function test_layanan_tampil_sebagai_kartu_dengan_harga_dan_estimasi_dalam_hari(): void
    {
        $this->tujuanLengkap($this->checkoutDenganKeranjang())
            ->set('courier', 'jne')
            ->assertSee('Pilih Layanan')
            ->assertSeeHtml('wire:click="selectShipping(0)"')
            ->assertSee('REG')
            ->assertSee(rupiah(19000))
            ->assertSee('Estimasi 2-3 hari')
            ->assertDontSee('2-3 day');
    }

    public function test_layanan_terpilih_ditandai_setelah_dipilih(): void
    {
        $this->tujuanLengkap($this->checkoutDenganKeranjang())
            ->set('courier', 'jne')
            ->call('selectShipping', 0)
            ->assertSet('shipping_service', 'REG')
            ->assertSet('shipping_cost', 19000)
            ->assertSeeHtml('value="REG" checked');
    }
}
