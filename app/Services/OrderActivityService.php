<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderActivityType;
use App\Jobs\SendBrevoEmail;
use App\Models\Order;
use App\Models\OrderActivity;

class OrderActivityService
{
    private const METHOD_LABELS = [
        'bni_va' => 'Virtual Account BNI',
        'bri_va' => 'Virtual Account BRI',
        'bca_va' => 'Virtual Account BCA',
        'permata_va' => 'Virtual Account Permata',
        'gopay' => 'GoPay',
        'qris' => 'QRIS',
    ];

    public function __construct(
        private readonly WebNotificationService $web,
    ) {}

    public function created(Order $order): void
    {
        $this->log(
            $order,
            OrderActivityType::CREATED,
            $order->customer_name,
            'Pesanan dibuat oleh '.$order->customer_name.'.',
        );

        SendBrevoEmail::dispatch($order, 'new_order');

        $this->web->orderCreated($order);
    }

    public function paymentStarted(Order $order, ?string $method = null): void
    {
        $label = $method === null ? 'Midtrans' : (self::METHOD_LABELS[$method] ?? $method);

        $this->log(
            $order,
            OrderActivityType::PAYMENT_STARTED,
            'Pelanggan',
            'Memulai pembayaran via '.$label.'.',
            ['method' => $method],
        );
    }

    public function paid(Order $order, ?string $actor = null): void
    {
        $this->log(
            $order,
            OrderActivityType::PAID,
            $actor ?? 'Sistem',
            'Pembayaran diterima.',
        );

        SendBrevoEmail::dispatch($order, 'payment_paid');

        $this->web->orderPaid($order);
    }

    public function shipped(Order $order, ?string $actor = null): void
    {
        $this->log(
            $order,
            OrderActivityType::SHIPPED,
            $actor ?? 'Sistem',
            'Pesanan ditandai telah dikirim.',
        );

        SendBrevoEmail::dispatch($order, 'order_shipped');
    }

    public function resiDiperbarui(Order $order, ?string $actor = null): void
    {
        $this->log(
            $order,
            OrderActivityType::SHIPPED,
            $actor ?? 'Sistem',
            'Nomor resi diperbarui menjadi '.$order->tracking_number.'.',
        );

        SendBrevoEmail::dispatch($order, 'order_shipped');
    }

    public function delivered(Order $order, ?string $actor = null): void
    {
        $this->log(
            $order,
            OrderActivityType::DELIVERED,
            $actor ?? 'Sistem',
            'Pesanan ditandai diterima.',
        );

        SendBrevoEmail::dispatch($order, 'order_delivered');
    }

    public function cancelled(Order $order, ?string $actor = null): void
    {
        $this->log(
            $order,
            OrderActivityType::CANCELLED,
            $actor ?? 'Sistem',
            'Pesanan dibatalkan; stok dikembalikan.',
        );
    }

    public function paymentFailed(Order $order, string $status, ?string $actor = null): void
    {
        $this->log(
            $order,
            OrderActivityType::PAYMENT_FAILED,
            $actor ?? 'Midtrans (otomatis)',
            'Pembayaran gagal/kedaluwarsa ('.$status.').',
            ['transaction_status' => $status],
        );
    }

    private function log(Order $order, OrderActivityType $type, ?string $actor, string $description, array $meta = []): OrderActivity
    {
        return $order->activities()->create([
            'type' => $type,
            'description' => $description,
            'actor' => $actor,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
