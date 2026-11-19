<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    // Halaman ini dinonaktifkan lewat storefront.disabled_pages; test tetap
    // menjalankannya supaya aman saat dimunculkan kembali.
    protected function setUp(): void
    {
        parent::setUp();

        config(['storefront.disabled_pages' => []]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_omset_hanya_dari_pesanan_lunas_dan_dikirim(): void
    {
        Order::factory()->create(['status' => OrderStatus::PAID, 'grand_total' => 2_000_000, 'created_at' => now()]);
        Order::factory()->create(['status' => OrderStatus::SHIPPED, 'grand_total' => 1_000_000, 'created_at' => now()]);
        Order::factory()->create(['status' => OrderStatus::PENDING, 'grand_total' => 8_000_000, 'created_at' => now()]);
        Order::factory()->create(['status' => OrderStatus::CANCELLED, 'grand_total' => 5_000_000, 'created_at' => now()]);

        $this->actingAs($this->admin())->get(route('admin.reports.sales'))
            ->assertOk()
            ->assertSee(rupiah(3_000_000))
            ->assertDontSee(rupiah(16_000_000));
    }

    public function test_laporan_tidak_menampilkan_kolom_laba(): void
    {
        $this->actingAs($this->admin())->get(route('admin.reports.sales'))
            ->assertOk()
            ->assertDontSee('HPP')
            ->assertDontSee('Laba Kotor')
            ->assertDontSee('Margin');
    }

    public function test_bulan_tanpa_pesanan_tetap_muncul_dengan_nilai_nol(): void
    {
        $this->actingAs($this->admin())->get(route('admin.reports.sales'))
            ->assertOk()
            ->assertSee(now()->locale('id')->isoFormat('MMMM YYYY'));
    }
}
