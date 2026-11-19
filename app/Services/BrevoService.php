<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoService
{
    public function send(array $payload): bool
    {
        $baseUrl = rtrim((string) config('services.brevo.base_url'), '/');
        $apiKey = config('services.brevo.key');

        if (blank($apiKey)) {
            Log::debug('Brevo API key kosong; email dilewati.', ['subject' => $payload['subject'] ?? null]);

            return false;
        }

        $response = Http::withHeaders([
            'api-key' => $apiKey,
            'Content-Type' => 'application/json',
            'accept' => 'application/json',
        ])->post($baseUrl.'/smtp/email', $payload);

        if ($response->failed()) {
            Log::error('Brevo gagal mengirim email', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    public function buildNewOrderCustomerPayload(Order $order): array
    {
        return [
            'sender' => $this->sender(),
            'to' => [
                ['email' => $order->customer_email, 'name' => $order->customer_name],
            ],
            'subject' => 'Pesanan Anda #'.$order->order_number,
            'htmlContent' => $this->render('emails.orders.new-order-customer', $order),
        ];
    }

    public function buildNewOrderAdminPayload(Order $order): array
    {
        return [
            'sender' => $this->sender(),
            'to' => [
                ['email' => (string) config('services.brevo.admin_email'), 'name' => 'Admin Jamu Mak Yuli'],
            ],
            'subject' => '[Pesanan Baru] #'.$order->order_number.', '.$order->customer_name,
            'htmlContent' => $this->render('emails.orders.new-order-admin', $order),
        ];
    }

    public function buildPaymentPaidPayload(Order $order): array
    {
        return [
            'sender' => $this->sender(),
            'to' => [
                ['email' => $order->customer_email, 'name' => $order->customer_name],
            ],
            'subject' => 'Pembayaran Diterima: Pesanan #'.$order->order_number,
            'htmlContent' => $this->render('emails.orders.payment-paid', $order),
        ];
    }

    public function buildPaymentPaidAdminPayload(Order $order): array
    {
        return [
            'sender' => $this->sender(),
            'to' => [
                ['email' => (string) config('services.brevo.admin_email'), 'name' => 'Admin Jamu Mak Yuli'],
            ],
            'subject' => '[Lunas] #'.$order->order_number.', '.$order->customer_name,
            'htmlContent' => $this->render('emails.orders.payment-paid-admin', $order),
        ];
    }

    public function buildOrderShippedPayload(Order $order): array
    {
        return [
            'sender' => $this->sender(),
            'to' => [
                ['email' => $order->customer_email, 'name' => $order->customer_name],
            ],
            'subject' => 'Pesanan Dikirim: #'.$order->order_number,
            'htmlContent' => $this->render('emails.orders.order-shipped', $order),
        ];
    }

    public function buildOrderDeliveredPayload(Order $order): array
    {
        return [
            'sender' => $this->sender(),
            'to' => [
                ['email' => $order->customer_email, 'name' => $order->customer_name],
            ],
            'subject' => 'Pesanan Selesai: #'.$order->order_number,
            'htmlContent' => $this->render('emails.orders.order-delivered', $order),
        ];
    }

    public function buildPaymentAlertPayload(Order $order, string $reason): array
    {
        return [
            'sender' => $this->sender(),
            'to' => [
                ['email' => (string) config('services.brevo.admin_email'), 'name' => 'Admin Jamu Mak Yuli'],
            ],
            'subject' => 'Perlu review pembayaran #'.$order->order_number,
            'htmlContent' => $this->render('emails.orders.payment-alert', $order, ['reason' => $reason]),
        ];
    }

    private function render(string $view, Order $order, array $extra = []): string
    {
        $order->loadMissing('items.product', 'items.variant');

        return view($view, ['order' => $order, ...$extra])->render();
    }

    private function sender(): array
    {
        return [
            'name' => (string) config('services.brevo.sender_name'),
            'email' => (string) config('services.brevo.sender_email'),
        ];
    }
}
