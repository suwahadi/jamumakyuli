<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use App\Services\OrderService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeShippingQuote();
        $this->service = app(OrderService::class);
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'is_active' => true,
            'stock' => 10,
            'original_price' => 100000,
            'promo_price' => null,
        ], $attributes));
    }

    private function payload(Product $product, int $qty = 2): array
    {
        return [
            'customer' => ['name' => 'Budi', 'email' => 'budi@email.com', 'phone' => '0812'],
            'shipping' => [
                'destination_id' => 17473,
                'destination_label' => 'JATINEGARA, CAKUNG, JAKARTA TIMUR',
                'address' => 'Jl. Mesin 1',
                'courier' => 'jne',
                'cost' => 20000,
            ],
            'items' => [['product_id' => $product->id, 'quantity' => $qty]],
        ];
    }

    public function test_checkout_membuat_pesanan_dan_memotong_stok(): void
    {
        $product = $this->product(['stock' => 10, 'original_price' => 100000]);

        $order = $this->service->checkout($this->payload($product, 2), 'key-1');

        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame(200000, $order->subtotal);
        $this->assertSame(220000, $order->grand_total);
        $this->assertSame(17473, $order->shipping_district_id);
        $this->assertSame('JATINEGARA, CAKUNG, JAKARTA TIMUR', $order->shipping_destination_label);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertCount(1, $order->items);
    }

    public function test_idempotency_mencegah_pesanan_ganda(): void
    {
        $product = $this->product(['stock' => 10]);
        $payload = $this->payload($product, 2);

        $first = $this->service->checkout($payload, 'same-key');
        $second = $this->service->checkout($payload, 'same-key');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::count());
        $this->assertSame(8, $product->fresh()->stock); // stok hanya terpotong sekali
    }

    public function test_pembatalan_mengembalikan_stok(): void
    {
        $product = $this->product(['stock' => 10]);

        $order = $this->service->checkout($this->payload($product, 3), 'key-cancel');
        $this->assertSame(7, $product->fresh()->stock);

        $this->service->cancel($order);

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_transisi_status_lunas_lalu_dikirim(): void
    {
        $product = $this->product(['stock' => 10]);
        $order = $this->service->checkout($this->payload($product), 'key-status');

        $this->service->markAsPaid($order);
        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);

        $this->service->markAsShipped($order);
        $this->assertSame(OrderStatus::SHIPPED, $order->fresh()->status);
    }

    public function test_tidak_bisa_kirim_pesanan_belum_lunas(): void
    {
        $product = $this->product(['stock' => 10]);
        $order = $this->service->checkout($this->payload($product), 'key-ship-fail');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('lunas');

        $this->service->markAsShipped($order);
    }

    public function test_tidak_bisa_batalkan_pesanan_terkirim(): void
    {
        $product = $this->product(['stock' => 10]);
        $order = $this->service->checkout($this->payload($product), 'key-cancel-fail');
        $this->service->markAsPaid($order);
        $this->service->markAsShipped($order);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('dikirim');

        $this->service->cancel($order);
    }

    public function test_checkout_produk_bervarian_memotong_stok_varian(): void
    {
        // Harga/stok produk berbeda dari varian agar terbukti varian yang dipakai.
        $product = $this->product(['stock' => 0, 'original_price' => 999999]);
        $variant = Variant::factory()->create([
            'product_id' => $product->id,
            'price' => 100000,
            'promo_price' => null,
            'stock' => 10,
            'weight' => 500,
        ]);

        $payload = $this->payload($product, 3);
        $payload['items'][0]['variant_id'] = $variant->id;

        $order = $this->service->checkout($payload, 'key-variant');

        $this->assertSame(7, $variant->fresh()->stock);      // stok varian terpotong
        $this->assertSame(0, $product->fresh()->stock);      // stok produk tak tersentuh
        $this->assertSame(100000, $order->items->first()->price); // snapshot harga varian
        $this->assertSame($variant->id, $order->items->first()->variant_id);
    }

    public function test_produk_bervarian_tanpa_varian_ditolak(): void
    {
        $product = $this->product(['stock' => 0]);
        Variant::factory()->create(['product_id' => $product->id, 'stock' => 10]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('pilih varian');

        // Payload tanpa variant_id untuk produk bervarian.
        $this->service->checkout($this->payload($product, 1), 'key-novariant');
    }

    public function test_cancel_mengembalikan_stok_varian(): void
    {
        $product = $this->product(['stock' => 0, 'original_price' => 999999]);
        $variant = Variant::factory()->create([
            'product_id' => $product->id,
            'price' => 100000,
            'promo_price' => null,
            'stock' => 10,
            'weight' => 500,
        ]);

        $payload = $this->payload($product, 3);
        $payload['items'][0]['variant_id'] = $variant->id;

        $order = $this->service->checkout($payload, 'key-varcancel');
        $this->assertSame(7, $variant->fresh()->stock);

        $this->service->cancel($order);
        $this->assertSame(10, $variant->fresh()->stock); // stok varian kembali
    }

    public function test_grand_total_is_subtotal_plus_shipping_without_unique_code(): void
    {
        $product = $this->product(['stock' => 5]);

        $order = app(OrderService::class)->checkout([
            'customer' => ['name' => 'A', 'email' => 'a@test.local', 'phone' => '08'],
            'shipping' => ['destination_id' => 1, 'address' => 'Jl', 'courier' => 'jne', 'cost' => 20000],
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
        ], (string) Str::uuid());

        $this->assertSame(120000, $order->grand_total);
    }

    public function test_markpaid_mengisi_paid_at(): void
    {
        $product = $this->product(['stock' => 10]);
        $order = $this->service->checkout($this->payload($product), 'key-paidflow');

        $this->service->markAsPaid($order->fresh(), 'Admin');

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::PAID, $fresh->status);
        $this->assertNotNull($fresh->paid_at);
    }

    public function test_checkout_merekam_user_id_saat_login(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->product();

        $order = $this->service->checkout($this->payload($product), 'key-user');

        $this->assertSame($user->id, $order->user_id);
    }

    public function test_checkout_guest_user_id_null(): void
    {
        $product = $this->product();

        $order = $this->service->checkout($this->payload($product), 'key-guest');

        $this->assertNull($order->user_id);
    }

    public function test_ongkir_tidak_bisa_dipalsukan_jadi_nol(): void
    {
        $this->app->forgetInstance(ShippingService::class);

        $product = $this->product(['stock' => 10, 'original_price' => 100000, 'weight' => 1000]);

        $payload = $this->payload($product, 1);
        $payload['shipping']['destination_id'] = 44401;
        $payload['shipping']['service'] = 'REG';
        $payload['shipping']['cost'] = 0; // client mencoba gratis

        $order = app(OrderService::class)->checkout($payload, 'key-ongkir-palsu');

        $this->assertGreaterThan(0, $order->shipping_cost);
        $this->assertSame(100000 + $order->shipping_cost, $order->grand_total);
    }

    public function test_order_number_uses_jmy_prefix(): void
    {
        $product = $this->product(['stock' => 5]);

        $order = $this->service->checkout($this->payload($product, 1), 'key-jmy');

        $this->assertMatchesRegularExpression('/^JMY-[A-Z0-9]{10}$/', $order->order_number);
    }
}
