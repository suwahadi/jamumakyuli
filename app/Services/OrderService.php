<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Product;
use App\Models\Variant;
use App\Services\Payments\Midtrans\MidtransClient;
use App\Support\ImageWebp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private readonly OrderActivityService $activities,
        private readonly ShippingService $shippingService,
        private readonly MidtransClient $midtrans,
    ) {}

    public function checkout(array $payload, string $idempotencyKey): Order
    {
        $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $lock = Cache::lock('checkout:'.$idempotencyKey, 15);

        return $lock->block(10, function () use ($payload, $idempotencyKey): Order {
            $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }

            return DB::transaction(function () use ($payload, $idempotencyKey): Order {
                return $this->persistOrder($payload, $idempotencyKey);
            });
        });
    }

    private function persistOrder(array $payload, string $idempotencyKey): Order
    {
        $items = $payload['items'] ?? [];

        if ($items === []) {
            throw new BusinessRuleException('Keranjang belanja kosong.');
        }

        $subtotal = 0;
        $totalWeight = 0;
        $lineRows = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $variantId = isset($item['variant_id']) ? (int) $item['variant_id'] : null;
            $quantity = (int) $item['quantity'];

            if ($quantity < 1) {
                throw new BusinessRuleException('Jumlah barang tidak valid.');
            }

            $product = Product::query()->find($productId);

            if ($product === null || ! $product->is_active) {
                throw new BusinessRuleException('Produk tidak tersedia.');
            }

            if ($variantId !== null) {
                $sellable = Variant::query()
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->find($variantId);

                if ($sellable === null) {
                    throw new BusinessRuleException('Varian tidak tersedia.');
                }

            } else {
                if ($product->hasVariants()) {
                    throw new BusinessRuleException('Silakan pilih varian untuk '.$product->name.'.');
                }

                $sellable = Product::query()->lockForUpdate()->find($productId);
            }

            $price = $sellable->effective_price;
            $lineTotal = $price * $quantity;
            $subtotal += $lineTotal;
            $totalWeight += $sellable->weight * $quantity;

            if ($sellable->stock < $quantity) {
                $label = $variantId !== null ? $product->name.' - '.$sellable->name : $product->name;

                throw BusinessRuleException::insufficientStock($label, (int) $sellable->stock);
            }

            $sellable->decrement('stock', $quantity);

            $customText = isset($item['custom_text']) ? trim((string) $item['custom_text']) : '';

            $lineRows[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'sku' => $sellable->sku,
                'variant_name' => $variantId === null ? null : $sellable->name,
                'price' => $price,
                'quantity' => $quantity,
                'custom_text' => $customText === '' ? null : $customText,
                'total' => $lineTotal,
            ];
        }

        $shipping = $payload['shipping'] ?? [];

        $quote = $this->shippingService->quote(
            (int) ($shipping['destination_id'] ?? $shipping['district_id'] ?? 0),
            $totalWeight,
            (string) ($shipping['courier'] ?? ''),
            $shipping['service'] ?? null,
        );

        $shippingCost = $quote['cost'];
        $grandTotal = $subtotal + $shippingCost;

        $customer = $payload['customer'] ?? [];

        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_number' => $this->generateOrderNumber(),
            'idempotency_key' => $idempotencyKey,
            'user_id' => auth()->id(),
            'customer_name' => (string) ($customer['name'] ?? ''),
            'customer_email' => (string) ($customer['email'] ?? ''),
            'customer_phone' => (string) ($customer['phone'] ?? ''),
            'shipping_province_id' => isset($shipping['province_id']) ? (int) $shipping['province_id'] : null,
            'shipping_city_id' => isset($shipping['city_id']) ? (int) $shipping['city_id'] : null,
            'shipping_district_id' => (int) ($shipping['destination_id'] ?? $shipping['district_id'] ?? 0),
            'shipping_destination_label' => $shipping['destination_label'] ?? null,
            'shipping_address' => (string) ($shipping['address'] ?? ''),
            'shipping_courier' => $quote['courier'],
            'shipping_courier_name' => $quote['courier_name'],
            'shipping_service' => $quote['service'],
            'shipping_service_label' => $quote['service_label'],
            'shipping_etd' => $quote['etd'],
            'shipping_cost' => $shippingCost,
            'total_weight' => $totalWeight,
            'subtotal' => $subtotal,
            'grand_total' => $grandTotal,
            'status' => OrderStatus::PENDING,
        ]);

        $this->persistLineItems($order, $lineRows);

        $this->activities->created($order);

        return $order;
    }

    private function persistLineItems(Order $order, array $lineRows): void
    {
        foreach ($lineRows as $row) {
            $order->items()->create($row);
        }
    }

    public function markAsPaid(Order $order, ?string $actor = null): Order
    {
        if ($order->status === OrderStatus::PAID) {
            return $order;
        }

        $closed = DB::transaction(function () use ($order): array {
            $order->update([
                'status' => OrderStatus::PAID,
                'paid_at' => $order->paid_at ?? now(),
            ]);

            return $this->closeOpenAttempts($order);
        });

        $this->cancelAtMidtrans($closed);

        $this->activities->paid($order, $actor);

        return $order;
    }

    public function markAsShipped(Order $order, ?string $actor = null, ?string $trackingNumber = null): Order
    {
        if ($order->status === OrderStatus::SHIPPED) {
            return $order;
        }

        if ($order->status !== OrderStatus::PAID) {
            throw new BusinessRuleException('Hanya pesanan yang sudah lunas yang dapat dikirim.');
        }

        $order->update([
            'status' => OrderStatus::SHIPPED,
            'tracking_number' => $trackingNumber,
        ]);

        $this->activities->shipped($order, $actor);

        return $order;
    }

    public function simpanBuktiKirim(Order $order, UploadedFile $foto): Order
    {
        if (! in_array($order->status, [OrderStatus::SHIPPED, OrderStatus::DELIVERED], true)) {
            throw new BusinessRuleException('Bukti kirim hanya untuk pesanan yang sudah dikirim.');
        }

        $lama = $order->shipping_proof_path;
        $order->update(['shipping_proof_path' => ImageWebp::store($foto, 'bukti-kirim', 2000, 80, 'local')]);
        ImageWebp::delete($lama, 'local');

        return $order;
    }

    public function markAsDelivered(Order $order, ?string $actor = null): Order
    {
        if ($order->status === OrderStatus::DELIVERED) {
            return $order;
        }

        if ($order->status !== OrderStatus::SHIPPED) {
            throw new BusinessRuleException('Hanya pesanan berstatus Dikirim yang dapat ditandai diterima.');
        }

        $order->update(['status' => OrderStatus::DELIVERED]);

        $this->activities->delivered($order, $actor);

        return $order;
    }

    public function updateTracking(Order $order, string $trackingNumber, ?string $actor = null): Order
    {
        $trackingNumber = trim($trackingNumber);

        if ($trackingNumber === (string) $order->tracking_number) {
            return $order;
        }

        $order->update(['tracking_number' => $trackingNumber]);

        $this->activities->resiDiperbarui($order, $actor);

        return $order;
    }

    public function cancel(Order $order, ?string $actor = null, bool $onlyUnpaid = false): Order
    {
        $closed = DB::transaction(function () use ($order, $actor, $onlyUnpaid): array {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === OrderStatus::CANCELLED) {
                return [];
            }

            if ($onlyUnpaid && $locked->status !== OrderStatus::PENDING) {
                return [];
            }

            if ($locked->status === OrderStatus::SHIPPED) {
                throw new BusinessRuleException('Pesanan yang sudah dikirim tidak dapat dibatalkan.');
            }

            foreach ($locked->items()->get() as $item) {
                if ($item->variant_id !== null) {
                    $variant = Variant::query()->lockForUpdate()->find($item->variant_id);
                    $variant?->increment('stock', $item->quantity);
                } else {
                    $product = Product::query()->lockForUpdate()->find($item->product_id);
                    $product?->increment('stock', $item->quantity);
                }
            }

            $locked->update(['status' => OrderStatus::CANCELLED]);

            $ids = $this->closeOpenAttempts($locked);

            $this->activities->cancelled($locked, $actor);

            return $ids;
        });

        $this->cancelAtMidtrans($closed);

        return $order->refresh();
    }

    private function closeOpenAttempts(Order $order): array
    {
        $ids = [];

        foreach ($order->paymentAttempts()->get() as $attempt) {
            if (! $attempt->isOpen()) {
                continue;
            }

            $attempt->update(['status' => PaymentAttemptStatus::SUPERSEDED]);

            if (filled($attempt->midtrans_order_id)) {
                $ids[] = (string) $attempt->midtrans_order_id;
            }
        }

        return $ids;
    }

    private function cancelAtMidtrans(array $midtransOrderIds): void
    {
        foreach ($midtransOrderIds as $id) {
            rescue(fn () => $this->midtrans->cancel($id), report: false);
        }
    }

    private function generateOrderNumber(): string
    {
        return 'JMY-'.strtoupper(Str::random(10));
    }
}
