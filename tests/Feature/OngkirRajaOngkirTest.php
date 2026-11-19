<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pengiriman dilakukan manual oleh pemilik toko, jadi ongkir checkout hanya
 * bersumber dari RajaOngkir untuk kurir yang diaktifkan toko.
 */
class OngkirRajaOngkirTest extends TestCase
{
    use RefreshDatabase;

    private const TARIF = ['jne' => 20000, 'jnt' => 18000];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.rajaongkir.base_url', 'https://ro.test');
        config()->set('services.rajaongkir.key', 'ro-key');
        config()->set('services.rajaongkir.mock', false);

        Cache::forever('setting.origin_district_id', '17641');

        Http::fake([
            'ro.test/calculate/domestic-cost' => fn (Request $r) => Http::response(['data' => [[
                'name' => 'Nama Kurir',
                'code' => $r['courier'],
                'service' => 'REG',
                'description' => 'Layanan Reguler',
                'cost' => self::TARIF[$r['courier']],
                'etd' => '2-3 day',
            ]]], 200),
        ]);
    }

    public function test_tarif_checkout_diambil_dari_rajaongkir_termurah_dulu(): void
    {
        $tarif = app(ShippingService::class)->tariffs(17673, 1500);

        $this->assertSame(['jnt', 'jne'], array_column($tarif, 'code'));
        $this->assertSame([18000, 20000], array_column($tarif, 'cost'));
        $this->assertSame('Layanan Reguler', $tarif[0]['description']);

        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://ro.test/calculate/domestic-cost'
            && $r->hasHeader('key', 'ro-key')
            && $r['origin'] === '17641'
            && (int) $r['destination'] === 17673
            && (int) $r['weight'] === 1500);
    }

    public function test_quote_mengunci_harga_dari_tarif_rajaongkir(): void
    {
        $quote = app(ShippingService::class)->quote(17673, 1500, 'jne', 'REG');

        $this->assertSame(20000, $quote['cost']);
        $this->assertSame('jne', $quote['courier']);
        $this->assertSame('REG', $quote['service']);
        $this->assertSame('2-3 day', $quote['etd']);
    }

    public function test_quote_menolak_layanan_yang_tidak_ditawarkan(): void
    {
        $this->expectException(BusinessRuleException::class);

        app(ShippingService::class)->quote(17673, 1500, 'jne', 'YES');
    }

    public function test_quote_menolak_kurir_di_luar_daftar_aktif(): void
    {
        $this->expectException(BusinessRuleException::class);

        app(ShippingService::class)->quote(17673, 1500, 'tiki', 'REG');
    }
}
