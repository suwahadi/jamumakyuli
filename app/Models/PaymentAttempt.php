<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'attempt_sequence',
        'midtrans_order_id',
        'payment_method',
        'status',
        'gross_amount',
        'snap_token',
        'redirect_url',
        'midtrans_transaction_id',
        'midtrans_transaction_status',
        'midtrans_fraud_status',
        'snap_request_payload',
        'snap_response_payload',
        'latest_notification_payload',
        'activated_at',
        'paid_at',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentAttemptStatus::class,
            'attempt_sequence' => 'integer',
            'gross_amount' => 'integer',
            'snap_request_payload' => 'array',
            'snap_response_payload' => 'array',
            'latest_notification_payload' => 'array',
            'activated_at' => 'datetime',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isOpen(): bool
    {
        return $this->status instanceof PaymentAttemptStatus && $this->status->isOpen();
    }

    public function isExpired(): bool
    {
        return $this->expired_at !== null && $this->expired_at->isPast();
    }

    public function vaNumber(): ?string
    {
        $payload = $this->latest_notification_payload ?? [];

        $va = $payload['va_numbers'][0]['va_number'] ?? null;

        return $va ?? ($payload['permata_va_number'] ?? null);
    }

    public function bankLabel(): ?string
    {
        $payload = $this->latest_notification_payload ?? [];

        $bank = $payload['va_numbers'][0]['bank'] ?? null;

        if ($bank === null && isset($payload['permata_va_number'])) {
            $bank = 'permata';
        }

        return $bank !== null ? strtoupper((string) $bank) : null;
    }

    public function methodLabel(): ?string
    {
        $payload = $this->latest_notification_payload ?? [];
        $issuer = $payload['issuer'] ?? null;

        return match ($payload['payment_type'] ?? null) {
            'qris' => filled($issuer) ? 'QRIS ('.self::walletName((string) $issuer).')' : 'QRIS',
            'gopay', 'shopeepay' => self::walletName((string) $payload['payment_type']),
            'bank_transfer' => trim('Virtual Account '.self::bankName($this->bankLabel())),
            'echannel' => 'Mandiri Bill Payment',
            'credit_card' => 'Kartu Kredit',
            'cstore' => Str::headline((string) ($payload['store'] ?? 'Gerai Retail')),
            null => self::REQUESTED_METHOD_LABELS[$this->payment_method] ?? null,
            default => Str::headline((string) $payload['payment_type']),
        };
    }

    private const REQUESTED_METHOD_LABELS = [
        'bca_va' => 'Virtual Account BCA',
        'bni_va' => 'Virtual Account BNI',
        'bri_va' => 'Virtual Account BRI',
        'permata_va' => 'Virtual Account Permata',
        'gopay' => 'GoPay',
        'qris' => 'QRIS',
    ];

    private const WALLET_NAMES = [
        'gopay' => 'GoPay',
        'shopeepay' => 'ShopeePay',
        'dana' => 'DANA',
        'ovo' => 'OVO',
        'linkaja' => 'LinkAja',
    ];

    private static function walletName(string $code): string
    {
        return self::WALLET_NAMES[strtolower($code)] ?? Str::headline($code);
    }

    private static function bankName(?string $bank): string
    {
        return $bank === 'PERMATA' ? 'Permata' : (string) $bank;
    }

    public function billKey(): ?string
    {
        return $this->latest_notification_payload['bill_key'] ?? null;
    }

    public function billerCode(): ?string
    {
        return $this->latest_notification_payload['biller_code'] ?? null;
    }
}
