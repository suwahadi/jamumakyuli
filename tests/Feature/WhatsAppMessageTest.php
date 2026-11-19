<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Services\SettingService;
use App\Support\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SettingService::set('site_name', 'Jamu Mak Yuli');
        SettingService::set('site_whatsapp', '087879865789');
    }

    private function order(OrderStatus $status = OrderStatus::PENDING): Order
    {
        $product = Product::factory()->create(['name' => 'Jamu Paket Promil Ideal', 'original_price' => 250000, 'promo_price' => null]);

        $order = Order::factory()->create([
            'order_number' => 'JMY-ABC',
            'customer_name' => 'Siti',
            'shipping_courier' => 'jnt',
            'shipping_courier_name' => 'J&T Express',
            'shipping_service' => 'EZ',
            'shipping_cost' => 18000,
            'subtotal' => 250000,
            'grand_total' => 268000,
            'status' => $status,
        ]);
        $order->items()->create(['product_id' => $product->id, 'variant_id' => null, 'price' => 250000, 'quantity' => 1, 'total' => 250000]);

        return $order->fresh('items.product');
    }

    public function test_general_text_follows_brief(): void
    {
        $this->assertSame(
            'Halo Jamu Mak Yuli, saya tertarik dengan Ramuan Rempah Kering Promil Pasutri. Mohon info lebih lanjut.',
            WhatsAppMessage::general()
        );
    }

    public function test_order_confirmation_text(): void
    {
        $this->assertSame(
            "Halo Jamu Mak Yuli, saya ingin konfirmasi pesanan:\nNo. Pesanan: JMY-ABC\nNama: Siti\n1x Jamu Paket Promil Ideal\nOngkir (J&T Express EZ): Rp 18.000\nTotal: Rp 268.000\nStatus: Menunggu Pembayaran",
            WhatsAppMessage::orderConfirmation($this->order())
        );
    }

    public function test_product_inquiry_text(): void
    {
        $product = Product::factory()->create(['name' => 'Jamu Temulawak']);

        $this->assertSame(
            'Halo Jamu Mak Yuli, saya tertarik dengan Jamu Temulawak. Mohon info lebih lanjut.',
            WhatsAppMessage::productInquiry($product)
        );
    }

    public function test_wa_link_encodes_ampersand_newline_and_emoji(): void
    {
        $url = wa_href("J&T #1\nSemangat ❤️");

        $this->assertStringStartsWith('https://wa.me/6287879865789?text=', $url);
        $this->assertSame("J&T #1\nSemangat ❤️", rawurldecode(Str::after($url, '?text=')));
    }

    public function test_success_page_has_confirm_button_except_cancelled(): void
    {
        $pending = $this->order();

        $this->get(route('checkout.success', $pending))
            ->assertOk()
            ->assertSee('Konfirmasi via WhatsApp')
            ->assertSee(rawurlencode('No. Pesanan: JMY-ABC'), false);

        $pending->update(['status' => OrderStatus::CANCELLED]);

        $this->get(route('checkout.success', $pending))
            ->assertOk()
            ->assertDontSee('Konfirmasi via WhatsApp');
    }

    public function test_product_page_has_inquiry_button(): void
    {
        $product = Product::factory()->create(['name' => 'Jamu Temulawak', 'is_active' => true, 'stock' => 5]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Tanya via WhatsApp')
            ->assertSee(rawurlencode('saya tertarik dengan Jamu Temulawak'), false);
    }
}
