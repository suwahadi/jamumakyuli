<?php

namespace Tests;

use App\Services\ShippingService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Kunci ongkir ke nilai tetap agar test yang tidak menguji pengiriman
     * tidak terpengaruh berat produk yang diacak factory.
     */
    protected function fakeShippingQuote(int $cost = 20000): void
    {
        $this->partialMock(ShippingService::class, function ($mock) use ($cost): void {
            $mock->shouldReceive('quote')->andReturn([
                'courier' => 'jne',
                'courier_name' => 'JNE',
                'service' => 'JNEFlat',
                'service_label' => 'JNE',
                'etd' => '1-2 day',
                'cost' => $cost,
            ]);
        });
    }
}
