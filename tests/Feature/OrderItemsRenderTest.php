<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderItemsRenderTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithItems(?User $user = null): Order
    {
        $order = Order::factory()->create([
            'user_id' => $user?->id,
            'customer_email' => $user?->email ?? 'tamu@jamumakyuli.test',
            'subtotal' => 350000,
            'shipping_cost' => 20000,
            'grand_total' => 370000,
        ]);

        $plain = Product::factory()->create(['name' => 'Jamu Uji Polos', 'is_active' => true]);

        $withVariant = Product::factory()->create(['name' => 'Jamu Uji Varian', 'is_active' => true]);
        $variant = Variant::factory()->create([
            'product_id' => $withVariant->id,
            'name' => 'Standar',
        ]);

        $order->items()->create([
            'product_id' => $plain->id,
            'variant_id' => null,
            'price' => 150000,
            'quantity' => 1,
            'custom_text' => 'Ukir nama Budi',
            'total' => 150000,
        ]);

        $order->items()->create([
            'product_id' => $withVariant->id,
            'variant_id' => $variant->id,
            'price' => 100000,
            'quantity' => 2,
            'total' => 200000,
        ]);

        return $order;
    }

    public function test_halaman_sukses_menampilkan_setiap_item_pesanan(): void
    {
        $order = $this->orderWithItems();

        $this->get(route('checkout.success', $order->uuid))
            ->assertOk()
            ->assertSee('Jamu Uji Polos')
            ->assertSee('Jamu Uji Varian')
            ->assertSee('Standar')
            ->assertSee('Ukir nama Budi')
            ->assertSee(rupiah(200000));
    }

    public function test_dashboard_pesanan_menampilkan_setiap_item(): void
    {
        $user = User::factory()->create();
        $order = $this->orderWithItems($user);

        $this->actingAs($user)
            ->get(route('dashboard.orders.show', $order->order_number))
            ->assertOk()
            ->assertSee('Jamu Uji Polos')
            ->assertSee('Standar')
            ->assertSee('Ukir nama Budi');
    }

    public function test_admin_pesanan_menampilkan_setiap_item(): void
    {
        $order = $this->orderWithItems();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.show', $order->order_number))
            ->assertOk()
            ->assertSee('Jamu Uji Polos')
            ->assertSee('Standar')
            ->assertSee('Ukir nama Budi');
    }

    public function test_tidak_ada_sisa_penanda_paket_di_halaman_pesanan(): void
    {
        $order = $this->orderWithItems();

        $this->get(route('checkout.success', $order->uuid))
            ->assertOk()
            ->assertDontSee('PAKET')
            ->assertDontSee('Termasuk');
    }

    public function test_halaman_keranjang_menampilkan_isi_keranjang(): void
    {
        $product = Product::factory()->inStock()->create([
            'name' => 'Jamu Uji Keranjang',
            'original_price' => 100000,
            'promo_price' => null,
        ]);

        app(CartService::class)->add($product->id, 2);

        Livewire::test('pages::storefront.cart')
            ->assertSee('Jamu Uji Keranjang')
            ->assertSee(rupiah(200000))
            ->assertDontSee('Termasuk');
    }
}
