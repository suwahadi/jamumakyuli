<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class CartService
{
    private const KEY = 'cart';

    public function add(int $productId, int $quantity = 1, ?int $variantId = null, ?string $customText = null): void
    {
        if ($quantity < 1) {
            throw new BusinessRuleException('Jumlah barang minimal 1.');
        }

        $product = Product::query()->with('variants')->find($productId);

        if ($product === null || ! $product->is_active) {
            throw new BusinessRuleException('Produk tidak tersedia.');
        }

        $customText = $this->resolveCustomText($product, $customText);

        [$variantId, $sellable, $label] = $this->resolveSellable($product, $variantId);

        $cart = $this->raw();
        $key = $this->lineKey($productId, $variantId, $customText);
        $newQty = ($cart[$key]['quantity'] ?? 0) + $quantity;

        if ($newQty > $sellable->stock) {
            throw BusinessRuleException::insufficientStock($label, (int) $sellable->stock);
        }

        $cart[$key] = [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $newQty,
            'custom_text' => $customText,
        ];

        $this->save($cart);
    }

    private function resolveCustomText(Product $product, ?string $customText): ?string
    {
        if (! $product->custom_text_enabled) {
            return null;
        }

        $customText = trim((string) $customText);

        if ($customText === '') {
            throw new BusinessRuleException('Mohon isi catatan custom terlebih dahulu.');
        }

        return Str::limit($customText, 60, '');
    }

    private function resolveSellable(Product $product, ?int $variantId): array
    {
        if ($product->hasVariants()) {
            if ($variantId === null) {
                throw new BusinessRuleException('Silakan pilih varian terlebih dahulu.');
            }

            $variant = $product->variants->firstWhere('id', $variantId);

            if ($variant === null) {
                throw new BusinessRuleException('Varian yang dipilih tidak tersedia.');
            }

            return [$variant->id, $variant, $product->name.' - '.$variant->name];
        }

        return [null, $product, $product->name];
    }

    public function update(string $lineKey, int $quantity): void
    {
        $cart = $this->raw();

        if (! isset($cart[$lineKey])) {
            return;
        }

        if ($quantity <= 0) {
            $this->remove($lineKey);

            return;
        }

        $product = Product::query()->with('variants')->find($cart[$lineKey]['product_id']);

        $variantId = $cart[$lineKey]['variant_id'];
        $sellable = $variantId !== null
            ? $product?->variants->firstWhere('id', $variantId)
            : $product;

        if ($sellable === null) {
            throw new BusinessRuleException('Produk tidak tersedia.');
        }

        if ($quantity > $sellable->stock && $quantity > (int) $cart[$lineKey]['quantity']) {
            $label = $variantId !== null ? $product->name.' - '.$sellable->name : $product->name;

            throw BusinessRuleException::insufficientStock($label, (int) $sellable->stock);
        }

        $cart[$lineKey]['quantity'] = $quantity;
        $this->save($cart);
    }

    public function remove(string $lineKey): void
    {
        $cart = $this->raw();
        unset($cart[$lineKey]);
        $this->save($cart);
    }

    public function clear(): void
    {
        Session::forget(self::KEY);
    }

    public function items(): Collection
    {
        $cart = $this->raw();

        if ($cart === []) {
            return collect();
        }

        $products = Product::query()
            ->with('variants.image', 'images')
            ->whereIn('id', collect($cart)->pluck('product_id')->unique())
            ->get()
            ->keyBy('id');

        return collect($cart)
            ->map(function (array $line, string $key) use ($products): ?array {
                $product = $products->get($line['product_id']);

                if ($product === null || ! $product->is_active) {
                    return null;
                }

                $variant = $line['variant_id'] !== null
                    ? $product->variants->firstWhere('id', $line['variant_id'])
                    : null;

                if (($line['variant_id'] !== null && $variant === null)
                    || ($product->hasVariants() && $variant === null)) {
                    return null;
                }

                $variant?->setRelation('product', $product);

                $sellable = $variant ?? $product;

                $price = $sellable->effective_price;

                return [
                    'key' => $key,
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $line['quantity'],
                    'price' => $price,
                    'line_total' => $price * $line['quantity'],
                    'weight' => $sellable->weight,
                    'stock' => (int) $sellable->stock,
                    'custom_text' => $line['custom_text'] ?? null,
                ];
            })
            ->filter()
            ->values();
    }

    public function count(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    public function isEmpty(): bool
    {
        return $this->items()->isEmpty();
    }

    public function subtotal(): int
    {
        return (int) $this->items()->sum('line_total');
    }

    public function totalWeight(): int
    {
        return (int) $this->items()->sum(
            fn (array $item): int => $item['weight'] * $item['quantity'],
        );
    }

    public function toCheckoutItems(): array
    {
        return $this->items()
            ->map(fn (array $item): array => [
                'product_id' => $item['product']->id,
                'variant_id' => $item['variant']?->id,
                'quantity' => $item['quantity'],
                'custom_text' => $item['custom_text'] ?? null,
            ])
            ->all();
    }

    public function lineKey(int $productId, ?int $variantId = null, ?string $customText = null): string
    {
        $key = $productId.':'.($variantId ?? 0);

        if ($customText !== null && $customText !== '') {
            $key .= ':c'.substr(md5($customText), 0, 8);
        }

        return $key;
    }

    private function raw(): array
    {
        return Session::get(self::KEY, []);
    }

    private function save(array $cart): void
    {
        Session::put(self::KEY, $cart);
    }
}
