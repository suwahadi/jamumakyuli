<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Halaman auth dulu memaksa tema terang tanpa skrip tema, tetapi kelas dark
 * tetap terbawa saat datang lewat wire:navigate dari toko yang gelap. Label
 * Flux lalu berubah putih di atas kartu putih dan hilang.
 */
class AuthTampilanTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function halaman(): array
    {
        return [
            'masuk' => ['login', 'Masuk ke akun Anda'],
            'daftar' => ['register', 'Buat akun baru'],
            'lupa kata sandi' => ['password.request', 'Lupa kata sandi'],
        ];
    }

    #[DataProvider('halaman')]
    public function test_halaman_auth_tanpa_subjudul(string $rute, string $judul): void
    {
        $this->get(route($rute))
            ->assertOk()
            ->assertSee($judul)
            ->assertDontSee('Masukkan email dan kata sandi Anda di bawah untuk masuk')
            ->assertDontSee('Lengkapi data di bawah untuk membuat akun Anda')
            ->assertDontSee('Masukkan email Anda untuk menerima tautan atur ulang kata sandi');
    }

    #[DataProvider('halaman')]
    public function test_halaman_auth_mengikuti_tema_dan_punya_permukaan_gelap(string $rute): void
    {
        $this->get(route($rute))
            ->assertOk()
            ->assertSee("localStorage.setItem('flux.appearance', 'dark')", false)
            ->assertSee('dark:bg-zinc-950', false)
            ->assertSee('dark:bg-zinc-900', false);
    }

    #[DataProvider('halaman')]
    public function test_header_wordmark_berlatar_foto_rempah_dengan_lapisan_gelap(string $rute): void
    {
        $this->get(route($rute))
            ->assertOk()
            ->assertSee('assets/images/auth-rempah.webp', false)
            ->assertSee('Jamu <span class="text-green-300">Mak Yuli</span>', false)
            ->assertSee('bg-gradient-to-t from-black', false);
    }

    public function test_foto_header_tersedia_sebagai_aset_publik(): void
    {
        $this->assertFileExists(public_path('assets/images/auth-rempah.webp'));
    }
}
