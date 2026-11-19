@php
    $waLink = wa_href('Halo, saya butuh bantuan terkait akun/pesanan saya.');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="admin min-h-screen bg-gray-50 text-zinc-800 antialiased dark:bg-zinc-950 dark:text-zinc-200">
        @include('partials.storefront.icons')
        @include('partials.admin.icons')

        <div class="flex min-h-screen" x-data="{ sidebar: false }">
            <x-shell.sidebar :menu="\App\Support\DashboardMenu::items()" :home="route('dashboard')" subtitle="Akun Saya">
                @if (auth()->user()?->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" wire:navigate
                       class="mb-1 flex items-center gap-3 rounded-md p-2.5 transition hover:bg-white/10">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-white/10">
                            <svg class="h-4 w-4 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-shield-check"/></svg>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold leading-tight">Panel Admin</p>
                            <p class="truncate text-xs text-white/60">Kelola toko</p>
                        </div>
                    </a>
                @endif

                <a href="{{ route('home') }}" wire:navigate
                   class="flex items-center gap-3 rounded-md p-2.5 transition hover:bg-white/10">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-white/10">
                        <svg class="h-4 w-4 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-external-link"/></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold leading-tight">Kembali ke Toko</p>
                        <p class="truncate text-xs text-white/60">Lanjut belanja</p>
                    </div>
                </a>

                @if ($waLink)
                    <a href="{{ $waLink }}" target="_blank" rel="noopener"
                       class="mt-1 flex items-center gap-3 rounded-md p-2.5 transition hover:bg-white/10">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-white/10">
                            <svg class="h-4 w-4 fill-current" aria-hidden="true"><use href="#i-wa"/></svg>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold leading-tight">Bantuan</p>
                            <p class="truncate text-xs text-white/60">Hubungi via WhatsApp</p>
                        </div>
                    </a>
                @endif
            </x-shell.sidebar>

            <div class="flex min-w-0 flex-1 flex-col">
                <x-shell.topbar :title="\App\Support\DashboardMenu::currentLabel()" :home="route('dashboard')" :role="auth()->user()?->role?->label() ?? 'Pelanggan'" />

                <main class="mx-auto w-full max-w-[1320px] flex-1 p-4 pb-10 sm:p-6 sm:pb-12">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
