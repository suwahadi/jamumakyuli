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

    // Permintaan pemilik 2026-11-20: hijau WA digelapkan ke #0E7C6F supaya teks putih
    // lolos kontras WCAG AA (5.08:1); #25D366 hanya 1.98:1. Berlaku juga untuk tombol mengambang.
    public function test_product_page_whatsapp_buttons_use_readable_whatsapp_green(): void
    {
        SettingService::set('site_whatsapp', '087879865789');
        $product = Product::factory()->create(['is_active' => true, 'stock' => 5]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('bg-[#0E7C6F] font-display text-sm font-bold tracking-wide text-white', false)
            ->assertSee('wa-fab fixed bottom-6 right-4 z-50 grid h-12 w-12 place-items-center rounded-full bg-[#0E7C6F] text-white', false)
            ->assertSee('hover:bg-[#0E7C6F] hover:text-white', false)
            ->assertDontSee('#25D366', false);
    }

    public function test_order_success_whatsapp_button_uses_readable_whatsapp_green(): void
    {
        SettingService::set('site_whatsapp', '087879865789');
        $order = Order::factory()->create();

        $this->get(route('checkout.success', $order))
            ->assertOk()
            ->assertSee('Konfirmasi via WhatsApp')
            ->assertSee('bg-[#0E7C6F]', false)
            ->assertDontSee('#25D366', false);
    }
}
