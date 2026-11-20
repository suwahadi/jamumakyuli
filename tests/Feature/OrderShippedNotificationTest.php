<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\SendBrevoEmail;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\BrevoService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OrderShippedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_ORDER_COMPONENT = 'pages::admin.orders.show';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeShippingQuote();
    }

    private function paidOrder(): Order
    {
        $product = Product::factory()->create([
            'is_active' => true, 'stock' => 10, 'original_price' => 100000, 'promo_price' => null,
        ]);

        $order = app(OrderService::class)->checkout([
            'customer' => ['name' => 'Budi', 'email' => 'budi@email.com', 'phone' => '0812'],
            'shipping' => [
                'destination_id' => 17473, 'address' => 'Jl. Melati 1',
                'courier' => 'jne', 'service' => 'REG', 'cost' => 20000,
            ],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], (string) Str::uuid());

        app(OrderService::class)->markAsPaid($order, 'Admin');

        return $order->fresh();
    }

    public function test_menandai_dikirim_menyimpan_nomor_resi(): void
    {
        $order = $this->paidOrder();

        app(OrderService::class)->markAsShipped($order, 'Admin', 'JP1234567890');

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::SHIPPED, $fresh->status);
        $this->assertSame('JP1234567890', $fresh->tracking_number);
    }

    public function test_resi_boleh_teks_bebas_untuk_kurir_internal(): void
    {
        $order = $this->paidOrder();

        app(OrderService::class)->markAsShipped($order, 'Admin', 'Diantar 12 Agu oleh Andi');

        $this->assertSame('Diantar 12 Agu oleh Andi', $order->fresh()->tracking_number);
    }

    public function test_menandai_dikirim_dua_kali_tidak_mengubah_resi_pertama(): void
    {
        $order = $this->paidOrder();

        app(OrderService::class)->markAsShipped($order, 'Admin', 'RESI-PERTAMA');
        app(OrderService::class)->markAsShipped($order->fresh(), 'Admin', 'RESI-KEDUA');

        $this->assertSame('RESI-PERTAMA', $order->fresh()->tracking_number);
    }

    public function test_menandai_dikirim_memicu_email_barang_dikirim(): void
    {
        Queue::fake();
        $order = $this->paidOrder();

        app(OrderService::class)->markAsShipped($order, 'Admin', 'JP1234567890');

        Queue::assertPushed(SendBrevoEmail::class, function (SendBrevoEmail $job) use ($order): bool {
            return $job->event === 'order_shipped' && $job->order->is($order);
        });
    }

    public function test_email_barang_dikirim_hanya_ke_pembeli(): void
    {
        $order = $this->paidOrder();
        $order->update(['tracking_number' => 'JP1234567890']);

        $payload = app(BrevoService::class)->buildOrderShippedPayload($order->fresh());

        $this->assertCount(1, $payload['to']);
        $this->assertSame('budi@email.com', $payload['to'][0]['email']);
        $this->assertStringContainsString($order->order_number, $payload['subject']);
        $this->assertStringContainsString('JP1234567890', $payload['htmlContent']);
    }

    public function test_pembayaran_berhasil_mengirim_ke_pembeli_dan_admin(): void
    {
        config()->set('services.brevo.admin_email', 'admin@jamumakyuli.test');

        $order = $this->paidOrder();
        $brevo = app(BrevoService::class);

        $pembeli = $brevo->buildPaymentPaidPayload($order);
        $admin = $brevo->buildPaymentPaidAdminPayload($order);

        $this->assertSame('budi@email.com', $pembeli['to'][0]['email']);
        $this->assertSame('admin@jamumakyuli.test', $admin['to'][0]['email']);
        $this->assertStringContainsString($order->order_number, $admin['subject']);
    }

    public function test_admin_tidak_bisa_menandai_dikirim_tanpa_resi(): void
    {
        $order = $this->paidOrder();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(self::ADMIN_ORDER_COMPONENT, ['order' => $order])
            ->set('tracking_number', '')
            ->call('markShipped')
            ->assertHasErrors(['tracking_number' => 'required']);

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
    }

    public function test_admin_menandai_dikirim_dengan_resi(): void
    {
        $order = $this->paidOrder();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(self::ADMIN_ORDER_COMPONENT, ['order' => $order])
            ->set('tracking_number', 'JP1234567890')
            ->call('markShipped')
            ->assertHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::SHIPPED, $fresh->status);
        $this->assertSame('JP1234567890', $fresh->tracking_number);
    }

    // Resi salah ketik membuat pembeli tidak bisa melacak paket, jadi resi
    // yang diperbaiki harus sampai ke pembeli lewat email yang sama.
    public function test_mengganti_resi_mengirim_ulang_email_ke_pembeli(): void
    {
        $order = $this->paidOrder();
        app(OrderService::class)->markAsShipped($order, 'Admin', 'RESI-LAMA');

        Queue::fake();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(self::ADMIN_ORDER_COMPONENT, ['order' => $order->fresh()])
            ->set('tracking_number', 'RESI-BARU')
            ->call('saveTracking')
            ->assertHasNoErrors();

        $this->assertSame('RESI-BARU', $order->fresh()->tracking_number);
        Queue::assertPushed(SendBrevoEmail::class, fn (SendBrevoEmail $job): bool => $job->event === 'order_shipped' && $job->order->is($order));
        $this->assertTrue($order->activities()->where('description', 'like', '%RESI-BARU%')->exists());
    }

    public function test_menyimpan_resi_yang_sama_tidak_mengirim_email(): void
    {
        $order = $this->paidOrder();
        app(OrderService::class)->markAsShipped($order, 'Admin', 'RESI-SAMA');

        Queue::fake();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(self::ADMIN_ORDER_COMPONENT, ['order' => $order->fresh()])
            ->set('tracking_number', '  RESI-SAMA ')
            ->call('saveTracking')
            ->assertHasNoErrors();

        Queue::assertNothingPushed();
    }

    public function test_resi_tampil_di_halaman_sukses_pesanan(): void
    {
        $order = $this->paidOrder();
        app(OrderService::class)->markAsShipped($order, 'Admin', 'JP1234567890');

        $this->get(route('checkout.success', $order->fresh()))
            ->assertSee('JP1234567890');
    }

    // Permintaan pemilik 2026-11-20: halaman pesanan pembeli cukup menampilkan nomor resi
    // yang bisa disalin, tanpa tautan "Lacak di situs" dan petunjuk tempel resi.
    public function test_halaman_pesanan_dikirim_menyediakan_salin_resi_tanpa_tautan_lacak(): void
    {
        $order = $this->paidOrder();
        app(OrderService::class)->markAsShipped($order, 'Admin', 'JP1234567890');

        $this->get(route('checkout.success', $order->fresh()))
            ->assertOk()
            ->assertSee('Salin nomor resi')
            ->assertDontSee('href="https://www.jne.co.id/tracking-package"', false)
            ->assertDontSee('Lacak di situs')
            ->assertDontSee('tempel di halaman lacak kurir')
            ->assertSee('Pesanan Anda dalam perjalanan.')
            ->assertDontSee('Total Pembayaran');
    }

    // Permintaan pemilik 2026-11-20: email Pesanan Dikirim juga tanpa tautan "Lacak di situs"
    // dan petunjuk tempel resi; nomor resi tetap dicantumkan.
    public function test_email_pesanan_dikirim_tanpa_tautan_lacak_kurir(): void
    {
        $order = $this->paidOrder();
        app(OrderService::class)->markAsShipped($order, 'Admin', 'JP1234567890');

        $html = app(BrevoService::class)->buildOrderShippedPayload($order->fresh())['htmlContent'];

        $this->assertStringContainsString('JP1234567890', $html);
        $this->assertStringNotContainsString('Lacak di situs', $html);
        $this->assertStringNotContainsString('tracking-package', $html);
        $this->assertStringNotContainsString('tempel di halaman lacak kurir', $html);
        $this->assertFalse(method_exists(Order::class, 'trackingUrl'));
    }

    public function test_keterangan_kirim_tanpa_resi_tetap_tampil_tanpa_tautan(): void
    {
        $order = $this->paidOrder();
        $order->update(['status' => OrderStatus::SHIPPED, 'tracking_number' => 'Diantar kurir toko', 'shipping_courier' => 'pos']);

        $this->get(route('checkout.success', $order->fresh()))
            ->assertSee('Diantar kurir toko')
            ->assertDontSee('Lacak di situs');
    }

    public function test_dashboard_pembeli_menampilkan_salin_resi_tanpa_tautan_lacak(): void
    {
        $user = User::factory()->create();
        $order = $this->paidOrder();
        $order->update(['user_id' => $user->id, 'status' => OrderStatus::SHIPPED, 'tracking_number' => 'JP1234567890']);

        $this->actingAs($user)->get(route('dashboard.orders.show', $order->fresh()))
            ->assertOk()
            ->assertSee('Salin nomor resi')
            ->assertDontSee('href="https://www.jne.co.id/tracking-package"', false)
            ->assertDontSee('Lacak di situs');
    }

    public function test_halaman_sukses_tanpa_resi_tidak_menampilkan_barisnya(): void
    {
        $order = $this->paidOrder();

        $this->get(route('checkout.success', $order))
            ->assertDontSee('No. Resi');
    }
}
