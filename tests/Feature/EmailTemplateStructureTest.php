<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

// Permintaan pemilik 2026-11-20: semua template email seragam dan terkumpul di
// resources/views/emails. Gaya hanya ada di komponen emails/components, template
// halaman hanya berisi teks dan susunan komponen, warna di config/mail.php.
class EmailTemplateStructureTest extends TestCase
{
    use RefreshDatabase;

    private const ORDER_TEMPLATES = [
        'new-order-customer', 'new-order-admin', 'payment-paid', 'payment-paid-admin',
        'order-shipped', 'order-delivered', 'payment-alert',
    ];

    private function order(): Order
    {
        $this->fakeShippingQuote();
        $product = Product::factory()->create(['is_active' => true, 'stock' => 10, 'original_price' => 100000, 'promo_price' => null]);

        $order = app(OrderService::class)->checkout([
            'customer' => ['name' => 'Budi Santoso', 'email' => 'budi@email.com', 'phone' => '0812'],
            'shipping' => [
                'destination_id' => 17473,
                'destination_label' => 'JATINEGARA, CAKUNG, JAKARTA TIMUR',
                'address' => 'Jl. Melati 1',
                'courier' => 'jne',
                'cost' => 20000,
            ],
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], 'key-'.uniqid());

        $order->forceFill(['tracking_number' => 'JP1234567890', 'paid_at' => now()])->save();

        return $order->fresh();
    }

    private function pageTemplates(): array
    {
        return [
            ...File::glob(resource_path('views/emails/orders/*.blade.php')),
            ...File::glob(resource_path('views/emails/auth/*.blade.php')),
        ];
    }

    public function test_page_templates_hold_no_inline_styles_or_tables(): void
    {
        $this->assertCount(8, $this->pageTemplates());

        foreach ($this->pageTemplates() as $path) {
            $isi = File::get($path);

            $this->assertStringNotContainsString('style=', $isi, basename($path));
            $this->assertStringNotContainsString('<table', $isi, basename($path));
        }
    }

    public function test_shared_pieces_live_in_the_email_components_folder(): void
    {
        foreach (['button', 'callout', 'text', 'strong', 'order-summary', 'shipping'] as $komponen) {
            $this->assertFileExists(resource_path("views/emails/components/{$komponen}.blade.php"));
        }

        $this->assertDirectoryDoesNotExist(resource_path('views/emails/orders/partials'));
    }

    public function test_email_colours_come_from_mail_config_not_the_service_provider(): void
    {
        $this->assertSame('#2F7D32', config('mail.palette.accent'));
        $this->assertSame('#fffbeb', config('mail.tones.warning.bg'));
        $this->assertStringNotContainsString('#2F7D32', File::get(app_path('Providers/AppServiceProvider.php')));
    }

    public function test_every_order_email_has_a_button_with_a_copyable_fallback_link(): void
    {
        $order = $this->order();

        foreach (self::ORDER_TEMPLATES as $tpl) {
            $html = view('emails.orders.'.$tpl, ['order' => $order, 'reason' => 'Nominal tidak cocok.'])->render();
            $tujuan = str_ends_with($tpl, 'admin') || $tpl === 'payment-alert'
                ? route('admin.orders.show', $order)
                : route('checkout.success', $order);

            $this->assertStringContainsString('Jika tombol di atas tidak berfungsi', $html, $tpl);
            $this->assertStringContainsString('>'.$tujuan.'</a>', $html, $tpl);
        }
    }

    public function test_reset_password_email_uses_the_same_button_and_fallback(): void
    {
        $user = User::factory()->create(['email' => 'budi@contoh.id']);

        $html = (string) (new ResetPassword('token-abc'))->toMail($user)->render();

        $this->assertStringContainsString('Jika tombol di atas tidak berfungsi', $html);
        $this->assertMatchesRegularExpression('#>http[^<]+reset-password/token-abc[^<]*</a>#', $html);
    }

    public function test_inline_highlight_leaves_no_stray_space_before_punctuation(): void
    {
        $html = view('emails.orders.payment-paid', ['order' => $this->order()])->render();

        $this->assertMatchesRegularExpression('#Budi Santoso</strong>\. Pembayaran#', $html);
    }
}
