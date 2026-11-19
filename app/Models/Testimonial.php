<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Testimonial extends Model
{
    public const MAX_ITEMS = 12;

    protected $fillable = [
        'name',
        'role',
        'rating',
        'content',
        'image',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Testimonial $testimonial): void {
            if (filled($testimonial->image)) {
                delete_webp($testimonial->image);
            }
        });
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => filled($this->image) ? Storage::disk('public')->url($this->image) : null,
        );
    }
}
