<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

// Permintaan pemilik 2026-11-20: fitur upload foto bukti kirim dibuang tuntas.
// Pengiriman cukup dicatat lewat kolom Nomor Resi / Keterangan Kirim.
class ShippingProofRemovedTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_shipping_proof_route_and_column_are_gone(): void
    {
        $this->assertFalse(Route::has('orders.bukti-kirim'));
        $this->assertFalse(Schema::hasColumn('orders', 'shipping_proof_path'));
        $this->assertFalse(method_exists(OrderService::class, 'simpanBuktiKirim'));
        $this->assertFalse(method_exists(Order::class, 'buktiKirimUrl'));
    }

    public function test_admin_shipped_order_has_no_photo_upload(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::SHIPPED, 'tracking_number' => 'JP1234567890']);

        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('JP1234567890')
            ->assertDontSee('Bukti Kirim')
            ->assertDontSee('Foto bukti kirim')
            ->assertDontSee('Ganti foto')
            ->assertDontSee('Unggah foto')
            ->assertDontSee('bukti_kirim', false);
    }

    public function test_admin_ship_modal_keeps_only_the_tracking_field(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PAID]);

        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Nomor Resi / Keterangan Kirim')
            ->assertDontSee('Foto bukti kirim (opsional)')
            ->assertDontSee('type="file"', false);

        Livewire::actingAs($this->admin())->test('pages::admin.orders.show', ['order' => $order])
            ->set('tracking_number', 'JP9876543210')
            ->call('markShipped')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::SHIPPED, $order->fresh()->status);
        $this->assertSame('JP9876543210', $order->fresh()->tracking_number);
    }

    public function test_customer_order_page_has_no_shipping_proof_button(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::SHIPPED, 'tracking_number' => 'JP1234567890']);

        $this->actingAs($user)->get(route('dashboard.orders.show', $order))
            ->assertOk()
            ->assertSee('JP1234567890')
            ->assertDontSee('Lihat Bukti Kirim');
    }
}
