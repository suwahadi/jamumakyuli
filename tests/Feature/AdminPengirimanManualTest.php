<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pemilik toko mengirim paket sendiri ke ekspedisi lalu memasukkan nomor
 * resinya. Tidak ada lagi pembuatan order atau penjemputan otomatis.
 */
class AdminPengirimanManualTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function pesanan(OrderStatus $status, ?string $resi = null): Order
    {
        return Order::factory()->create([
            'status' => $status,
            'shipping_courier' => 'jne',
            'shipping_service' => 'REG',
            'tracking_number' => $resi,
        ]);
    }

    public function test_pesanan_lunas_menampilkan_petunjuk_kirim_manual_tanpa_jadwal_jemput(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::admin.orders.show', ['order' => $this->pesanan(OrderStatus::PAID)])
            ->assertSee('Tandai Dikirim')
            ->assertSee('Setelah paket diserahkan ke ekspedisi')
            ->assertDontSee('Jadwalkan Jemput')
            ->assertDontSee('Tanggal Jemput')
            ->assertDontSee('terisi otomatis')
            ->assertDontSee('Pengiriman otomatis');
    }

    public function test_pesanan_terkirim_tidak_menampilkan_aksi_pengiriman_otomatis(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::admin.orders.show', ['order' => $this->pesanan(OrderStatus::SHIPPED, 'JP1234567890')])
            ->assertDontSee('Unduh Label')
            ->assertDontSee('Lihat Pelacakan')
            ->assertDontSee('Batalkan Pengiriman')
            ->assertDontSee('Nomor Order Pengiriman')
            ->assertSee('Ubah nomor resi');
    }

    // Nama penyedia jasa tidak boleh bocor ke antarmuka klien.
    public function test_antarmuka_tidak_menyebut_nama_penyedia(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::admin.orders.show', ['order' => $this->pesanan(OrderStatus::SHIPPED, 'JP1234567890')])
            ->assertDontSee('Komerce');
    }

    public function test_tombol_salin_nomor_resi_tersedia(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::admin.orders.show', ['order' => $this->pesanan(OrderStatus::SHIPPED, '010116260018059')])
            ->assertSee('Salin nomor resi')
            ->assertSeeHtml("teks: '010116260018059'");
    }

    public function test_tombol_salin_tidak_muncul_tanpa_resi(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::admin.orders.show', ['order' => $this->pesanan(OrderStatus::PAID)])
            ->assertDontSee('Salin nomor resi');
    }

    public function test_pengaturan_tidak_lagi_menampilkan_dimensi_paket(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('ID Kelurahan Asal Pengiriman')
            ->assertDontSee('Panjang Paket Default');
    }

    public function test_webhook_pengiriman_otomatis_sudah_tidak_tersedia(): void
    {
        $this->postJson('/webhook/komerce/token-uji', ['order_no' => 'KOM123'])->assertNotFound();
    }
}
