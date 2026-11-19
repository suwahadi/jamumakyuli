<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

class WhatsAppMessage
{
    public static function general(): string
    {
        return 'Halo '.self::brand().', saya tertarik dengan Ramuan Rempah Kering Promil Pasutri. Mohon info lebih lanjut.';
    }

    public static function productInquiry(Product $product): string
    {
        return 'Halo '.self::brand().', saya tertarik dengan '.$product->name.'. Mohon info lebih lanjut.';
    }

    public static function orderConfirmation(Order $order): string
    {
        $order->loadMissing('items.product', 'items.variant');

        $lines = [
            'Halo '.self::brand().', saya ingin konfirmasi pesanan:',
            'No. Pesanan: '.$order->order_number,
            'Nama: '.$order->customer_name,
        ];

        foreach ($order->items as $item) {
            $lines[] = self::itemLine($item);
        }

        $lines[] = 'Ongkir ('.$order->shippingCourierName().'): '.Format::rupiah((int) $order->shipping_cost);
        $lines[] = 'Total: '.Format::rupiah((int) $order->grand_total);
        $lines[] = 'Status: '.$order->status->label();

        return implode("\n", $lines);
    }

    private static function itemLine(OrderItem $item): string
    {
        $variant = $item->namaVarian();

        return $item->quantity.'x '.$item->product?->name.($variant ? ' ('.$variant.')' : '');
    }

    private static function brand(): string
    {
        return (string) setting('site_name', 'Jamu Mak Yuli');
    }
}
