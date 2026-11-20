<?php

return [

    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'brevo' => [
            'transport' => 'brevo',
            'key' => env('BREVO_API_KEY'),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

    'palette' => [
        'accent' => '#2F7D32',
        'accentDark' => '#1F5A23',
        'dark' => '#0F2A14',
        'ink' => '#0b0b0c',
        'muted' => '#6b7280',
        'line' => '#e5e7eb',
        'cream' => '#f9fafb',
        'creamDeep' => '#f5f5f5',
    ],

    'tones' => [
        'warning' => ['bg' => '#fffbeb', 'border' => '#fde68a', 'label' => '#92400e', 'note' => '#92400e', 'badgeBg' => '#f59e0b', 'badgeText' => '#0b0b0c'],
        'success' => ['bg' => '#ecfdf5', 'border' => '#a7f3d0', 'label' => '#065f46', 'note' => '#047857', 'badgeBg' => '#047857', 'badgeText' => '#ffffff'],
        'info' => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'label' => '#1e40af', 'note' => '#1d4ed8', 'badgeBg' => '#1d4ed8', 'badgeText' => '#ffffff'],
        'danger' => ['bg' => '#fef2f2', 'border' => '#fecaca', 'label' => '#991b1b', 'note' => '#b91c1c', 'badgeBg' => '#dc2626', 'badgeText' => '#ffffff'],
        'brand' => ['bg' => '#f5f5f5', 'border' => '#2F7D32', 'label' => '#1F5A23', 'note' => '#1F5A23', 'badgeBg' => '#2F7D32', 'badgeText' => '#ffffff'],
        'neutral' => ['bg' => '#ffffff', 'border' => '#e5e7eb', 'label' => '#6b7280', 'note' => '#6b7280', 'badgeBg' => '#6b7280', 'badgeText' => '#ffffff'],
    ],

];
