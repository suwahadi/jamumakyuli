<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

class CancelUnpaidOrders extends Command
{
    protected $signature = 'orders:cancel-unpaid';

    protected $description = 'Batalkan pesanan yang masih menunggu pembayaran dan telah melewati batas waktu (expiry_order).';

    public function handle(OrderService $orders): int
    {
        $threshold = now()->subMinutes(order_expiry_minutes());

        $expired = Order::query()
            ->where('status', OrderStatus::PENDING->value)
            ->where('created_at', '<', $threshold)
            ->get();

        if ($expired->isEmpty()) {
            $this->info('Tidak ada pesanan kedaluwarsa untuk dibatalkan.');

            return self::SUCCESS;
        }

        $cancelled = 0;

        foreach ($expired as $order) {
            try {
                $orders->cancel($order, 'Sistem (kedaluwarsa)', onlyUnpaid: true);
                $cancelled++;
            } catch (BusinessRuleException $e) {
                $this->warn("Lewati {$order->order_number}: {$e->getMessage()}");
            }
        }

        $this->info("Selesai: {$cancelled} pesanan dibatalkan.");

        return self::SUCCESS;
    }
}
