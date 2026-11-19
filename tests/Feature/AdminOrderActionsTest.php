<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AdminOrderActionsTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENT = 'pages::admin.orders.show';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_admin_memverifikasi_pembayaran_menandai_lunas(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        Livewire::actingAs($admin)
            ->test(self::COMPONENT, ['order' => $order])
            ->call('markPaid');

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::PAID, $fresh->status);
        $this->assertNotNull($fresh->paid_at);
    }
}
