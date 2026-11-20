<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use App\Support\AdminMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisabledAdminPagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function menuRoutes(): array
    {
        $routes = [];

        foreach (AdminMenu::items() as $entry) {
            foreach ($entry['items'] ?? [$entry] as $item) {
                $routes[] = $item['route'];
            }
        }

        return $routes;
    }

    public function test_stock_opname_and_sales_report_are_disabled_by_default(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/stock-opname')->assertNotFound();
        $this->actingAs($admin)->get('/admin/reports/sales')->assertNotFound();

        $this->assertNotContains('admin.stock-opname.index', $this->menuRoutes());
        $this->assertNotContains('admin.reports.sales', $this->menuRoutes());
    }

    public function test_product_export_import_is_disabled_by_default(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/transfer/products')->assertNotFound();
        $this->actingAs($admin)->get('/admin/transfer/products/export')->assertNotFound();

        $this->assertNotContains('admin.transfer.products', $this->menuRoutes());
    }

    // Permintaan pemilik 2026-11-20: ekspor/impor pengguna juga ditutup di level URL,
    // bukan hanya disembunyikan dari menu.
    public function test_user_export_import_is_disabled_by_default(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/transfer/users')->assertNotFound();
        $this->actingAs($admin)->get('/admin/transfer/users/export')->assertNotFound();

        $this->assertNotContains('admin.transfer.users', $this->menuRoutes());
    }

    public function test_dashboard_does_not_link_to_disabled_stock_opname(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('/admin/stock-opname', false);
    }

    public function test_disabled_pages_come_back_when_removed_from_config(): void
    {
        config(['storefront.disabled_pages' => []]);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/stock-opname')->assertOk();
        $this->actingAs($admin)->get('/admin/reports/sales')->assertOk();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('/admin/stock-opname', false);

        $this->assertContains('admin.stock-opname.index', $this->menuRoutes());
        $this->assertContains('admin.reports.sales', $this->menuRoutes());
    }

    public function test_critically_low_stock_on_dashboard_uses_warning_red(): void
    {
        Product::factory()->create(['name' => 'Jamu Hampir Habis', 'stock' => 1, 'is_active' => true]);

        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Jamu Hampir Habis')
            ->assertSee('font-display text-sm font-bold text-sale', false);
    }
}
