<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    // Dashboard member memakai kerangka yang sama dengan panel admin: rail gelap
    // <x-shell.sidebar>, bukan lagi komponen sidebar bawaan Flux.
    public function test_dashboard_memakai_kerangka_yang_sama_dengan_panel_admin(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('bg-brand-dark', $html);
        $this->assertStringContainsString('nav-bar', $html);
        $this->assertStringContainsString('Akun Saya', $html);
        $this->assertStringNotContainsString('flux-sidebar', $html);
    }

    public function test_penanda_aktif_sidebar_member_menyala_di_halaman_yang_tepat(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('dashboard.orders.index'))
            ->assertOk()
            ->getContent();

        preg_match_all('/<a[^>]*class="[^"]*nav-link[^"]*"[^>]*>/', $html, $tautan);

        $aktif = array_values(array_filter(
            $tautan[0],
            fn (string $tag): bool => str_contains($tag, 'active'),
        ));

        $this->assertCount(1, $aktif, 'Tepat satu tautan sidebar harus bertanda aktif.');
        $this->assertStringContainsString(route('dashboard.orders.index'), $aktif[0]);
    }

    // Area member dan pengaturan akun berbahasa Indonesia saja, tanpa dwibahasa seperti
    // storefront. Halaman pengaturan bawaan starter kit dulu berbahasa Inggris di balik __().
    public function test_area_member_tidak_memakai_terjemahan(): void
    {
        $pelanggar = [];

        foreach (['views/pages/dashboard', 'views/pages/settings'] as $folder) {
            foreach (File::allFiles(resource_path($folder)) as $berkas) {
                if (str_contains((string) file_get_contents($berkas->getPathname()), '__(')) {
                    $pelanggar[] = $berkas->getRelativePathname();
                }
            }
        }

        $this->assertSame([], $pelanggar);
    }
}
