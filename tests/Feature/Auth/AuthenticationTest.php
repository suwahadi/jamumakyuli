<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    // Keputusan pemilik 2026-10-05: kop kartu auth memakai logo brand resmi, bukan
    // wordmark teks. Kop selalu berupa foto gelap ber-overlay di kedua tema, jadi
    // yang dipakai selalu versi logo untuk latar gelap, tanpa ditukar menurut tema.
    public function test_kop_auth_memakai_wordmark_untuk_latar_gelap(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $badan = substr($html, strpos($html, '<body'));

        $this->assertStringContainsString('Jamu <span class="text-green-300">Mak Yuli</span>', $badan);
        $this->assertStringNotContainsString('assets/images/logo-', $badan);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrorsIn('email');

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
