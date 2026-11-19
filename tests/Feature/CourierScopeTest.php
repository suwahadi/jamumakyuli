<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class CourierScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_jne_and_jnt_are_offered(): void
    {
        $codes = collect(app(ShippingService::class)->tariffs(17641, 1000))
            ->pluck('code')->unique()->values()->all();

        $this->assertSame(['jne', 'jnt'], $codes);
    }

    public function test_checkout_disables_pay_when_no_tariff(): void
    {
        $product = Product::factory()->create(['stock' => 5, 'is_active' => true, 'weight' => 500]);
        app(CartService::class)->add($product->id, 1);
        $shipping = Mockery::mock(ShippingService::class.'[tariffs]', []);
        $shipping->shouldReceive('tariffs')->andReturn([]);
        $this->app->instance(ShippingService::class, $shipping);

        Livewire::test('pages::storefront.checkout')
            ->set('customer_name', 'Siti')->set('customer_email', 'siti@test.local')->set('customer_phone', '081234567890')
            ->set('shipping_address', 'Jl. Melati 1')
            ->set('provinceId', 10)->set('cityId', 138)->set('districtId', 1348)->set('subDistrictId', 17641)
            ->assertSee('Ongkir belum tersedia untuk alamat ini. Hubungi kami via WhatsApp.')
            ->assertSet('payBlocked', true)
            ->call('placeOrder')
            ->assertHasErrors();

        $this->assertSame(0, Order::query()->count());
    }
}
