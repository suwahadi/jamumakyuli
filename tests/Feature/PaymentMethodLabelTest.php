<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Uji order produksi 2026-11-20: dibayar dengan QRIS (issuer GoPay), tetapi invoice
// menulis "Transfer bank BCA" karena teksnya ditulis mati. Label kini diambil dari
// payment_type di notifikasi Midtrans terakhir.
class PaymentMethodLabelTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(?array $payload, ?string $requested = null, PaymentAttemptStatus $status = PaymentAttemptStatus::PAID, ?Order $order = null): PaymentAttempt
    {
        $order ??= Order::factory()->create();

        return PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'attempt_sequence' => PaymentAttempt::query()->where('order_id', $order->id)->count() + 1,
            'payment_method' => $requested,
            'latest_notification_payload' => $payload,
            'status' => $status,
        ]);
    }

    public function test_labels_follow_the_payment_type_midtrans_reported(): void
    {
        $this->assertSame('QRIS (GoPay)', $this->attempt(['payment_type' => 'qris', 'issuer' => 'gopay', 'acquirer' => 'gopay'])->methodLabel());
        $this->assertSame('QRIS (ShopeePay)', $this->attempt(['payment_type' => 'qris', 'issuer' => 'shopeepay'])->methodLabel());
        $this->assertSame('QRIS', $this->attempt(['payment_type' => 'qris'])->methodLabel());
        $this->assertSame('GoPay', $this->attempt(['payment_type' => 'gopay'])->methodLabel());
        $this->assertSame('Virtual Account BCA', $this->attempt(['payment_type' => 'bank_transfer', 'va_numbers' => [['bank' => 'bca', 'va_number' => '12345']]])->methodLabel());
        $this->assertSame('Virtual Account Permata', $this->attempt(['payment_type' => 'bank_transfer', 'permata_va_number' => '8778'])->methodLabel());
        $this->assertSame('Mandiri Bill Payment', $this->attempt(['payment_type' => 'echannel', 'bill_key' => '1'])->methodLabel());
        $this->assertSame('Kartu Kredit', $this->attempt(['payment_type' => 'credit_card'])->methodLabel());
        $this->assertSame('Indomaret', $this->attempt(['payment_type' => 'cstore', 'store' => 'indomaret'])->methodLabel());
    }

    public function test_requested_method_is_used_until_midtrans_reports_one(): void
    {
        $this->assertSame('Virtual Account BNI', $this->attempt(null, 'bni_va')->methodLabel());
        $this->assertSame('QRIS', $this->attempt(['transaction_status' => 'pending'], 'qris')->methodLabel());
        $this->assertNull($this->attempt(null)->methodLabel());
    }

    public function test_order_label_prefers_the_paid_attempt(): void
    {
        $order = Order::factory()->create();
        $this->attempt(['payment_type' => 'qris', 'issuer' => 'gopay'], order: $order);
        $this->attempt(['payment_type' => 'bank_transfer', 'va_numbers' => [['bank' => 'bni']]], status: PaymentAttemptStatus::EXPIRED, order: $order);

        $this->assertSame('QRIS (GoPay)', $order->fresh()->load('paymentAttempts')->paymentMethodLabel());
        $this->assertSame('-', Order::factory()->create()->load('paymentAttempts')->paymentMethodLabel());
    }

    public function test_invoice_shows_the_real_payment_method(): void
    {
        $order = Order::factory()->create();
        $this->attempt(['payment_type' => 'qris', 'issuer' => 'gopay'], order: $order);

        $this->get(route('orders.invoice', $order))
            ->assertOk()
            ->assertSee('QRIS (GoPay)')
            ->assertDontSee('Transfer bank BCA');
    }

    public function test_admin_order_list_uses_the_same_label(): void
    {
        $order = Order::factory()->create();
        $this->attempt(['payment_type' => 'bank_transfer', 'va_numbers' => [['bank' => 'bca', 'va_number' => '12345']]], order: $order);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('>Virtual Account BCA<', false)
            ->assertDontSee('>BANK TRANSFER<', false);
    }
}
