<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    public const AMBANG_STOK_MENIPIS = 5;

    public const MAKS_KATA_CARI = 8;

    public const MAKS_PANJANG_CARI = 100;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'sku',
        'original_price',
        'promo_price',
        'description',
        'weight',
        'stock',
        'is_active',
        'custom_text_enabled',
    ];

    protected function casts(): array
    {
        return [
            'original_price' => 'integer',
            'promo_price' => 'integer',
            'weight' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'custom_text_enabled' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function mainImage(): ?ProductImage
    {
        $images = $this->relationLoaded('images') ? $this->images : $this->images()->get();

        return $images->firstWhere('is_main', true) ?? $images->first();
    }

    public function thumbnailUrl(): ?string
    {
        return $this->mainImage()?->url;
    }

    protected function effectivePrice(): Attribute
    {
        return Attribute::get(
            fn (): int => $this->promo_price ?? $this->original_price,
        );
    }

    public function isOnPromo(): bool
    {
        if ($this->hasVariants()) {
            return $this->variants->contains(fn (Variant $v): bool => $v->isOnPromo());
        }

        return $this->promo_price !== null && $this->promo_price < $this->original_price;
    }

    public function hasVariants(): bool
    {
        return $this->relationLoaded('variants')
            ? $this->variants->isNotEmpty()
            : $this->variants()->exists();
    }

    public function fromPrice(): int
    {
        if ($this->hasVariants()) {
            return (int) $this->variants->min(fn (Variant $v): int => $v->effective_price);
        }

        return $this->effective_price;
    }

    public static function effectivePriceSql(): string
    {
        return '(COALESCE('
            .'(SELECT MIN(COALESCE(variants.promo_price, variants.price)) '
            .'FROM variants WHERE variants.product_id = products.id), '
            .'COALESCE(products.promo_price, products.original_price)))';
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (self::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    public function totalStock(): int
    {
        if ($this->hasVariants()) {
            return (int) $this->variants->sum('stock');
        }

        return $this->stock;
    }

    public function stokTerendah(): int
    {
        return $this->hasVariants() ? (int) $this->variants->min('stock') : $this->stock;
    }

    public function isSoldOut(): bool
    {
        return $this->totalStock() <= 0;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public static function kataKunci(?string $kata): array
    {
        $kata = mb_strtolower(mb_substr(trim((string) $kata), 0, self::MAKS_PANJANG_CARI));
        $kata = str_replace('-', '', $kata);
        $potongan = preg_split('/[^\p{L}\p{N}%_\\\\.]+/u', $kata, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $hasil = [];

        foreach ($potongan as $kepingan) {
            $bersih = trim($kepingan, '.');

            if ($bersih !== '') {
                $hasil[] = addcslashes($bersih, '\\%_');
            }
        }

        return array_slice(array_values(array_unique($hasil)), 0, self::MAKS_KATA_CARI);
    }

    public function scopeCari(Builder $query, ?string $kata): void
    {
        foreach (self::kataKunci($kata) as $kunci) {
            $pola = '%'.$kunci.'%';

            $query->where(fn (Builder $w) => $w
                ->whereRaw("REPLACE(name, '-', '') LIKE ?", [$pola])
                ->orWhereRaw("REPLACE(sku, '-', '') LIKE ?", [$pola])
                ->orWhereHas('variants', fn (Builder $v) => $v->whereRaw("REPLACE(sku, '-', '') LIKE ?", [$pola])));
        }
    }

    public function scopeStokMenipis(Builder $query, int $ambang = self::AMBANG_STOK_MENIPIS): void
    {
        $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $induk) => $induk->whereDoesntHave('variants')->where('stock', '<=', $ambang))
            ->orWhereHas('variants', fn (Builder $varian) => $varian->where('stock', '<=', $ambang)));
    }
}
