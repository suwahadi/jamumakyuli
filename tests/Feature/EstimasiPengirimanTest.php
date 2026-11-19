<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EstimasiPengirimanTest extends TestCase
{
    use RefreshDatabase;

    public static function estimasi(): array
    {
        return [
            'satuan inggris' => ['1 day', '1'],
            'rentang inggris jamak' => ['2-3 days', '2-3'],
            'sudah indonesia' => ['2-3 hari', '2-3'],
            'angka saja' => ['2-3', '2-3'],
            'huruf besar' => ['1-2 DAY', '1-2'],
            'nol' => ['0 day', ''],
            'strip' => ['-', ''],
            'kosong' => ['', ''],
            'null' => [null, ''],
        ];
    }

    #[DataProvider('estimasi')]
    public function test_rentang_etd_dinormalkan(?string $masukan, string $harapan): void
    {
        $this->assertSame($harapan, rentang_etd($masukan));
    }

    public function test_detail_layanan_pesanan_memakai_estimasi_berbahasa_indonesia(): void
    {
        $order = Order::factory()->make(['shipping_courier_name' => 'JNE', 'shipping_service_label' => 'Layanan Reguler', 'shipping_etd' => '1 day']);

        $this->assertSame('JNE · Layanan Reguler · Estimasi tiba 1 hari', $order->shippingServiceDetail());
    }

    public function test_detail_layanan_tanpa_estimasi_tidak_menulis_nol_hari(): void
    {
        $order = Order::factory()->make(['shipping_courier_name' => 'JNE', 'shipping_service_label' => 'Super Speed', 'shipping_etd' => '0 day']);

        $this->assertSame('JNE · Super Speed', $order->shippingServiceDetail());
    }

    private function fakeTarif(): void
    {
        config()->set('services.rajaongkir.base_url', 'https://ro.test');
        config()->set('services.rajaongkir.key', 'ro-key');
        config()->set('services.rajaongkir.mock', false);
        Cache::forever('setting.origin_district_id', '17641');

        Http::fake(['ro.test/calculate/domestic-cost' => fn (Request $r) => Http::response(['data' => $r['courier'] === 'jne' ? [
            ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 12000, 'etd' => '1 day'],
            ['name' => 'JNE', 'code' => 'jne', 'service' => 'JTR', 'description' => 'JNE Trucking', 'cost' => 45000, 'etd' => '3 day'],
            ['name' => 'JNE', 'code' => 'jne', 'service' => 'JTR<130', 'description' => 'JNE Trucking', 'cost' => 450000, 'etd' => '3 day'],
        ] : []], 200)]);
    }

    public function test_layanan_kargo_disembunyikan_untuk_paket_di_bawah_10_kg(): void
    {
        $this->fakeTarif();

        $layanan = array_column(app(ShippingService::class)->tariffs(17673, 1600), 'service');

        $this->assertSame(['REG'], $layanan);
    }

    public function test_layanan_kargo_ditawarkan_untuk_paket_10_kg_ke_atas(): void
    {
        $this->fakeTarif();

        $layanan = array_column(app(ShippingService::class)->tariffs(17673, 12000), 'service');

        $this->assertEqualsCanonicalizing(['REG', 'JTR', 'JTR<130'], $layanan);
    }
}
