<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_shows_horizontal_tabs_with_profile_active(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('profile.edit'));

        $response->assertOk()
            ->assertSee('aria-label="Pengaturan akun"', false)
            ->assertSeeInOrder(['Profil', 'Keamanan', 'Tampilan'])
            ->assertSee('href="'.route('profile.edit').'" wire:navigate aria-current="page"', false)
            ->assertSee('Informasi Profil')
            ->assertDontSee('flux:navlist', false);
    }

    public function test_profile_shows_name_email_and_phone_in_one_row(): void
    {
        $user = User::factory()->create(['phone' => '081234567890']);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('md:grid-cols-3', false)
            ->assertSeeInOrder(['Nama', 'Email', 'Telepon'])
            ->assertSee('081234567890');
    }

    public function test_profile_phone_can_be_updated(): void
    {
        $user = User::factory()->create(['phone' => null]);

        Livewire::actingAs($user)->test('pages::settings.profile')
            ->set('phone', '085700001111')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertSame('085700001111', $user->fresh()->phone);
    }

    public function test_profile_phone_longer_than_twenty_characters_is_rejected_inline(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test('pages::settings.profile')
            ->set('phone', str_repeat('8', 21))
            ->call('updateProfileInformation')
            ->assertHasErrors(['phone' => 'max']);
    }

    public function test_topbar_shows_the_real_role_of_the_signed_in_account(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('profile.edit'))
            ->assertSee('Administrator');

        $this->actingAs(User::factory()->create(['role' => 'customer']))->get(route('profile.edit'))
            ->assertSee('Pelanggan')
            ->assertDontSee('Administrator');
    }

    public function test_danger_buttons_use_the_accessible_error_red(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('[data-flux-button].bg-red-500 { background-color: var(--color-sale); }', $css);
    }

    public function test_appearance_page_marks_its_own_tab_active(): void
    {
        $this->actingAs(User::factory()->create())->get(route('appearance.edit'))
            ->assertOk()
            ->assertSee('href="'.route('appearance.edit').'" wire:navigate aria-current="page"', false)
            ->assertDontSee('href="'.route('profile.edit').'" wire:navigate aria-current="page"', false)
            ->assertSee('Tema')
            ->assertSee('role="radiogroup"', false)
            ->assertSee('grid grid-cols-3', false);
    }
}
