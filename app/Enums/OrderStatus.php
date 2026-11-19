<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'menunggu_pembayaran';
    case PAID = 'lunas';
    case SHIPPED = 'dikirim';
    case DELIVERED = 'selesai';
    case CANCELLED = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Pembayaran',
            self::PAID => 'Diproses',
            self::SHIPPED => 'Dikirim',
            self::DELIVERED => 'Pesanan Diterima',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'amber',
            self::PAID => 'emerald',
            self::SHIPPED => 'sky',
            self::DELIVERED => 'teal',
            self::CANCELLED => 'rose',
        };
    }

    public static function berpenghasilan(): array
    {
        return [self::PAID, self::SHIPPED, self::DELIVERED];
    }
}
