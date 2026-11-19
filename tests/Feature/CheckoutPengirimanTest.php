<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutPengirimanTest extends TestCase
{
    use RefreshDatabase;

    private const PROVINSI = 10;

    private const KOTA = 138;

    private const KECAMATAN = 1348;

    private const KELURAHAN = 17641;

    private function seedCart(int $weight = 2000): Product
    {
        $product = Product::factory()->create([
            'is_active' => true, 'stock' => 10, 'original_price' => 100000,
            'promo_price' => null, 'weight' => $weight,
        ]);

        app(CartService::class)->add($product->id, 1, null, '', []);

        return $product;
    }

    /**
     * Menelusuri rantai wilayah mock: JAWA TENGAH > KARANGANYAR > MOJOGEDANG
     * > MUNGGUR (17641).
     */
    private function pilihWilayahLengkap(): Testable
    {
        return Livewire::test('pages::storefront.checkout')
            ->set('provinceId', self::PROVINSI)
            ->set('cityId', self::KOTA)
            ->set('districtId', self::KECAMATAN)
            ->set('subDistrictId', self::KELURAHAN);
    }

    public function test_tingkat_di_bawah_provinsi_kosong_sebelum_provinsi_dipilih(): void
    {
        $this->seedCart();

        Livewire::test('pages::storefront.checkout')
            ->assertSet('provinceId', null)
            ->assertSet('cityId', null)
            ->assertSet('districtId', null)
            ->assertSet('subDistrictId', null)
            ->assertSet('destinationId', null);
    }

    public function test_memilih_kelurahan_menetapkan_tujuan_dan_label(): void
    {
        $this->seedCart();

        $this->pilihWilayahLengkap()
            ->assertSet('destinationId', self::KELURAHAN)
            ->assertSet('destinationLabel', 'MUNGGUR, MOJOGEDANG, KARANGANYAR, JAWA TENGAH, 57752')
            ->assertSee('Pilih Kurir');
    }

    // Mengganti provinsi harus mengosongkan seluruh tingkat di bawahnya beserta
    // tujuan, kalau tidak ongkir bisa terhitung untuk kelurahan yang sudah tidak
    // relevan dengan provinsi baru.
    public function test_mengganti_provinsi_mengosongkan_tingkat_di_bawahnya(): void
    {
        $this->seedCart();

        $this->pilihWilayahLengkap()
            ->set('provinceId', 11)
            ->assertSet('cityId', null)
            ->assertSet('districtId', null)
            ->assertSet('subDistrictId', null)
            ->assertSet('destinationId', null)
            ->assertSet('destinationLabel', null);
    }

    // Opsi placeholder di <select> bernilai string kosong (bukan disabled) agar
    // browser tidak otomatis menampilkan opsi pertama saat state masih null.
    // Livewire harus memetakan "" ke null dan tetap menjalankan reset berjenjang.
    public function test_memilih_placeholder_kosong_mengosongkan_provinsi_dan_turunannya(): void
    {
        $this->seedCart();

        $this->pilihWilayahLengkap()
            ->set('provinceId', '')
            ->assertSet('provinceId', null)
            ->assertSet('cityId', null)
            ->assertSet('subDistrictId', null)
            ->assertSet('destinationId', null);
    }

    public function test_mengganti_kota_hanya_mengosongkan_kecamatan_dan_kelurahan(): void
    {
        $this->seedCart();

        $this->pilihWilayahLengkap()
            ->set('cityId', 135)
            ->assertSet('provinceId', self::PROVINSI)
            ->assertSet('districtId', null)
            ->assertSet('subDistrictId', null)
            ->assertSet('destinationId', null);
    }

    // Permintaan klien: layanan yang sudah dipilih (mis. JNE REG) harus lepas dan
    // ongkirnya dihitung ulang begitu wilayah diganti. Sejak 2026-10-07 kurirnya
    // ikut dilepas: ketersediaan kurir berbeda per alamat, jadi pelanggan memilih
    // ulang setelah alamat baru lengkap.
    public function test_mengganti_wilayah_melepas_kurir_layanan_dan_mengosongkan_ongkir(): void
    {
        $this->seedCart();

        $komponen = $this->pilihWilayahLengkap()
            ->set('courier', 'jne')
            ->call('selectShipping', 0)
            ->assertSet('shipping_service', 'REG')
            ->assertSet('courier', 'jne');

        $this->assertGreaterThan(0, $komponen->get('shipping_cost'));

        $komponen
            ->set('districtId', 1327)
            ->assertSet('courier', '')
            ->assertSet('shipping_service', null)
            ->assertSet('shipping_service_label', null)
            ->assertSet('shipping_cost', 0)
            ->assertSet('destinationId', null);
    }

    // Sejak 2026-10-07 kurir ikut dilepas saat kelurahan berganti; setelah kurir
    // dipilih ulang, layanannya dihitung untuk tujuan yang baru.
    public function test_layanan_dihitung_ulang_untuk_kelurahan_baru_setelah_kurir_dipilih_ulang(): void
    {
        $this->seedCart();

        $komponen = $this->pilihWilayahLengkap()
            ->set('courier', 'jne')
            ->call('selectShipping', 0)
            ->set('subDistrictId', 17643)
            ->assertSet('destinationId', 17643)
            ->assertSet('courier', '')
            ->assertSet('shipping_service', null);

        $this->assertEmpty($komponen->instance()->shippingOptions);

        $komponen->set('courier', 'jne');

        $this->assertNotEmpty($komponen->instance()->shippingOptions);
    }

    public function test_kurir_kargo_tidak_ditawarkan(): void
    {
        $this->seedCart(weight: 15000);

        $this->pilihWilayahLengkap()
            ->assertDontSee('Sentral Cargo')
            ->assertDontSee('Kargo');
    }

    public function test_keempat_tingkat_wilayah_wajib_diisi(): void
    {
        $this->seedCart();

        Livewire::test('pages::storefront.checkout')
            ->set('customer_name', 'Budi')
            ->set('customer_email', 'budi@email.com')
            ->set('customer_phone', '0812')
            ->set('shipping_address', 'Jl. Melati 1')
            ->set('provinceId', self::PROVINSI)
            ->set('cityId', self::KOTA)
            ->call('placeOrder')
            ->assertHasErrors(['districtId' => 'required', 'subDistrictId' => 'required']);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_galat_wilayah_tampil_sekali_per_isian(): void
    {
        $this->seedCart();

        $html = Livewire::test('pages::storefront.checkout')
            ->call('placeOrder')
            ->html();

        foreach (['Silakan pilih provinsi.', 'Silakan pilih kabupaten/kota.', 'Silakan pilih kecamatan.', 'Silakan pilih kelurahan.'] as $pesan) {
            $this->assertSame(1, substr_count($html, $pesan), $pesan);
        }
    }

    public function test_isian_kontak_memakai_keyboard_dan_isi_otomatis_yang_tepat(): void
    {
        $this->seedCart();

        Livewire::test('pages::storefront.checkout')
            ->assertSeeHtml('type="tel"')
            ->assertSeeHtml('autocomplete="name"')
            ->assertSeeHtml('autocomplete="tel"')
            ->assertSeeHtml('autocomplete="email"')
            ->assertDontSeeHtml('kode-voucher');
    }

    public function test_wajib_memilih_layanan_pengiriman(): void
    {
        $this->seedCart();

        $this->pilihWilayahLengkap()
            ->set('customer_name', 'Budi')
            ->set('customer_email', 'budi@email.com')
            ->set('customer_phone', '0812')
            ->set('shipping_address', 'Jl. Melati 1')
            ->set('courier', 'jne')
            ->call('placeOrder')
            ->assertHasErrors(['shipping_service' => 'required']);
    }

    // Kolom wilayah pada orders selama ini selalu kosong karena pencarian bebas
    // hanya tahu ID kelurahan. Rantai berjenjang mengisi ketiganya.
    public function test_pesanan_menyimpan_provinsi_kota_dan_kelurahan(): void
    {
        $this->seedCart();

        $this->pilihWilayahLengkap()
            ->set('customer_name', 'Budi')
            ->set('customer_email', 'budi@email.com')
            ->set('customer_phone', '0812')
            ->set('shipping_address', 'Jl. Melati 1')
            ->set('courier', 'jne')
            ->call('selectShipping', 0)
            ->call('placeOrder')
            ->assertHasNoErrors();

        $order = Order::query()->firstOrFail();

        $this->assertSame(self::PROVINSI, $order->shipping_province_id);
        $this->assertSame(self::KOTA, $order->shipping_city_id);
        $this->assertSame(self::KELURAHAN, $order->shipping_district_id);
        $this->assertSame('MUNGGUR, MOJOGEDANG, KARANGANYAR, JAWA TENGAH, 57752', $order->shipping_destination_label);
    }
}
