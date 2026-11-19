<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class InvoicePesananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeShippingQuote();
        Cache::forever('setting.site_name', 'Jamu Mak Yuli');
        Cache::forever('setting.site_whatsapp', '087879865789');
        Cache::forever('setting.site_email', 'toko@jamumakyuli.test');
    }

    private function pesanan(OrderStatus $status = OrderStatus::PENDING): Order
    {
        $product = Product::factory()->create([
            'name' => 'Filter Bensin Uji Invoice', 'sku' => 'JMY-INV-001',
            'is_active' => true, 'stock' => 10, 'original_price' => 100000, 'promo_price' => null,
        ]);

        $order = app(OrderService::class)->checkout([
            'customer' => ['name' => 'Budi', 'email' => 'budi@email.com', 'phone' => '081234567890'],
            'shipping' => [
                'destination_id' => 17473, 'address' => 'Jl. Mesin 1',
                'courier' => 'jne', 'service' => 'REG', 'cost' => 20000,
            ],
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], (string) Str::uuid());

        if (in_array($status, [OrderStatus::PAID, OrderStatus::SHIPPED], true)) {
            app(OrderService::class)->markAsPaid($order, 'Admin');
        }

        if ($status === OrderStatus::SHIPPED) {
            app(OrderService::class)->markAsShipped($order->fresh(), 'Admin', 'JP1234567890');
        }

        if ($status === OrderStatus::CANCELLED) {
            app(OrderService::class)->cancel($order, 'Admin');
        }

        return $order->fresh();
    }

    public function test_invoice_pesanan_lunas_berisi_rincian_dan_pita_lunas(): void
    {
        $order = $this->pesanan(OrderStatus::SHIPPED);

        $this->get(route('orders.invoice', $order))
            ->assertOk()
            ->assertSee('<title>Invoice '.$order->order_number.'</title>', false)
            ->assertSee('noindex', false)
            ->assertSee('>LUNAS<', false)
            ->assertSee($order->order_number)
            ->assertSee('Ditagihkan kepada')
            ->assertSee('Budi')
            ->assertSee('Jl. Mesin 1')
            ->assertSee('JP1234567890')
            ->assertSee('Filter Bensin Uji Invoice')
            ->assertSee(rupiah(100000))
            ->assertSee(rupiah($order->grand_total))
            ->assertSee('Total dibayar')
            ->assertSee('Transfer bank BCA')
            ->assertSee('Terima kasih sudah berbelanja di Jamu Mak Yuli')
            ->assertSee('087879865789')
            ->assertSeeInOrder(['Kembali ke pesanan', '>Unduh<', '>Cetak<'], false)
            ->assertSee('data-unduh-pdf="invoice-'.$order->order_number.'.pdf"', false)
            ->assertSeeInOrder(['data-unduh-pdf=', 'bg-brand-green', '>Unduh<', 'bg-white', '>Cetak<'], false)
            ->assertSee('data-ukuran-mm="210x297"', false)
            ->assertDontSee('Cetak / Simpan PDF');
    }

    public function test_invoice_pesanan_belum_lunas_tanpa_pita_lunas(): void
    {
        $order = $this->pesanan();

        $this->get(route('orders.invoice', $order))
            ->assertOk()
            ->assertDontSee('>LUNAS<', false)
            ->assertSee('Total tagihan')
            ->assertDontSee('Total dibayar');
    }

    public function test_invoice_pesanan_dibatalkan_tidak_tersedia(): void
    {
        $order = $this->pesanan(OrderStatus::CANCELLED);

        $this->get(route('orders.invoice', $order))->assertNotFound();
    }

    public function test_halaman_pesanan_pembeli_dan_tamu_punya_tautan_lihat_invoice(): void
    {
        $user = User::factory()->create();
        $order = $this->pesanan(OrderStatus::PAID);
        $order->update(['user_id' => $user->id]);
        $tautan = 'href="'.route('orders.invoice', $order).'"';

        $this->get(route('checkout.success', $order))
            ->assertOk()
            ->assertSee($tautan, false)
            ->assertSee('Lihat Invoice');

        $this->actingAs($user)->get(route('dashboard.orders.show', $order))
            ->assertOk()
            ->assertSee($tautan, false);
    }

    public function test_detail_pesanan_admin_punya_tautan_lihat_invoice(): void
    {
        $order = $this->pesanan(OrderStatus::PAID);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.orders.show', ['order' => $order])
            ->assertSee('href="'.route('orders.invoice', $order).'"', false)
            ->assertSee('Lihat Invoice');
    }

    public function test_pesanan_dibatalkan_tidak_menawarkan_invoice(): void
    {
        $order = $this->pesanan(OrderStatus::CANCELLED);

        $this->get(route('checkout.success', $order))->assertDontSee('Lihat Invoice');

        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.orders.show', ['order' => $order])
            ->assertDontSee('Lihat Invoice');
    }
}
