<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutMidtransTest extends TestCase
{
    use RefreshDatabase;

    private function seedCart(): Product
    {
        config([
            'services.midtrans.server_key' => 'test-server-key',
            'services.midtrans.client_key' => 'test-client-key',
            'services.midtrans.is_production' => false,
        ]);

        $product = Product::factory()->create(['original_price' => 100000, 'promo_price' => null, 'stock' => 5, 'is_active' => true]);
        app(CartService::class)->add($product->id, 1);

        return $product;
    }

    private function isiCheckout(): Testable
    {
        return Livewire::test('pages::storefront.checkout')
            ->set('customer_name', 'Siti')->set('customer_email', 'siti@test.local')->set('customer_phone', '081234567890')
            ->set('shipping_address', 'Jl. Melati 1')
            ->set('provinceId', 10)->set('cityId', 138)->set('districtId', 1348)->set('subDistrictId', 17641)
            ->set('courier', 'jne')
            ->call('selectShipping', 0);
    }

    public function test_checkout_membuat_attempt_pending_dengan_snap_token(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/snap/v1/transactions')) {
                return Http::response([
                    'token' => 'snap-token-abc',
                    'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/abc',
                ], 201);
            }

            return Http::response([], 404);
        });
        $this->seedCart();

        $this->isiCheckout()->call('placeOrder');

        $attempt = PaymentAttempt::query()->first();
        $this->assertNotNull($attempt);
        $this->assertSame('snap-token-abc', $attempt->snap_token);
        $this->assertSame('https://app.sandbox.midtrans.com/snap/v4/redirection/abc', $attempt->redirect_url);
        $this->assertSame(PaymentAttemptStatus::PENDING, $attempt->status);
        $this->assertNull($attempt->payment_method, 'Checkout memaku satu metode; Snap tidak akan menampilkan seluruh pilihan.');
    }

    public function test_checkout_tidak_memaku_metode_pada_payload_snap(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/snap/v1/transactions')) {
                return Http::response([
                    'token' => 'snap-token-abc',
                    'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/abc',
                ], 201);
            }

            return Http::response([], 404);
        });
        $this->seedCart();

        $this->isiCheckout()->call('placeOrder');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/snap/v1/transactions')) {
                return false;
            }

            $this->assertArrayNotHasKey('enabled_payments', $request->data());

            return true;
        });
    }

    public function test_checkout_redirects_to_snap_with_order_expiry(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/snap/v1/transactions')) {
                return Http::response([
                    'token' => 'snap-token-abc',
                    'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/abc',
                ], 201);
            }

            return Http::response([], 404);
        });

        $this->seedCart();

        $this->isiCheckout()->call('placeOrder')
            ->assertRedirect('https://app.sandbox.midtrans.com/snap/v4/redirection/abc');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/snap/v1/transactions')
            && $request['expiry']['duration'] === order_expiry_minutes());

        $order = Order::query()->sole();
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame($order->subtotal + $order->shipping_cost, $order->grand_total);
    }

    public function test_failed_submit_dispatches_scroll_to_error_event(): void
    {
        $this->seedCart();

        Livewire::test('pages::storefront.checkout')
            ->call('placeOrder')
            ->assertHasErrors(['customer_name', 'customer_email'])
            ->assertDispatched('checkout-invalid');
    }
}
