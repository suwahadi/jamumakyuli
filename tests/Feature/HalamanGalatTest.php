<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HalamanGalatTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_bertema_toko_dengan_pencarian_dan_jalan_kembali(): void
    {
        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('Mak Yuli</span>', false)
            ->assertSee('name="q"', false)
            ->assertSee('Ke Beranda')
            ->assertSee('Lihat Katalog')
            ->assertDontSee('Not Found');
    }

    public function test_halaman_galat_server_dan_pemeliharaan_tampil_tanpa_database(): void
    {
        foreach (['500' => 'Terjadi kesalahan di sisi kami', '503' => 'Toko sedang dalam pemeliharaan', '419' => 'Sesi sudah berakhir', '403' => 'Akses ditolak', '429' => 'Terlalu banyak permintaan'] as $kode => $judul) {
            $html = view('errors.'.$kode)->render();
            $kode = (string) $kode;

            $this->assertStringContainsString($judul, $html, $kode);
            $this->assertStringNotContainsString('—', $html, $kode);
        }
    }
}
