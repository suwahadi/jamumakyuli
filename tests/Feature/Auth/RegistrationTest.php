<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register_and_are_redirected_to_dashboard(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
        ]);
    }

    public function test_registered_user_can_immediately_access_the_dashboard(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Siti Rahma',
            'email' => 'siti@example.com',
            'phone' => '081234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Andi',
            'email' => 'andi@example.com',
            'password' => 'password',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post(route('register.store'), [
            'name' => 'Dewi',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_registrasi_menyimpan_nomor_hp(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertSame('081234567890', User::query()->where('email', 'budi@example.com')->value('phone'));
    }

    public function test_nomor_hp_wajib_diisi(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('phone');

        $this->assertNull(User::query()->where('email', 'budi@example.com')->first());
    }

    public function test_form_registrasi_menampilkan_field_nomor_hp(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('Nomor HP');
    }
}
