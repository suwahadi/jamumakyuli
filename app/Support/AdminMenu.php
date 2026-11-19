<?php

declare(strict_types=1);

namespace App\Support;

class AdminMenu
{
    private const DERIVED_PAGE_LABELS = [
        'admin.products.create' => 'Produk Baru',
        'admin.products.edit' => 'Ubah Produk',
        'admin.orders.show' => 'Detail Pesanan',
    ];

    public static function isDisabled(string $routeName): bool
    {
        return in_array($routeName, (array) config('storefront.disabled_pages', []), true);
    }

    public static function items(): array
    {
        $tersembunyi = [...(array) config('storefront.hidden.admin_menu', []), ...(array) config('storefront.disabled_pages', [])];

        if ($tersembunyi === []) {
            return self::semua();
        }

        $tampil = static fn (array $item): bool => ! in_array($item['route'] ?? '', $tersembunyi, true);

        $hasil = [];

        foreach (self::semua() as $entri) {
            if (! isset($entri['items'])) {
                if ($tampil($entri)) {
                    $hasil[] = $entri;
                }

                continue;
            }

            $entri['items'] = array_values(array_filter($entri['items'], $tampil));

            if ($entri['items'] !== []) {
                $hasil[] = $entri;
            }
        }

        return $hasil;
    }

    private static function semua(): array
    {
        return [
            ['label' => 'Dashboard', 'icon' => 'i-layout-dashboard', 'route' => 'admin.dashboard'],

            ['group' => 'Produk', 'icon' => 'i-package', 'items' => [
                ['label' => 'Kategori', 'icon' => 'i-folder-tree', 'route' => 'admin.categories.index'],
                ['label' => 'List Produk', 'icon' => 'i-list', 'route' => 'admin.products.index'],
                ['label' => 'Export / Import Data', 'icon' => 'i-arrow-down-up', 'route' => 'admin.transfer.products'],
            ]],

            ['group' => 'Order / Pesanan', 'icon' => 'i-shopping-bag', 'items' => [
                ['label' => 'List Pesanan', 'icon' => 'i-receipt-text', 'route' => 'admin.orders.index'],
                ['label' => 'Notifikasi', 'icon' => 'i-bell', 'route' => 'admin.notifications.index'],
            ]],

            ['group' => 'Stok', 'icon' => 'i-warehouse', 'items' => [
                ['label' => 'Stok Opname', 'icon' => 'i-scan-line', 'route' => 'admin.stock-opname.index'],
            ]],

            ['group' => 'Pengguna', 'icon' => 'i-users', 'items' => [
                ['label' => 'List Pengguna', 'icon' => 'i-user-round', 'route' => 'admin.users.index'],
                ['label' => 'Export / Import Data', 'icon' => 'i-arrow-down-up', 'route' => 'admin.transfer.users'],
            ]],

            ['group' => 'Konten', 'icon' => 'i-file-text', 'items' => [
                ['label' => 'Halaman', 'icon' => 'i-file-text', 'route' => 'admin.pages.index'],
                ['label' => 'Slide Hero', 'icon' => 'i-image', 'route' => 'admin.slides.index'],
                ['label' => 'Testimoni', 'icon' => 'i-quote', 'route' => 'admin.testimonials.index'],
                ['label' => 'Sekilas Tentang', 'icon' => 'i-grid', 'route' => 'admin.about-images.index'],
            ]],

            ['group' => 'Laporan', 'icon' => 'i-chart-column', 'items' => [
                ['label' => 'Laporan Penjualan', 'icon' => 'i-chart-column', 'route' => 'admin.reports.sales'],
            ]],

            ['label' => 'Pengaturan Global', 'icon' => 'i-settings', 'route' => 'admin.settings'],
        ];
    }

    public static function currentLabel(): string
    {
        foreach (self::semua() as $entri) {
            foreach ($entri['items'] ?? [$entri] as $item) {
                if (isset($item['route']) && request()->routeIs($item['route'])) {
                    return $item['label'];
                }
            }
        }

        foreach (self::DERIVED_PAGE_LABELS as $route => $label) {
            if (request()->routeIs($route)) {
                return $label;
            }
        }

        return 'Dashboard';
    }
}
