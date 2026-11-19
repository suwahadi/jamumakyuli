<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Services\BrevoService;
use App\Services\OrderService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderEmailRenderTest extends TestCase
{
    use RefreshDatabase;

    private BrevoService $brevo;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeShippingQuote();
        $this->brevo = app(BrevoService::class);
        $this->orders = app(OrderService::class);
    }

    private function order(): Order
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'stock' => 10,
            'original_price' => 100000,
            'promo_price' => null,
        ]);

        return $this->orders->checkout([
            'customer' => ['name' => 'Budi Santoso', 'email' => 'budi@email.com', 'phone' => '0812'],
            'shipping' => [
                'destination_id' => 17473,
                'destination_label' => 'JATINEGARA, CAKUNG, JAKARTA TIMUR',
                'address' => 'Jl. Melati 1',
                'courier' => 'jne',
                'cost' => 20000,
            ],
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], 'key-'.uniqid());
    }

    public function test_email_pesanan_baru_pelanggan_ter_render(): void
    {
        $order = $this->order();

        $payload = $this->brevo->buildNewOrderCustomerPayload($order);

        $this->assertStringContainsString($order->order_number, $payload['subject']);
        $this->assertStringContainsString($order->order_number, $payload['htmlContent']);
        $this->assertStringContainsString('Budi Santoso', $payload['htmlContent']);
        $this->assertStringContainsString('budi@email.com', $payload['to'][0]['email']);
    }

    public function test_email_pesanan_baru_admin_ter_render(): void
    {
        $order = $this->order();

        $payload = $this->brevo->buildNewOrderAdminPayload($order);

        $this->assertStringContainsString($order->order_number, $payload['subject']);
        $this->assertStringContainsString('Pesanan Baru', $payload['htmlContent']);
        $this->assertStringContainsString('Buka di Panel Admin', $payload['htmlContent']);
    }

    public function test_email_pembayaran_lunas_ter_render(): void
    {
        $order = $this->order();
        $order->update(['paid_at' => now()]);

        $payload = $this->brevo->buildPaymentPaidPayload($order);

        $this->assertStringContainsString($order->order_number, $payload['subject']);
        $this->assertStringContainsString('LUNAS', $payload['htmlContent']);
        $this->assertStringContainsString('Lihat Detail Pesanan', $payload['htmlContent']);
    }

    public function test_all_order_emails_render_with_jamu_brand(): void
    {
        SettingService::set('site_name', 'Jamu Mak Yuli');
        $order = $this->order();

        foreach (['new-order-customer', 'new-order-admin', 'payment-paid', 'payment-paid-admin', 'order-shipped', 'order-delivered', 'payment-alert'] as $tpl) {
            $html = view('emails.orders.'.$tpl, ['order' => $order, 'reason' => 'x'])->render();

            $this->assertStringContainsString('Jamu Mak Yuli', $html, $tpl);
            if ($tpl !== 'payment-alert') {
                $this->assertStringContainsString('#2F7D32', $html, $tpl);
            }
            $this->assertStringNotContainsString('#de1414', $html, $tpl);
            $this->assertStringNotContainsStringIgnoringCase('transfer', $html, $tpl);
            $this->assertStringNotContainsStringIgnoringCase('honda', $html, $tpl);
        }
    }
}
