<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCatalog;
use Tests\TestCase;

class HeaderHpTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCatalog;

    public function test_sakelar_tema_hp_pindah_ke_baris_cari_dan_menu_kategori_tetap_di_header(): void
    {
        $this->seedCatalog();

        $html = $this->actingAs(User::factory()->create())->get(route('home'))->assertOk()->getContent();

        preg_match('/<header id="site-header".*?<\/header>/s', $html, $header);
        preg_match('/<div id="mobile-search"[^>]*>.*?<\/form>.*?<\/div>/s', $html, $barisCari);

        $this->assertMatchesRegularExpression('/<button[^>]*role="switch"[^>]*class="[^"]*\bhidden\b[^"]*md:inline-flex/s', $header[0]);
        $this->assertStringContainsString('role="switch"', $barisCari[0]);
        $this->assertStringContainsString('<div class="relative shrink-0" x-data="{ open: false }"', $header[0]);
        $this->assertStringContainsString('aria-label="Kategori"', $header[0]);
    }

    public function test_jarak_header_hp_dirapatkan_supaya_muat_di_layar_360(): void
    {
        $this->seedCatalog();

        $html = $this->actingAs(User::factory()->create())->get(route('home'))->assertOk()->getContent();
        preg_match('/<header id="site-header".*?<\/header>/s', $html, $header);

        $this->assertStringContainsString('class="container-x flex h-[84px] items-center gap-1 sm:gap-4"', $header[0]);
        $this->assertStringContainsString('class="ml-auto flex items-center gap-0 sm:gap-1"', $header[0]);
        $this->assertStringContainsString('text-xl sm:text-[28px]', $header[0]);
        $this->assertStringNotContainsString('aria-label="Pilih bahasa"', $header[0]);
    }
}
