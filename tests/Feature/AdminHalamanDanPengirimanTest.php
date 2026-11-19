<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminHalamanDanPengirimanTest extends TestCase
{
    use RefreshDatabase;

    private const ORDER = 'pages::admin.orders.show';

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function pesananDikirim(): Order
    {
        return Order::factory()->create([
            'status' => OrderStatus::SHIPPED,
            'shipping_courier' => 'jne',
            'shipping_service' => 'REG',
            'tracking_number' => 'JP1234567890',
        ]);
    }

    public function test_daftar_halaman_punya_tautan_pratinjau_di_tab_baru_untuk_halaman_aktif(): void
    {
        Page::query()->delete();
        $aktif = Page::factory()->create(['slug' => 'kebijakan-uji', 'is_active' => true]);
        $nonaktif = Page::factory()->create(['slug' => 'draf-uji', 'is_active' => false]);

        $html = Livewire::actingAs($this->admin())->test('pages::admin.pages.index')->html();

        $this->assertStringContainsString('href="'.route('pages.show', $aktif).'"', $html);
        $this->assertStringContainsString('Lihat halaman di toko (tab baru)', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringNotContainsString('href="'.route('pages.show', $nonaktif).'"', $html);
    }

    public function test_form_tambah_ubah_halaman_tidak_punya_tombol_lihat_halaman(): void
    {
        $page = Page::factory()->create();

        $html = Livewire::actingAs($this->admin())
            ->test('pages::admin.pages.index')
            ->call('edit', $page->id)
            ->html();

        $form = substr($html, strpos($html, 'wire:submit="save"'));
        $this->assertStringNotContainsString('Lihat halaman', $form);
    }

    // Kartu Pengiriman dipindah ke kolom utama, tepat di bawah Riwayat Pesanan.
    public function test_kartu_pengiriman_berada_di_bawah_riwayat_pesanan(): void
    {
        $html = Livewire::actingAs($this->admin())->test(self::ORDER, ['order' => $this->pesananDikirim()])->html();

        $riwayat = strpos($html, '>Riwayat Pesanan<');
        $pengiriman = strpos($html, '>Pengiriman<');
        $pelanggan = strpos($html, '>Pelanggan<');

        $this->assertNotFalse($riwayat);
        $this->assertGreaterThan($riwayat, $pengiriman);
        $this->assertLessThan($pelanggan, $pengiriman);
    }

    public function test_nomor_resi_diubah_lewat_field_inline_tanpa_tombol_hapus(): void
    {
        Livewire::actingAs($this->admin())
            ->test(self::ORDER, ['order' => $this->pesananDikirim()])
            ->assertSee('Ubah nomor resi')
            ->assertSee('Simpan nomor resi')
            ->assertSee('Batal ubah nomor resi')
            ->assertDontSee('Simpan Resi')
            ->assertDontSee('Hapus nomor resi');
    }

    public function test_menyimpan_resi_menutup_mode_ubah(): void
    {
        Livewire::actingAs($this->admin())
            ->test(self::ORDER, ['order' => $this->pesananDikirim()])
            ->set('tracking_number', 'JP0000000001')
            ->call('saveTracking')
            ->assertHasNoErrors()
            ->assertDispatched('resi-tersimpan');
    }
}
