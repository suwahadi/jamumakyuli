<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Product;
use App\Models\Variant;

final class AturanSku
{
    public static function pemakaiLain(string $sku, ?int $produkId): ?Product
    {
        $sku = trim($sku);
        $produkBervarian = Variant::query()->where('sku', $sku)->pluck('product_id');

        return Product::query()
            ->where(fn ($q) => $q->where('sku', $sku)->orWhereIn('id', $produkBervarian))
            ->when($produkId, fn ($q) => $q->whereKeyNot($produkId))
            ->first();
    }
}
