<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Tests\TestCase;

class ResetPasswordEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_reset_password_memakai_layout_brand_toko(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@contoh.id']);

        $html = (string) (new ResetPassword('token-abc'))->toMail($user)->render();

        $this->assertStringContainsString('Atur Ulang Kata Sandi', $html);
        $this->assertStringContainsString('#2F7D32', $html);
        $this->assertStringContainsString('font-family:Arial,Helvetica,sans-serif', $html);
        $this->assertStringContainsString('Email ini dikirim otomatis oleh sistem.', $html);
        $this->assertStringContainsString('budi@contoh.id', $html);
        $this->assertStringContainsString('reset-password/token-abc', $html);
    }

    public function test_mailer_brevo_terdaftar_dan_memakai_brevo_api_key(): void
    {
        config(['mail.mailers.brevo.key' => 'xkeysib-untuk-tes']);

        $this->assertInstanceOf(BrevoApiTransport::class, Mail::mailer('brevo')->getSymfonyTransport());
    }

    public function test_email_reset_password_tidak_lagi_memakai_template_bawaan_laravel(): void
    {
        $user = User::factory()->create();

        $html = (string) (new ResetPassword('token-abc'))->toMail($user)->render();

        $this->assertStringNotContainsString('Whoops!', $html);
        $this->assertStringNotContainsString('Reset Password Notification', $html);
    }
}
