<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Poin 10 brief klien: setelah pembayaran, resi dan tracking harus muncul
 * dengan benar. Berkas ini mengunci perilakunya di tiga tempat pelanggan
 * melihat pesanannya.
 */
class TrackingVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private const RESI = 'JNE0123456789';

    public function test_resi_tampil_di_halaman_pesanan_publik(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::SHIPPED,
            'tracking_number' => self::RESI,
        ]);

        $this->get(route('checkout.success', $order))
            ->assertOk()
            ->assertSee(self::RESI);
    }

    public function test_resi_tampil_di_dashboard_member(): void
    {
        $user = User::factory()->create();
        // OrderPolicy mencocokkan pemilik lewat customer_email, bukan user_id.
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'customer_email' => $user->email,
            'status' => OrderStatus::SHIPPED,
            'tracking_number' => self::RESI,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.orders.show', $order))
            ->assertOk()
            ->assertSee(self::RESI);
    }

    public function test_resi_belum_tampil_sebelum_pesanan_dikirim(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::PAID,
            'tracking_number' => null,
        ]);

        $this->get(route('checkout.success', $order))
            ->assertOk()
            ->assertDontSee(self::RESI);
    }

    public function test_admin_dapat_menyimpan_resi(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = Order::factory()->create(['status' => OrderStatus::SHIPPED]);

        Livewire::actingAs($admin)
            ->test('pages::admin.orders.show', ['order' => $order])
            ->set('tracking_number', self::RESI)
            ->call('saveTracking');

        $this->assertSame(self::RESI, $order->fresh()->tracking_number);
    }
}
