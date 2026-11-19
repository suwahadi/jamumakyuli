<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case ORDER_CREATED = 'order_created';
    case ORDER_PAID = 'order_paid';
    case PAYMENT_ALERT = 'payment_alert';

    public function label(): string
    {
        return match ($this) {
            self::ORDER_CREATED => 'Pesanan Baru',
            self::ORDER_PAID => 'Pembayaran Diterima',
            self::PAYMENT_ALERT => 'Peringatan Pembayaran',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ORDER_CREATED => 'bell',
            self::ORDER_PAID => 'check-circle',
            self::PAYMENT_ALERT => 'exclamation-triangle',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ORDER_CREATED => 'amber',
            self::ORDER_PAID => 'emerald',
            self::PAYMENT_ALERT => 'red',
        };
    }
}
