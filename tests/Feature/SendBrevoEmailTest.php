<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendBrevoEmail;
use App\Models\Order;
use App\Services\BrevoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendBrevoEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.brevo.key' => 'test-key']);
        Http::fake([
            '*/smtp/email' => Http::response(['messageId' => 'x'], 201),
        ]);
    }

    public function test_event_new_order_mengirim_email_pelanggan_dan_admin(): void
    {
        $order = Order::factory()->create();

        (new SendBrevoEmail($order, 'new_order'))->handle(app(BrevoService::class));

        Http::assertSentCount(2);
    }

    public function test_event_payment_paid_mengirim_email_pelanggan_dan_admin(): void
    {
        $order = Order::factory()->paid()->create();

        (new SendBrevoEmail($order, 'payment_paid'))->handle(app(BrevoService::class));

        Http::assertSentCount(2);
    }

    public function test_event_order_shipped_hanya_mengirim_email_ke_pembeli(): void
    {
        config(['services.brevo.admin_email' => 'admin@jamumakyuli.test']);
        $order = Order::factory()->create(['customer_email' => 'pembeli@jamumakyuli.test']);

        (new SendBrevoEmail($order, 'order_shipped'))->handle(app(BrevoService::class));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['to'][0]['email'] === 'pembeli@jamumakyuli.test');
    }
}
