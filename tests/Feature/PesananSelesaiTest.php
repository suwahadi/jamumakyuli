<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderActivityType;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Jobs\SendBrevoEmail;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\BrevoService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PesananSelesaiTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_ORDER_COMPONENT = 'pages::admin.orders.show';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeShippingQuote();
    }

    private function pesanan(OrderStatus $status): Order
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

        if ($status !== OrderStatus::PENDING) {
            app(OrderService::class)->markAsPaid($order, 'Admin');
        }

        if ($status === OrderStatus::SHIPPED) {
            app(OrderService::class)->markAsShipped($order->fresh(), 'Admin', 'JP1234567890');
        }

        return $order->fresh();
    }

    public function test_pesanan_dikirim_bisa_ditandai_selesai_dan_memicu_email(): void
    {
        $order = $this->pesanan(OrderStatus::SHIPPED);
        Queue::fake();

        app(OrderService::class)->markAsDelivered($order, 'Admin Toko');

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertTrue($order->activities()->where('type', OrderActivityType::DELIVERED->value)->where('actor', 'Admin Toko')->exists());
        Queue::assertPushed(SendBrevoEmail::class, fn (SendBrevoEmail $job): bool => $job->event === 'order_delivered' && $job->order->is($order));
    }

    public function test_pesanan_yang_belum_dikirim_tidak_bisa_ditandai_selesai(): void
    {
        $order = $this->pesanan(OrderStatus::PAID);
        Queue::fake();

        try {
            app(OrderService::class)->markAsDelivered($order, 'Admin');
            $this->fail('Pesanan lunas yang belum dikirim tidak boleh ditandai selesai.');
        } catch (BusinessRuleException) {
        }

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_menandai_selesai_dua_kali_hanya_mengirim_satu_email(): void
    {
        $order = $this->pesanan(OrderStatus::SHIPPED);
        Queue::fake();

        app(OrderService::class)->markAsDelivered($order, 'Admin');
        app(OrderService::class)->markAsDelivered($order->fresh(), 'Admin');

        Queue::assertPushed(SendBrevoEmail::class, 1);
    }

    public function test_konfirmasi_tandai_lunas_menyebut_status_diproses(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(self::ADMIN_ORDER_COMPONENT, ['order' => $this->pesanan(OrderStatus::PENDING)])
            ->assertSee('Status pesanan akan menjadi <span class="font-semibold">Diproses</span>', false);
    }

    public function test_tombol_tandai_selesai_hanya_muncul_saat_pesanan_dikirim(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(self::ADMIN_ORDER_COMPONENT, ['order' => $this->pesanan(OrderStatus::SHIPPED)])
            ->assertSee('Tandai Diterima')
            ->assertSee('Tandai pesanan diterima?');

        Livewire::actingAs($admin)
            ->test(self::ADMIN_ORDER_COMPONENT, ['order' => $this->pesanan(OrderStatus::PAID)])
            ->assertDontSee('Tandai Diterima');
    }

    public function test_admin_menandai_selesai_dari_detail_pesanan(): void
    {
        $order = $this->pesanan(OrderStatus::SHIPPED);
        Queue::fake();

        Livewire::actingAs(User::factory()->admin()->create(['name' => 'Rina']))
            ->test(self::ADMIN_ORDER_COMPONENT, ['order' => $order])
            ->call('markDelivered')
            ->assertHasNoErrors()
            ->assertSee('Pesanan sudah diterima');

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertTrue($order->activities()->where('type', OrderActivityType::DELIVERED->value)->where('actor', 'Rina')->exists());
        Queue::assertPushed(SendBrevoEmail::class, fn (SendBrevoEmail $job): bool => $job->event === 'order_delivered');
    }

    public function test_email_selesai_hanya_ke_pembeli_berisi_ucapan_dan_kontak_whatsapp(): void
    {
        Cache::forever('setting.site_name', 'Jamu Mak Yuli');
        Cache::forever('setting.site_whatsapp', '087879865789');
        $order = $this->pesanan(OrderStatus::SHIPPED);

        $payload = app(BrevoService::class)->buildOrderDeliveredPayload($order);

        $this->assertCount(1, $payload['to']);
        $this->assertSame('budi@email.com', $payload['to'][0]['email']);
        $this->assertSame('Pesanan Selesai: #'.$order->order_number, $payload['subject']);
        $this->assertStringContainsString('sudah selesai', $payload['htmlContent']);
        $this->assertStringContainsString('Terima kasih sudah berbelanja di', $payload['htmlContent']);
        $this->assertStringContainsString('087879865789', $payload['htmlContent']);
        $this->assertStringContainsString('https://wa.me/6287879865789', $payload['htmlContent']);
        $this->assertStringContainsString(route('checkout.success', $order), $payload['htmlContent']);
        $this->assertStringNotContainsString('—', $payload['htmlContent']);
    }

    /** Status ini dulu tak pernah tercapai, sehingga judul halaman pelanggan galat 500. */
    public function test_halaman_pesanan_pelanggan_terbuka_setelah_selesai(): void
    {
        $user = User::factory()->create();
        $order = $this->pesanan(OrderStatus::SHIPPED);
        $order->update(['user_id' => $user->id]);
        app(OrderService::class)->markAsDelivered($order->fresh(), 'Admin');

        $this->get(route('checkout.success', $order))
            ->assertOk()
            ->assertSee('<title>Pesanan Diterima', false)
            ->assertSee('Pesanan sudah sampai. Terima kasih telah berbelanja.');

        $this->actingAs($user)->get(route('dashboard.orders.show', $order))->assertOk();
    }

    public function test_job_email_mengenali_peristiwa_selesai(): void
    {
        $order = $this->pesanan(OrderStatus::SHIPPED);
        $brevo = $this->mock(BrevoService::class);
        $brevo->shouldReceive('buildOrderDeliveredPayload')->once()->andReturn(['subject' => 'x']);
        $brevo->shouldReceive('send')->once()->andReturn(true);

        (new SendBrevoEmail($order, 'order_delivered'))->handle($brevo);
    }
}
