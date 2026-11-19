<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    use HasFactory;

    public const PROMIL_SLUG = 'paket-promil-pasutri';

    public const HERBAL_SLUG = 'jamu-ramuan-rempah';

    protected $fillable = [
        'name',
        'slug',
        'thumbnail',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function thumbnailUrl(): ?string
    {
        return filled($this->thumbnail) ? Storage::disk('public')->url($this->thumbnail) : null;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
