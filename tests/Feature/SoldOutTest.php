<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SoldOutTest extends TestCase
{
    use RefreshDatabase;

    private function payloadFor(Product $product, int $quantity): array
    {
        return [
            'customer' => ['name' => 'Siti', 'email' => 'siti@test.local', 'phone' => '081234567890'],
            'shipping' => ['destination_id' => 17641, 'address' => 'Jl. Melati 1', 'courier' => 'jne'],
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => $quantity]],
        ];
    }

    public function test_sold_out_product_shows_habis_and_cannot_be_added(): void
    {
        $product = Product::factory()->create(['stock' => 0, 'is_active' => true]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Habis')
            ->assertDontSee('Pre-Order');

        $this->expectException(BusinessRuleException::class);

        app(CartService::class)->add($product->id, 1);
    }

    public function test_sold_out_badge_on_catalog_card(): void
    {
        Product::factory()->create(['name' => 'Jamu Temulawak', 'stock' => 0, 'is_active' => true]);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('Jamu Temulawak')
            ->assertSee('Habis')
            ->assertDontSee('Pre-Order');
    }

    public function test_cart_rejects_quantity_above_stock(): void
    {
        $product = Product::factory()->create(['name' => 'Jamu Temulawak', 'stock' => 2, 'is_active' => true]);

        try {
            app(CartService::class)->add($product->id, 3);
            $this->fail('harus ditolak');
        } catch (BusinessRuleException $e) {
            $this->assertSame('Stok Jamu Temulawak tidak mencukupi. Sisa 2.', $e->getMessage());
        }
    }

    public function test_checkout_rejects_quantity_above_stock_and_never_goes_negative(): void
    {
        $this->fakeShippingQuote();
        $product = Product::factory()->create(['name' => 'Jamu Temulawak', 'stock' => 2, 'is_active' => true]);

        try {
            app(OrderService::class)->checkout($this->payloadFor($product, 3), 'idem-1');
            $this->fail('harus ditolak');
        } catch (BusinessRuleException $e) {
            $this->assertSame('Stok Jamu Temulawak tidak mencukupi. Sisa 2.', $e->getMessage());
        }

        $this->assertSame(2, $product->fresh()->stock);
        $this->assertSame(0, Order::query()->count());
    }

    public function test_cart_with_item_that_ran_out_blocks_checkout_inline(): void
    {
        $product = Product::factory()->create(['name' => 'Jamu Temulawak', 'stock' => 1, 'is_active' => true, 'weight' => 500]);
        app(CartService::class)->add($product->id, 1);
        $product->update(['stock' => 0]);

        $this->get(route('cart.index'))->assertOk()->assertSee('Habis');

        Livewire::test('pages::storefront.checkout')
            ->set('customer_name', 'Siti')->set('customer_email', 'siti@test.local')->set('customer_phone', '081234567890')
            ->set('shipping_address', 'Jl. Melati 1')
            ->set('provinceId', 10)->set('cityId', 138)->set('districtId', 1348)->set('subDistrictId', 17641)
            ->set('courier', 'jne')
            ->call('selectShipping', 0)
            ->call('placeOrder')
            ->assertHasErrors('cart')
            ->assertSee('Stok Jamu Temulawak tidak mencukupi. Sisa 0.');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, $product->fresh()->stock);
    }
}
