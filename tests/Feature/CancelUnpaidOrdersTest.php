<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelUnpaidOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function batasWaktu(int $menit): void
    {
        Setting::query()->updateOrCreate(['key' => 'expiry_order'], ['value' => (string) $menit]);
        cache()->flush();
    }

    private function pesananLama(OrderStatus $status, int $jamLalu): Order
    {
        $order = Order::factory()->create(['status' => $status]);
        $order->forceFill(['created_at' => now()->subHours($jamLalu)])->save();

        return $order;
    }

    // Pelanggan yang sudah mengunggah bukti sedang menunggu admin, bukan
    // menunggak. Pembatalan otomatis tidak boleh menyentuh mereka.
    public function test_pesanan_belum_bayar_melewati_batas_dibatalkan(): void
    {
        $this->batasWaktu(1440);
        $order = $this->pesananLama(OrderStatus::PENDING, 25);

        $this->artisan('orders:cancel-unpaid');

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
    }

    // Dengan batas 24 jam, pesanan yang masuk malam hari harus selamat sampai
    // pelanggan sempat membuka aplikasi banknya keesokan pagi.
    public function test_pesanan_belum_bayar_dalam_24_jam_tetap_hidup(): void
    {
        $this->batasWaktu(1440);
        $order = $this->pesananLama(OrderStatus::PENDING, 20);

        $this->artisan('orders:cancel-unpaid');

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_pesanan_lunas_tidak_tersentuh(): void
    {
        $this->batasWaktu(1440);
        $order = $this->pesananLama(OrderStatus::PAID, 72);

        $this->artisan('orders:cancel-unpaid');

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
    }
}
