<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tautan reset kata sandi dulu dibentuk dari host permintaan, sementara semua
 * proxy dipercaya. Penyerang cukup mengirim X-Forwarded-Host miliknya saat
 * meminta reset untuk email admin, lalu tautan di email asli Jamu Mak Yuli mengarah
 * ke servernya dan membocorkan token reset.
 */
class ResetPasswordHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_tautan_reset_memakai_app_url_walau_ada_x_forwarded_host(): void
    {
        config()->set('app.url', 'https://jamumakyuli.com');
        Notification::fake();
        $admin = User::factory()->create(['email' => 'admin@contoh.id']);

        $this->post(route('password.email'), ['email' => 'admin@contoh.id'], [
            'X-Forwarded-Host' => 'evil.example',
            'X-Forwarded-Proto' => 'https',
        ]);

        Notification::assertSentTo($admin, ResetPassword::class, function (ResetPassword $notifikasi) use ($admin): bool {
            $html = (string) $notifikasi->toMail($admin)->render();

            return str_contains($html, 'https://jamumakyuli.com/reset-password/')
                && ! str_contains($html, 'evil.example');
        });
    }

    // Lapis kedua: walau host permintaan sendiri palsu, tautan tetap dari APP_URL.
    public function test_tautan_reset_memakai_app_url_walau_header_host_palsu(): void
    {
        config()->set('app.url', 'https://jamumakyuli.com');
        Notification::fake();
        $admin = User::factory()->create(['email' => 'admin@contoh.id']);

        $this->withServerVariables(['HTTP_HOST' => 'evil.example'])
            ->post(route('password.email', [], false), ['email' => 'admin@contoh.id']);

        Notification::assertSentTo($admin, ResetPassword::class, function (ResetPassword $notifikasi) use ($admin): bool {
            $html = (string) $notifikasi->toMail($admin)->render();

            return str_contains($html, 'https://jamumakyuli.com/reset-password/')
                && ! str_contains($html, 'evil.example');
        });
    }

    public function test_x_forwarded_host_tidak_mengubah_host_permintaan(): void
    {
        $this->get('/', ['X-Forwarded-Host' => 'evil.example']);

        $this->assertNotSame('evil.example', request()->getHost());
    }

    public function test_host_tepercaya_dibatasi_di_production(): void
    {
        $this->assertTrue(app(Kernel::class)->hasMiddleware(TrustHosts::class));
    }
}
