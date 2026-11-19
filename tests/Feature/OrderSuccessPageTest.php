<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OrderSuccessPageTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENT = 'pages::storefront.order-success';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake('public');
    }

    private function configMidtrans(): void
    {
        config([
            'services.midtrans.server_key' => 'test-server-key',
            'services.midtrans.client_key' => 'test-client-key',
            'services.midtrans.is_production' => false,
        ]);
    }

    public function test_halaman_pending_menampilkan_total_pembayaran(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->assertSee(rupiah($order->grand_total));
    }

    public function test_pending_order_menampilkan_tombol_bayar_sekarang_dan_sync(): void
    {
        config([
            'services.midtrans.server_key' => 'test-server-key',
            'services.midtrans.client_key' => 'test-client-key',
            'services.midtrans.is_production' => false,
        ]);
        Http::fake(fn () => Http::response(['transaction_status' => 'pending'], 200));

        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentAttemptStatus::PENDING,
            'snap_token' => 'snap-token-abc',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/abc',
        ]);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->assertSee('Bayar Sekarang')
            ->assertSee('https://app.sandbox.midtrans.com/snap/v4/redirection/abc', false);

        $this->assertSame(1, PaymentAttempt::query()->where('order_id', $order->id)->count());
    }

    public function test_attempt_tertutup_menampilkan_tombol_bayar_ulang(): void
    {
        Http::fake();

        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentAttemptStatus::EXPIRED,
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/lama',
        ]);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->assertSee('Bayar Ulang');
    }

    public function test_membuka_halaman_sukses_tanpa_membayar_tidak_melunasi_order(): void
    {
        $this->configMidtrans();
        Http::fake(fn () => Http::response(['transaction_status' => 'pending'], 200));

        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentAttemptStatus::PENDING,
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/abc',
        ]);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        Livewire::test(self::COMPONENT, ['order' => $order]);

        $order->refresh();
        $this->assertNotSame(OrderStatus::PAID, $order->status);
        $this->assertNull($order->paid_at);
    }

    public function test_judul_hero_mengikuti_status_pesanan(): void
    {
        $judul = [
            OrderStatus::PENDING->value => 'Selesaikan Pembayaran',
            OrderStatus::PAID->value => 'Pembayaran Berhasil',
            OrderStatus::SHIPPED->value => 'Pesanan Sedang Dikirim',
            OrderStatus::CANCELLED->value => 'Pesanan Dibatalkan',
        ];

        foreach ($judul as $status => $teks) {
            $order = Order::factory()->create(['status' => OrderStatus::from($status)]);

            $this->get(route('checkout.success', $order))
                ->assertOk()
                ->assertSee($teks, false);
        }
    }

    public function test_manual_proof_upload_is_gone(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->assertDontSee('Kirim Bukti Pembayaran')
            ->assertDontSee('Kirim Bukti Transfer')
            ->assertDontSee('Transfer ke Rekening BCA');
    }
}
