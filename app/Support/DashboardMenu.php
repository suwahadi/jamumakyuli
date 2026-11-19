<?php

declare(strict_types=1);

namespace App\Support;

class DashboardMenu
{
    public static function items(): array
    {
        return [
            ['label' => 'Dashboard', 'icon' => 'i-layout-dashboard', 'route' => 'dashboard'],

            ['group' => 'Transaksi', 'icon' => 'i-shopping-bag', 'items' => [
                ['label' => 'Riwayat Pesanan', 'icon' => 'i-receipt-text', 'route' => 'dashboard.orders.index', 'match' => ['dashboard.orders.*']],
                ['label' => 'Notifikasi', 'icon' => 'i-bell', 'route' => 'dashboard.notifications.index'],
            ]],

            ['label' => 'Favorit', 'icon' => 'i-heart', 'route' => 'dashboard.wishlist'],

            ['label' => 'Pengaturan Akun', 'icon' => 'i-settings', 'route' => 'profile.edit', 'match' => ['profile.*', 'security.*', 'appearance.*']],
        ];
    }

    public static function currentLabel(): string
    {
        foreach (self::items() as $entri) {
            foreach ($entri['items'] ?? [$entri] as $item) {
                if (isset($item['route']) && request()->routeIs(...(array) ($item['match'] ?? $item['route']))) {
                    return $item['label'];
                }
            }
        }

        return 'Dashboard';
    }
}
