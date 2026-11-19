<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Exceptions\BusinessRuleException;
use App\Jobs\SendBrevoEmail;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\Payments\Midtrans\MidtransPaymentAttemptService;
use App\Services\Payments\Midtrans\MidtransWebhookService;
use App\Services\SettingService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FinalReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => 'test-server-key',
            'services.midtrans.client_key' => 'test-client-key',
            'services.midtrans.is_production' => false,
        ]);
        Queue::fake();
    }

    private function pendingOrderWithStock(int $stock = 5, int $quantity = 2): array
    {
        $product = Product::factory()->create(['stock' => $stock, 'is_active' => true]);
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);
        $order->items()->create(['product_id' => $product->id, 'variant_id' => null, 'price' => 10000, 'quantity' => $quantity, 'total' => 10000 * $quantity]);

        return [$order, $product];
    }

    private function openAttempt(Order $order): PaymentAttempt
    {
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'midtrans_order_id' => $order->order_number.'-A1',
            'payment_method' => null,
            'status' => PaymentAttemptStatus::PENDING,
            'gross_amount' => $order->grand_total,
        ]);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        return $attempt;
    }

    public function test_unpaid_cancel_with_stale_model_never_overrides_paid(): void
    {
        Http::fake();
        [$order, $product] = $this->pendingOrderWithStock(5, 2);
        $stale = Order::query()->findOrFail($order->id);

        app(OrderService::class)->markAsPaid(Order::query()->findOrFail($order->id));
        app(OrderService::class)->cancel($stale, 'Sistem (kedaluwarsa)', onlyUnpaid: true);

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_cancelling_twice_with_stale_models_returns_stock_once(): void
    {
        Http::fake();
        [$order, $product] = $this->pendingOrderWithStock(5, 2);
        $first = Order::query()->findOrFail($order->id);
        $second = Order::query()->findOrFail($order->id);

        app(OrderService::class)->cancel($first);
        app(OrderService::class)->cancel($second);

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_cancel_closes_open_attempt_at_midtrans(): void
    {
        Http::fake(['*/v2/*/cancel' => Http::response(['status_code' => '200'], 200)]);
        [$order] = $this->pendingOrderWithStock();
        $attempt = $this->openAttempt($order);

        app(OrderService::class)->cancel($order);

        $this->assertSame(PaymentAttemptStatus::SUPERSEDED, $attempt->fresh()->status);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/'.$attempt->midtrans_order_id.'/cancel'));
    }

    public function test_manual_mark_paid_closes_open_attempt_at_midtrans(): void
    {
        Http::fake(['*/v2/*/cancel' => Http::response(['status_code' => '200'], 200)]);
        [$order] = $this->pendingOrderWithStock();
        $attempt = $this->openAttempt($order);

        app(OrderService::class)->markAsPaid($order, 'Admin');

        $this->assertSame(PaymentAttemptStatus::SUPERSEDED, $attempt->fresh()->status);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/'.$attempt->midtrans_order_id.'/cancel'));
    }

    public function test_retry_payment_uses_remaining_time_not_a_fresh_day(): void
    {
        SettingService::set('expiry_order', '1440');
        Http::fake(['*/snap/v1/transactions' => Http::response(['token' => 't', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/t'], 201)]);
        $order = Order::factory()->create(['status' => OrderStatus::PENDING, 'created_at' => now()->subHours(20)]);

        app(MidtransPaymentAttemptService::class)->createOrReuseActiveAttempt($order);

        Http::assertSent(function ($request) {
            $duration = $request['expiry']['duration'] ?? null;

            return $duration !== null && $duration >= 239 && $duration <= 241;
        });
    }

    public function test_retry_payment_after_deadline_is_refused(): void
    {
        SettingService::set('expiry_order', '1440');
        Http::fake();
        $order = Order::factory()->create(['status' => OrderStatus::PENDING, 'created_at' => now()->subHours(25)]);

        $this->expectException(BusinessRuleException::class);

        app(MidtransPaymentAttemptService::class)->createOrReuseActiveAttempt($order);
    }

    public function test_rajaongkir_connection_failure_becomes_business_error(): void
    {
        config(['services.rajaongkir.mock' => false, 'services.rajaongkir.key' => 'kunci', 'services.rajaongkir.base_url' => 'https://ro.test']);
        SettingService::set('origin_district_id', '17641');
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(BusinessRuleException::class);

        app(ShippingService::class)->tariffs(17641, 1000);
    }

    public function test_rajaongkir_region_connection_failure_becomes_business_error(): void
    {
        config(['services.rajaongkir.mock' => false, 'services.rajaongkir.key' => 'kunci', 'services.rajaongkir.base_url' => 'https://ro.test']);
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(BusinessRuleException::class);

        app(ShippingService::class)->provinces();
    }

    public function test_cart_quantity_above_stock_can_still_be_decreased(): void
    {
        $product = Product::factory()->create(['stock' => 3, 'is_active' => true]);
        $cart = app(CartService::class);
        $cart->add($product->id, 3);
        $product->update(['stock' => 1]);
        $key = $cart->items()->first()['key'];

        $cart->update($key, 2);

        $this->assertSame(2, $cart->items()->first()['quantity']);
    }

    public function test_admin_can_edit_origin_district_maps_and_hours(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test('pages::admin.settings')
            ->set('values.site_name', 'Jamu Mak Yuli')
            ->set('values.origin_district_id', '12345')
            ->set('values.site_maps_url', 'https://maps.app.goo.gl/contoh')
            ->set('values.site_hours', 'Senin sampai Sabtu, 08.00 sampai 17.00 WIB')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('12345', setting('origin_district_id'));
        $this->assertSame('https://maps.app.goo.gl/contoh', setting('site_maps_url'));
    }

    public function test_wa_link_percent_encodes_special_characters(): void
    {
        SettingService::set('site_whatsapp', '087879865789');

        $url = wa_href("J&T #1\nSemangat ❤️");

        $this->assertStringContainsString('%26', $url);
        $this->assertStringContainsString('%23', $url);
        $this->assertStringContainsString('%0A', $url);
        $this->assertStringNotContainsString("\n", $url);
        $this->assertStringNotContainsString('&T', $url);
    }

    public function test_settlement_for_cancelled_order_keeps_stock_and_status(): void
    {
        Http::fake(['*/v2/*/cancel' => Http::response(['status_code' => '200'], 200)]);
        [$order, $product] = $this->pendingOrderWithStock(5, 2);
        $attempt = $this->openAttempt($order);
        $order->update(['status' => OrderStatus::CANCELLED]);

        $payload = [
            'order_id' => $attempt->midtrans_order_id,
            'transaction_id' => (string) Str::uuid(),
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => number_format((int) $order->grand_total, 2, '.', ''),
            'fraud_status' => 'accept',
        ];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test-server-key');

        app(MidtransWebhookService::class)->handle($payload);

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock);
        Queue::assertPushed(SendBrevoEmail::class, fn ($job): bool => $job->event === 'payment_alert_cancelled');
    }

    public function test_destructive_and_failed_states_stay_red(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pending = Order::factory()->create(['status' => OrderStatus::PENDING]);
        Order::factory()->create(['status' => OrderStatus::CANCELLED]);

        $this->actingAs($admin)->get(route('admin.orders.show', $pending))
            ->assertOk()
            ->assertSee('!text-rose-700', false)
            ->assertDontSee('border-brand-green/30 !text-brand-green', false);

        $this->actingAs($admin)->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('rounded-full bg-rose-500', false);

        $this->assertStringContainsString('.lbl .req { color: var(--color-sale); }', file_get_contents(resource_path('css/app.css')));
    }

    public function test_product_form_keeps_stock_sold_while_form_was_open(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create(['stock' => 20]);

        $form = Livewire::actingAs($admin)->test('pages::admin.products.form', ['product' => $product]);
        $product->update(['stock' => 18]);
        $form->set('name', 'Jamu Kunyit Asam Baru')->call('save')->assertHasNoErrors();

        $this->assertSame(18, $product->fresh()->stock);
        $this->assertSame('Jamu Kunyit Asam Baru', $product->fresh()->name);
    }

    public function test_product_form_saves_stock_the_admin_changed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create(['stock' => 20]);

        Livewire::actingAs($admin)->test('pages::admin.products.form', ['product' => $product])
            ->set('stock', 30)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(30, $product->fresh()->stock);
    }

    public function test_product_form_keeps_variant_stock_sold_while_form_was_open(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create();
        $variant = Variant::factory()->create(['product_id' => $product->id, 'stock' => 10]);

        $form = Livewire::actingAs($admin)->test('pages::admin.products.form', ['product' => $product]);
        $variant->update(['stock' => 7]);
        $form->set('name', 'Jamu Bervarian Baru')->call('save')->assertHasNoErrors();

        $this->assertSame(7, $variant->fresh()->stock);
    }

    public function test_reconcile_keeps_going_when_one_status_call_cannot_connect(): void
    {
        $first = Order::factory()->create(['status' => OrderStatus::PENDING]);
        $second = Order::factory()->create(['status' => OrderStatus::PENDING]);
        $broken = $this->openAttempt($first);
        $healthy = $this->openAttempt($second);
        PaymentAttempt::query()->whereKey([$broken->id, $healthy->id])->update(['activated_at' => now()->subHour()]);

        Http::fake(function ($request) use ($broken, $healthy) {
            if (str_contains($request->url(), $broken->midtrans_order_id)) {
                throw new ConnectionException('timeout');
            }

            if (str_contains($request->url(), $healthy->midtrans_order_id.'/status')) {
                return Http::response([
                    'order_id' => $healthy->midtrans_order_id,
                    'transaction_status' => 'settlement',
                    'status_code' => '200',
                    'gross_amount' => number_format((int) $healthy->order->grand_total, 2, '.', ''),
                    'fraud_status' => 'accept',
                ], 200);
            }

            return Http::response(['status_code' => '200'], 200);
        });

        $this->artisan('payments:midtrans:reconcile')->assertSuccessful();

        $this->assertSame(OrderStatus::PAID, $second->fresh()->status);
    }

    public function test_snap_connection_failure_becomes_business_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        $this->expectException(BusinessRuleException::class);

        app(MidtransPaymentAttemptService::class)->createOrReuseActiveAttempt($order);
    }

    public function test_no_old_brand_red_left_in_hover_and_active_states(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringNotContainsString('var(--color-red-50)', $css);
        $this->assertStringNotContainsString('var(--color-red-200)', $css);
        $this->assertStringContainsString('.dark .bnav.is-active { color: var(--color-green-400); }', $css);
        $this->assertStringContainsString('.dark .cat-card:hover', $css);
    }

    public function test_form_errors_and_submit_buttons_follow_house_rules(): void
    {
        $this->assertStringContainsString('[data-flux-error] { color: var(--color-sale); }', file_get_contents(resource_path('css/app.css')));

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('Memproses...');
    }
}
