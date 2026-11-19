<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Services\BrevoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBrevoEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public Order $order,
        public string $event,
    ) {}

    public function handle(BrevoService $brevo): void
    {
        $payloads = match ($this->event) {
            'new_order' => [
                $brevo->buildNewOrderCustomerPayload($this->order),
                $brevo->buildNewOrderAdminPayload($this->order),
            ],
            'payment_paid' => [
                $brevo->buildPaymentPaidPayload($this->order),
                $brevo->buildPaymentPaidAdminPayload($this->order),
            ],
            'order_shipped' => [
                $brevo->buildOrderShippedPayload($this->order),
            ],
            'order_delivered' => [
                $brevo->buildOrderDeliveredPayload($this->order),
            ],
            'payment_alert_mismatch' => [
                $brevo->buildPaymentAlertPayload($this->order, 'Nominal pembayaran tidak cocok dengan total order.'),
            ],
            'payment_alert_double' => [
                $brevo->buildPaymentAlertPayload($this->order, 'Order sudah lunas tetapi ada pembayaran dari attempt lain. Perlu review refund.'),
            ],
            'payment_alert_cancelled' => [
                $brevo->buildPaymentAlertPayload($this->order, 'Pembayaran masuk untuk pesanan yang sudah dibatalkan. Stok sudah dikembalikan; perlu review refund.'),
            ],
            default => [],
        };

        foreach ($payloads as $payload) {
            $brevo->send($payload);
        }
    }
}
