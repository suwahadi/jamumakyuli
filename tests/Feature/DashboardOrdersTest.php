<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_dashboard_pages(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('dashboard.orders.index'))->assertRedirect(route('login'));
    }

    public function test_dashboard_only_lists_orders_belonging_to_the_user(): void
    {
        $user = User::factory()->create(['email' => 'mine@example.com']);
        $other = User::factory()->create(['email' => 'other@example.com']);

        $own = Order::factory()->create(['user_id' => $user->id, 'customer_email' => $user->email]);
        $foreign = Order::factory()->create(['user_id' => $other->id, 'customer_email' => $other->email]);

        $this->actingAs($user)
            ->get(route('dashboard.orders.index'))
            ->assertOk()
            ->assertSee($own->order_number)
            ->assertDontSee($foreign->order_number);
    }

    public function test_stats_count_disiapkan_dikirim_and_total_for_the_user_only(): void
    {
        $user = User::factory()->create(['email' => 'mine@example.com']);
        $other = User::factory()->create(['email' => 'other@example.com']);

        // Milik user: 2 PAID (disiapkan), 1 SHIPPED + 1 DELIVERED (dikirim), 1 PENDING => total 5.
        Order::factory()->count(2)->create(['user_id' => $user->id, 'customer_email' => $user->email, 'status' => OrderStatus::PAID]);
        Order::factory()->create(['user_id' => $user->id, 'customer_email' => $user->email, 'status' => OrderStatus::SHIPPED]);
        Order::factory()->create(['user_id' => $user->id, 'customer_email' => $user->email, 'status' => OrderStatus::DELIVERED]);
        Order::factory()->create(['user_id' => $user->id, 'customer_email' => $user->email, 'status' => OrderStatus::PENDING]);

        // Milik user lain: tidak boleh ikut terhitung.
        Order::factory()->count(3)->create(['user_id' => $other->id, 'customer_email' => $other->email, 'status' => OrderStatus::PAID]);

        $component = Livewire::actingAs($user)->test('pages::dashboard.index');

        $this->assertSame(2, $component->instance()->stats['disiapkan']);
        $this->assertSame(2, $component->instance()->stats['dikirim']);
        $this->assertSame(5, $component->instance()->stats['total']);
    }

    public function test_user_can_view_own_order_detail(): void
    {
        $user = User::factory()->create(['email' => 'mine@example.com']);
        $order = Order::factory()->create(['user_id' => $user->id, 'customer_email' => $user->email]);

        $this->actingAs($user)
            ->get(route('dashboard.orders.show', $order->order_number))
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_user_cannot_view_another_users_order_detail(): void
    {
        $user = User::factory()->create(['email' => 'mine@example.com']);
        $other = User::factory()->create(['email' => 'other@example.com']);
        $foreign = Order::factory()->create(['user_id' => $other->id, 'customer_email' => $other->email]);

        $this->actingAs($user)
            ->get(route('dashboard.orders.show', $foreign->order_number))
            ->assertForbidden();
    }

    public function test_orders_list_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create(['email' => 'mine@example.com']);
        $paid = Order::factory()->create(['user_id' => $user->id, 'customer_email' => $user->email, 'status' => OrderStatus::PAID]);
        $pending = Order::factory()->create(['user_id' => $user->id, 'customer_email' => $user->email, 'status' => OrderStatus::PENDING]);

        Livewire::actingAs($user)
            ->test('pages::dashboard.orders.index')
            ->set('status', OrderStatus::PAID->value)
            ->assertSee($paid->order_number)
            ->assertDontSee($pending->order_number);
    }
}
