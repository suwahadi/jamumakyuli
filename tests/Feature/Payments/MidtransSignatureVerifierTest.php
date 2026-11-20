<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Services\Payments\Midtrans\MidtransSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Midtrans sejak 28 Okt 2026: signature_key notifikasi dihitung dengan Signature Secret Key
// (Settings > Access Keys), bukan Server Key. Server Key tetap diterima sebagai cadangan
// untuk akun lama dan masa rotasi kunci. Sumber: https://docs.midtrans.com/docs/access-keys
class MidtransSignatureVerifierTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $key): array
    {
        $payload = [
            'order_id' => 'payment_notif_test_M322784249_uji',
            'status_code' => '200',
            'gross_amount' => '105000.00',
            'transaction_status' => 'settlement',
        ];

        return $payload + ['signature_key' => hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$key)];
    }

    private function verifier(?string $signatureSecret, ?string $serverKey): MidtransSignatureVerifier
    {
        config([
            'services.midtrans.signature_secret_key' => $signatureSecret,
            'services.midtrans.server_key' => $serverKey,
        ]);

        return app(MidtransSignatureVerifier::class);
    }

    public function test_signature_made_with_the_signature_secret_key_is_valid(): void
    {
        $this->assertTrue($this->verifier('rahasia-tanda-tangan', 'SB-Mid-server-lain')->isValid($this->payload('rahasia-tanda-tangan')));
    }

    public function test_signature_made_with_the_server_key_is_still_accepted(): void
    {
        $this->assertTrue($this->verifier('rahasia-tanda-tangan', 'SB-Mid-server-lain')->isValid($this->payload('SB-Mid-server-lain')));
    }

    public function test_signature_made_with_an_unknown_key_is_rejected(): void
    {
        $this->assertFalse($this->verifier('rahasia-tanda-tangan', 'SB-Mid-server-lain')->isValid($this->payload('kunci-penyerang')));
    }

    public function test_empty_keys_never_validate_a_signature_hashed_without_a_key(): void
    {
        $this->assertFalse($this->verifier('', '')->isValid($this->payload('')));
        $this->assertFalse($this->verifier(null, null)->isValid($this->payload('')));
    }

    public function test_dashboard_test_notification_signed_with_signature_secret_key_gets_200(): void
    {
        config([
            'services.midtrans.signature_secret_key' => 'rahasia-tanda-tangan',
            'services.midtrans.server_key' => 'SB-Mid-server-lain',
        ]);

        $this->postJson(route('payments.midtrans.notification'), $this->payload('rahasia-tanda-tangan'))
            ->assertOk();

        $this->assertDatabaseHas('payment_webhook_events', [
            'midtrans_order_id' => 'payment_notif_test_M322784249_uji',
            'processing_status' => 'ignored',
        ]);
    }
}
