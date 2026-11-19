<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DarkModeCoverageTest extends TestCase
{
    public function test_setiap_halaman_storefront_dan_admin_punya_pasangan_kelas_gelap(): void
    {
        $tanpaGelap = [];

        $berkasHalamanAdmin = collect(File::allFiles(resource_path('views/pages/admin')))
            ->map(fn ($berkas) => $berkas->getPathname())
            ->filter(fn ($path) => str_ends_with($path, '.blade.php'))
            ->values()
            ->all();

        $berkas = array_merge(
            glob(resource_path('views/pages/storefront/*.blade.php')),
            glob(resource_path('views/partials/storefront/*.blade.php')),
            glob(resource_path('views/components/*.blade.php')),
            $berkasHalamanAdmin,
            glob(resource_path('views/components/admin/*.blade.php')),
        );

        /**
         * Dikecualikan karena bukan halaman/komponen berpermukaan warna storefront:
         * - auth-header, auth-session-status, desktop-user-menu: dipakai halaman
         *   auth Fortify dan sidebar admin, bukan storefront.
         * - app-logo, wordmark, container: murni struktural atau meneruskan warna
         *   ke komponen anak, tidak punya permukaan sendiri.
         * - page-header: banner selalu bg-brand-green + teks putih di kedua mode
         *   (pola "banner lunas" yang sama seperti final-review Rencana 2).
         * - breadcrumb: satu-satunya pemanggil (page-header) selalu menimpa
         *   warnanya lewat class, jadi warna bawaannya tak pernah terlihat.
         * - product-lightbox: overlay layar penuh selalu bg-ink/95 dengan kontrol
         *   putih di kedua mode, jadi tidak ada permukaan yang berganti warna.
         * - theme-toggle: warna jalur sakelar ditukar Alpine lewat isDark
         *   (bg-gray-300 saat gelap, bg-brand-green saat terang), bukan kelas dark:.
         *
         * Dikecualikan dari area admin:
         * - sidebar.blade.php: rail admin memakai bg-brand-dark + teks putih
         *   secara tetap di kedua mode, persis acuan desain — tidak ada
         *   permukaan di berkas ini yang berubah warna saat mode berganti.
         * - order-status-badge.blade.php: kelas chip & dot diambil dari
         *   App\Support\Tone::chip()/dot(), yang memasangkan setiap warna
         *   dengan varian dark: miliknya sendiri — pasangannya ada di berkas
         *   PHP itu, bukan sebagai teks literal di blade ini.
         */
        $dikecualikan = [
            'counter.blade.php',
            'icons.blade.php',
            'auth-header.blade.php',
            'auth-session-status.blade.php',
            'desktop-user-menu.blade.php',
            'app-logo.blade.php',
            'wordmark.blade.php',
            'container.blade.php',
            'page-header.blade.php',
            'breadcrumb.blade.php',
            'product-lightbox.blade.php',
            'theme-toggle.blade.php',
            'sidebar.blade.php',
            'order-status-badge.blade.php',
        ];

        foreach ($berkas as $path) {
            $nama = basename($path);

            if (in_array($nama, $dikecualikan, true) || str_contains($nama, 'counter')) {
                continue;
            }

            $isi = (string) file_get_contents($path);

            if (! str_contains($isi, 'dark:')) {
                $tanpaGelap[] = $nama;
            }
        }

        $this->assertSame([], $tanpaGelap);
    }
}
