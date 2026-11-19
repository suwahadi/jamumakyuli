<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShippingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.rajaongkir.base_url', 'https://api.test/v1');
        config()->set('services.rajaongkir.key', 'dummy-key');
        config()->set('services.rajaongkir.mock', false);

    }

    private function fakeRegion(string $path, array $rows): void
    {
        Http::fake([
            'api.test/v1/destination/'.$path => Http::response(['meta' => ['code' => 200], 'data' => $rows], 200),
        ]);
    }

    public function test_daftar_provinsi_dipetakan(): void
    {
        $this->fakeRegion('province', [['id' => 10, 'name' => 'DKI JAKARTA'], ['id' => 11, 'name' => 'JAWA TIMUR']]);

        $hasil = (new ShippingService)->provinces();

        $this->assertSame([['id' => 10, 'name' => 'DKI JAKARTA'], ['id' => 11, 'name' => 'JAWA TIMUR']], $hasil);
    }

    public function test_kota_kecamatan_kelurahan_memakai_id_induk(): void
    {
        Http::fake([
            'api.test/v1/destination/city/10' => Http::response(['meta' => ['code' => 200], 'data' => [['id' => 138, 'name' => 'JAKARTA UTARA']]], 200),
            'api.test/v1/destination/district/138' => Http::response(['meta' => ['code' => 200], 'data' => [['id' => 1348, 'name' => 'MOJOGEDANG']]], 200),
            'api.test/v1/destination/sub-district/1348' => Http::response(['meta' => ['code' => 200], 'data' => [['id' => 17641, 'name' => 'MUNGGUR', 'zip_code' => '14240']]], 200),
        ]);

        $service = new ShippingService;

        $this->assertSame([['id' => 138, 'name' => 'JAKARTA UTARA']], $service->cities(10));
        $this->assertSame([['id' => 1348, 'name' => 'MOJOGEDANG']], $service->districts(138));
        $this->assertSame([['id' => 17641, 'name' => 'MUNGGUR', 'zip' => '14240']], $service->subDistricts(1348));
    }

    // Kuota API Komerce terbatas; daftar wilayah nyaris tidak pernah berubah,
    // jadi panggilan kedua untuk induk yang sama harus dilayani cache.
    public function test_daftar_wilayah_di_cache(): void
    {
        $this->fakeRegion('city/10', [['id' => 138, 'name' => 'JAKARTA UTARA']]);

        $service = new ShippingService;
        $service->cities(10);
        $service->cities(10);

        Http::assertSentCount(1);
    }

    public function test_induk_berbeda_tidak_berbagi_cache(): void
    {
        Http::fake([
            'api.test/v1/destination/city/10' => Http::response(['meta' => ['code' => 200], 'data' => [['id' => 138, 'name' => 'JAKARTA UTARA']]], 200),
            'api.test/v1/destination/city/11' => Http::response(['meta' => ['code' => 200], 'data' => [['id' => 444, 'name' => 'SURABAYA']]], 200),
        ]);

        $service = new ShippingService;

        $this->assertSame('JAKARTA UTARA', $service->cities(10)[0]['name']);
        $this->assertSame('SURABAYA', $service->cities(11)[0]['name']);
        Http::assertSentCount(2);
    }

    public function test_404_wilayah_dikembalikan_sebagai_kosong(): void
    {
        Http::fake([
            'api.test/v1/destination/*' => Http::response(['meta' => ['code' => 404, 'status' => 'error'], 'data' => null], 404),
        ]);

        $this->assertSame([], (new ShippingService)->districts(999999));
    }

    public function test_api_wilayah_gagal_melempar_exception(): void
    {
        Http::fake([
            'api.test/v1/destination/*' => Http::response('', 500),
        ]);

        $this->expectException(BusinessRuleException::class);

        (new ShippingService)->provinces();
    }

    // Mode mock harus menyediakan rantai lengkap yang saling nyambung, karena
    // tes checkout menelusurinya dari provinsi sampai kelurahan.
    public function test_mode_mock_menyediakan_rantai_wilayah_tanpa_memanggil_api(): void
    {
        config()->set('services.rajaongkir.mock', true);
        Http::fake();

        $service = new ShippingService;
        $provinsi = $service->provinces();
        $kota = $service->cities($provinsi[0]['id']);
        $kecamatan = $service->districts($kota[0]['id']);
        $kelurahan = $service->subDistricts($kecamatan[0]['id']);

        $this->assertNotEmpty($provinsi);
        $this->assertNotEmpty($kota);
        $this->assertNotEmpty($kecamatan);
        $this->assertNotEmpty($kelurahan);
        $this->assertArrayHasKey('zip', $kelurahan[0]);
        Http::assertNothingSent();
    }
}
