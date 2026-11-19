<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AboutImage extends Model
{
    public const MAX_ITEMS = 5;

    protected $fillable = [
        'image',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (AboutImage $item): void {
            delete_webp($item->image);
        });
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(
            fn (): string => Storage::disk('public')->url($this->image),
        );
    }

    public function scopeTampil(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id')->limit(self::MAX_ITEMS);
    }
}
