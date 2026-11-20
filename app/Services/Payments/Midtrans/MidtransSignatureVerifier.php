<?php

declare(strict_types=1);

namespace App\Services\Payments\Midtrans;

class MidtransSignatureVerifier
{
    public function isValid(array $payload): bool
    {
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $key) {
            if (! isset($payload[$key])) {
                return false;
            }
        }

        $signedText = $payload['order_id'].$payload['status_code'].$payload['gross_amount'];

        foreach ($this->acceptedKeys() as $key) {
            if (hash_equals(hash('sha512', $signedText.$key), (string) $payload['signature_key'])) {
                return true;
            }
        }

        return false;
    }

    private function acceptedKeys(): array
    {
        return array_values(array_filter([
            (string) config('services.midtrans.signature_secret_key'),
            (string) config('services.midtrans.server_key'),
        ], static fn (string $key): bool => $key !== ''));
    }
}
