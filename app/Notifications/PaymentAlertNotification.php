<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Order;
use Illuminate\Notifications\Notification;

final class PaymentAlertNotification extends Notification
{
    public function __construct(public readonly Order $order, public readonly string $reason) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => NotificationType::PAYMENT_ALERT->value,
            'order_number' => $this->order->order_number,
            'title' => 'Perlu review pembayaran '.$this->order->order_number,
            'message' => $this->reason,
        ];
    }
}
