<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StatusPesananSaatKembaliTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_pesanan_admin_dimuat_ulang_saat_kembali(): void
    {
        $html = Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.orders.index')->html();

        $this->assertMatchesRegularExpression('#^<div[^>]*\bdata-segarkan-saat-kembali\b#', $html);
    }

    public function test_dashboard_admin_dimuat_ulang_saat_kembali(): void
    {
        $html = Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.dashboard')->html();

        $this->assertMatchesRegularExpression('#^<div[^>]*\bdata-segarkan-saat-kembali\b#', $html);
    }

    public function test_riwayat_pesanan_pembeli_dimuat_ulang_saat_kembali(): void
    {
        $html = Livewire::actingAs(User::factory()->create())->test('pages::dashboard.orders.index')->html();

        $this->assertMatchesRegularExpression('#^<div[^>]*\bdata-segarkan-saat-kembali\b#', $html);
    }
}
