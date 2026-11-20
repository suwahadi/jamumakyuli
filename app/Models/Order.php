<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Services\ShippingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'order_number',
        'idempotency_key',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_province_id',
        'shipping_city_id',
        'shipping_district_id',
        'shipping_destination_label',
        'shipping_address',
        'shipping_courier',
        'shipping_courier_name',
        'shipping_service',
        'shipping_service_label',
        'shipping_etd',
        'tracking_number',
        'shipping_cost',
        'total_weight',
        'subtotal',
        'grand_total',
        'status',
        'active_payment_attempt_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'shipping_cost' => 'integer',
            'total_weight' => 'integer',
            'subtotal' => 'integer',
            'grand_total' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    private const HALAMAN_LACAK = [
        'jne' => 'https://www.jne.co.id/tracking-package',
        'jnt' => 'https://jet.co.id/track',
    ];

    public function trackingUrl(): ?string
    {
        return filled($this->tracking_number) ? (self::HALAMAN_LACAK[$this->shipping_courier] ?? null) : null;
    }

    public function beratTotalGram(): int
    {
        return $this->total_weight
            ?? (int) $this->items->sum(fn (OrderItem $item): int => (int) ($item->variant?->weight ?? $item->product?->weight ?? 0) * $item->quantity);
    }

    public function shippingCourierName(): string
    {
        $courier = ShippingService::COURIERS[$this->shipping_courier]
            ?? ($this->shipping_courier_name ?: mb_strtoupper((string) $this->shipping_courier));

        return trim($courier.' '.(string) $this->shipping_service);
    }

    public function shippingServiceDetail(): string
    {
        $parts = array_filter([
            (string) $this->shipping_courier_name,
            (string) $this->shipping_service_label,
            rentang_etd($this->shipping_etd) !== '' ? 'Estimasi tiba '.rentang_etd($this->shipping_etd).' hari' : '',
        ]);

        return implode(' · ', $parts);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function paymentMethodLabel(): string
    {
        $attempts = $this->paymentAttempts->sortByDesc('id');
        $attempt = $attempts->firstWhere('status', PaymentAttemptStatus::PAID) ?? $attempts->first();

        return $attempt?->methodLabel() ?? ($attempt !== null ? 'Midtrans' : '-');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(OrderActivity::class)->orderBy('created_at')->orderBy('id');
    }

    public function activePaymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class, 'active_payment_attempt_id');
    }
}
