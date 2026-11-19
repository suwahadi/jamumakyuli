<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppButtonColorTest extends TestCase
{
    use RefreshDatabase;

    // Permintaan pemilik 2026-11-19: tombol WhatsApp memakai hijau khas WA (#25D366)
    // dengan teks putih, termasuk tombol WA mengambang, supaya beda dari tombol keranjang.
    public function test_product_page_whatsapp_buttons_use_whatsapp_green_with_white_text(): void
    {
        SettingService::set('site_whatsapp', '087879865789');
        $product = Product::factory()->create(['is_active' => true, 'stock' => 5]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('bg-[#25D366]', false)
            ->assertSee('bg-[#25D366] font-display text-sm font-bold tracking-wide text-white', false)
            ->assertSee('wa-fab fixed bottom-6 right-4 z-50 grid h-12 w-12 place-items-center rounded-full bg-[#25D366] text-white', false)
            ->assertDontSee('#15803D', false)
            ->assertDontSee('#1FAA55', false);
    }

    public function test_order_success_whatsapp_button_uses_whatsapp_green(): void
    {
        SettingService::set('site_whatsapp', '087879865789');
        $order = Order::factory()->create();

        $this->get(route('checkout.success', $order))
            ->assertOk()
            ->assertSee('Konfirmasi via WhatsApp')
            ->assertSee('bg-[#25D366]', false)
            ->assertDontSee('#15803D', false);
    }
}
