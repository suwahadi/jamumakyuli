<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderPaidNotification;
use App\Services\WebNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kepemilikan pesanan ditentukan oleh user_id, bukan kecocokan email. Email akun
 * tidak diverifikasi dan bisa diganti bebas, jadi siapa pun yang mendaftar atau
 * mengganti email ke alamat pembeli tamu dulu bisa membaca nama, telepon, dan
 * alamat lengkap pembeli itu dari dashboard.
 */
class KepemilikanPesananTest extends TestCase
{
    use RefreshDatabase;

    private function pesananTamu(string $email): Order
    {
        return Order::factory()->create(['user_id' => null, 'customer_email' => $email]);
    }

    public function test_akun_dengan_email_pembeli_tamu_tidak_melihat_pesanan_tamu_di_daftar(): void
    {
        $tamu = $this->pesananTamu('budi@contoh.id');
        $penyerang = User::factory()->create(['email' => 'budi@contoh.id']);

        Livewire::actingAs($penyerang)
            ->test('pages::dashboard.orders.index')
            ->assertDontSee($tamu->order_number);
    }

    public function test_akun_dengan_email_pembeli_tamu_ditolak_membuka_detail_pesanan_tamu(): void
    {
        $tamu = $this->pesananTamu('budi@contoh.id');
        $penyerang = User::factory()->create(['email' => 'budi@contoh.id']);

        $this->actingAs($penyerang)
            ->get(route('dashboard.orders.show', $tamu))
            ->assertForbidden();
    }

    public function test_statistik_dashboard_tidak_menghitung_pesanan_tamu_dengan_email_yang_sama(): void
    {
        $this->pesananTamu('budi@contoh.id');
        $penyerang = User::factory()->create(['email' => 'budi@contoh.id']);

        $dashboard = Livewire::actingAs($penyerang)->test('pages::dashboard.index');

        $this->assertSame(0, $dashboard->instance()->stats()['total']);
    }

    public function test_akun_melihat_pesanannya_walau_email_di_pesanan_berbeda(): void
    {
        $pemilik = User::factory()->create(['email' => 'pemilik@contoh.id']);
        $order = Order::factory()->create(['user_id' => $pemilik->id, 'customer_email' => 'lain@contoh.id']);

        Livewire::actingAs($pemilik)
            ->test('pages::dashboard.orders.index')
            ->assertSee($order->order_number);

        $this->actingAs($pemilik)
            ->get(route('dashboard.orders.show', $order))
            ->assertOk();
    }

    // Pengguna yang login bisa mengetik email orang lain saat checkout. Pesanan itu
    // tetap milik pemesannya, bukan pemilik email yang diketik.
    public function test_pesanan_tidak_berpindah_ke_akun_pemilik_email_yang_diketik(): void
    {
        $pemesan = User::factory()->create();
        $korban = User::factory()->create(['email' => 'korban@contoh.id']);
        $order = Order::factory()->create(['user_id' => $pemesan->id, 'customer_email' => 'korban@contoh.id']);

        $this->actingAs($korban)
            ->get(route('dashboard.orders.show', $order))
            ->assertForbidden();
    }

    public function test_notifikasi_lunas_dikirim_ke_akun_pemesan_bukan_pemilik_email(): void
    {
        Notification::fake();
        $pemesan = User::factory()->create();
        $pemilikEmail = User::factory()->create(['email' => 'korban@contoh.id']);
        $order = Order::factory()->create(['user_id' => $pemesan->id, 'customer_email' => 'korban@contoh.id']);

        app(WebNotificationService::class)->orderPaid($order);

        Notification::assertSentTo($pemesan, OrderPaidNotification::class);
        Notification::assertNotSentTo($pemilikEmail, OrderPaidNotification::class);
    }

    public function test_pesanan_tamu_tidak_mengirim_notifikasi_ke_akun_yang_emailnya_sama(): void
    {
        Notification::fake();
        $pemilikEmail = User::factory()->create(['email' => 'budi@contoh.id']);

        app(WebNotificationService::class)->orderPaid($this->pesananTamu('budi@contoh.id'));

        Notification::assertNotSentTo($pemilikEmail, OrderPaidNotification::class);
    }
}
