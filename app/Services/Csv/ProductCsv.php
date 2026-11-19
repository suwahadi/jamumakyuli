<?php

declare(strict_types=1);

namespace App\Services\Csv;

use App\Models\Category;
use App\Models\Product;
use App\Models\Variant;

class ProductCsv implements CsvSchema
{
    private array $skuBaruDiBerkas = [];

    private ?array $kategori = null;

    private ?array $petaSku = null;

    private ?array $produkTersimpan = null;

    private ?array $varianTersimpan = null;

    public function columns(): array
    {
        return ['sku', 'variant_name', 'variant_sku', 'name', 'category_slug', 'original_price', 'promo_price', 'stock', 'weight', 'is_active', 'description'];
    }

    public function rows(): iterable
    {
        return Product::query()
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('variants', 'variants.product_id', '=', 'products.id')
            ->orderBy('products.sku')
            ->orderBy('variants.id')
            ->select([
                'products.sku',
                'variants.name as variant_name',
                'variants.sku as variant_sku',
                'variants.price as variant_price',
                'variants.promo_price as variant_promo',
                'variants.stock as variant_stock',
                'variants.weight as variant_weight',
                'products.name',
                'categories.slug as category_slug',
                'products.original_price',
                'products.promo_price',
                'products.stock',
                'products.weight',
                'products.is_active',
                'products.description',
            ])
            ->cursor()
            ->map(function (Product $product): array {
                $bervarian = $product->variant_name !== null;

                return [
                    $product->sku,
                    $product->variant_name,
                    $product->variant_sku,
                    $product->name,
                    $product->category_slug,
                    $bervarian ? $product->variant_price : $product->original_price,
                    $bervarian ? $product->variant_promo : $product->promo_price,
                    $bervarian ? $product->variant_stock : $product->stock,
                    $bervarian ? $product->variant_weight : $product->weight,
                    (int) $product->is_active,
                    $product->description,
                ];
            });
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:100'],
            'variant_name' => ['nullable', 'string', 'max:255', 'required_with:variant_sku'],
            'variant_sku' => ['nullable', 'string', 'max:100', 'required_with:variant_name'],
            'name' => ['required', 'string', 'max:255'],
            'category_slug' => ['required', 'string', function (string $atribut, mixed $nilai, \Closure $gagal): void {
                if (! isset($this->kategori()[self::normalSlug((string) $nilai)])) {
                    $gagal('validation.exists')->translate();
                }
            }],
            'original_price' => ['required', 'integer', 'min:0'],
            'promo_price' => ['nullable', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'weight' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function matchKey(): string
    {
        return 'sku';
    }

    public function nilaiKunci(array $data): string
    {
        $sku = (string) ($data['sku'] ?? '');

        if (blank($data['variant_name'] ?? null)) {
            return $sku;
        }

        return implode(' / ', array_map('trim', [$sku, (string) $data['variant_sku'], (string) $data['variant_name']]));
    }

    public function galatBaris(array $data): ?string
    {
        $peta = $this->petaSku();
        $induk = self::normalSku((string) $data['sku']);
        $produkId = $peta['produk'][$induk] ?? null;
        $milikProduk = $produkId === null ? [] : $peta['milik'][$produkId];

        foreach (array_filter([$data['sku'], $data['variant_sku'] ?? null], 'filled') as $sku) {
            $sku = (string) $sku;
            $kunci = self::normalSku($sku);

            if (isset($milikProduk[$kunci])) {
                continue;
            }

            $pemakai = collect($peta['pemakai'][$kunci] ?? [])->first(fn (array $p): bool => $p['id'] !== $produkId);

            if ($pemakai) {
                return "SKU {$sku} sudah dipakai produk \"{$pemakai['name']}\"";
            }

            if (($this->skuBaruDiBerkas[$kunci] ??= $induk) !== $induk) {
                return "SKU {$sku} dipakai dua produk dalam berkas";
            }
        }

        return null;
    }

    private function petaSku(): array
    {
        if ($this->petaSku !== null) {
            return $this->petaSku;
        }

        $peta = ['produk' => [], 'milik' => [], 'pemakai' => []];
        $nama = [];

        foreach (Product::query()->toBase()->get(['id', 'sku', 'name']) as $produk) {
            $sku = self::normalSku((string) $produk->sku);
            $peta['produk'][$sku] = (int) $produk->id;
            $peta['milik'][(int) $produk->id][$sku] = true;
            $peta['pemakai'][$sku][] = ['id' => (int) $produk->id, 'name' => (string) $produk->name];
            $nama[(int) $produk->id] = (string) $produk->name;
        }

        foreach (Variant::query()->toBase()->get(['product_id', 'sku']) as $varian) {
            $id = (int) $varian->product_id;

            if (! isset($nama[$id])) {
                continue;
            }

            $sku = self::normalSku((string) $varian->sku);
            $peta['milik'][$id][$sku] = true;
            $peta['pemakai'][$sku][] = ['id' => $id, 'name' => $nama[$id]];
        }

        return $this->petaSku = $peta;
    }

    private function kategori(): array
    {
        return $this->kategori ??= Category::query()->pluck('id', 'slug')
            ->mapWithKeys(fn (int $id, string $slug): array => [self::normalSlug($slug) => $id])
            ->all();
    }

    private static function normalSku(string $sku): string
    {
        return mb_strtoupper(trim($sku));
    }

    private static function normalSlug(string $slug): string
    {
        return mb_strtolower(trim($slug));
    }

    public function upsert(array $row): void
    {
        $kunciProduk = self::normalSku((string) $row['sku']);
        $existing = $this->produkTersimpan()[$kunciProduk] ?? null;
        $bervarian = filled($row['variant_name']);
        $nilaiBaris = [
            'original_price' => (int) $row['original_price'],
            'promo_price' => filled($row['promo_price']) ? (int) $row['promo_price'] : null,
            'stock' => (int) $row['stock'],
            'weight' => (int) $row['weight'],
        ];

        $isian = [
            'name' => $row['name'],
            'slug' => $existing && $existing->name === $row['name']
                ? $existing->slug
                : Product::uniqueSlug((string) $row['name'], $existing?->id),
            'category_id' => $this->kategori()[self::normalSlug((string) $row['category_slug'])] ?? null,
            'is_active' => (bool) $row['is_active'],
            'description' => (string) $row['description'],
        ] + ($bervarian && $existing ? [] : $nilaiBaris);

        if ($existing) {
            $produk = $existing->fill($isian);
            $produk->save();
        } else {
            $produk = $this->produkTersimpan[$kunciProduk] = Product::query()->create(['sku' => $row['sku']] + $isian);
        }

        if ($bervarian) {
            $kunciVarian = self::kunciVarian($produk->id, (string) $row['variant_sku'], (string) $row['variant_name']);
            $nilaiVarian = [
                'price' => $nilaiBaris['original_price'],
                'promo_price' => $nilaiBaris['promo_price'],
                'stock' => $nilaiBaris['stock'],
                'weight' => $nilaiBaris['weight'],
            ];
            $varian = $this->varianTersimpan()[$kunciVarian] ?? null;

            if ($varian) {
                $varian->fill($nilaiVarian)->save();
            } else {
                $this->varianTersimpan[$kunciVarian] = Variant::query()->create(
                    ['product_id' => $produk->id, 'sku' => $row['variant_sku'], 'name' => $row['variant_name']] + $nilaiVarian,
                );
            }
        }
    }

    private function produkTersimpan(): array
    {
        if ($this->produkTersimpan === null) {
            $this->produkTersimpan = [];

            foreach (Product::query()->orderBy('id')->get() as $produk) {
                $this->produkTersimpan[self::normalSku((string) $produk->sku)] ??= $produk;
            }
        }

        return $this->produkTersimpan;
    }

    private function varianTersimpan(): array
    {
        if ($this->varianTersimpan === null) {
            $this->varianTersimpan = [];

            foreach (Variant::query()->orderBy('id')->get() as $varian) {
                $this->varianTersimpan[self::kunciVarian($varian->product_id, (string) $varian->sku, (string) $varian->name)] ??= $varian;
            }
        }

        return $this->varianTersimpan;
    }

    private static function kunciVarian(int $produkId, string $sku, string $nama): string
    {
        return $produkId.'|'.self::normalSku($sku).'|'.mb_strtoupper(trim($nama));
    }

    public function label(): string
    {
        return 'Produk';
    }
}
