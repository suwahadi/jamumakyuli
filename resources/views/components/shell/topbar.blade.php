@props([
    'title' => '',
    'home' => null,
    'homeLabel' => 'Beranda',
    'role' => '',
])

@php
    $beranda = $home ?? route('home');
    $pengguna = auth()->user();
@endphp

<header class="sticky top-0 z-20 flex h-[64px] items-center gap-3 border-b border-gray-200 bg-white px-4 dark:border-zinc-800 dark:bg-zinc-900 sm:px-6">
    <button type="button" @click="sidebar = true" aria-label="Buka menu"
            class="grid h-10 w-10 place-items-center rounded-md hover:bg-gray-100 dark:hover:bg-zinc-800 lg:hidden">
        <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-menu"/></svg>
    </button>

    <nav aria-label="Breadcrumb" class="hidden items-center gap-2 text-sm sm:flex">
        <a href="{{ $beranda }}" wire:navigate class="font-medium text-gray-400 transition hover:text-brand-green dark:text-zinc-400">{{ $homeLabel }}</a>
        <svg class="h-4 w-4 fill-none stroke-current stroke-2 text-gray-300 dark:text-zinc-600" aria-hidden="true"><use href="#i-chev-right"/></svg>
        <span class="font-display font-bold text-brand-dark dark:text-zinc-100" aria-current="page">{{ $title }}</span>
    </nav>

    <div class="ml-auto flex items-center gap-1 sm:gap-2">
        <livewire:notification-bell position="bottom" align="end" />

        <button type="button" x-data="{ isDark: document.documentElement.classList.contains('dark') }" role="switch"
                :aria-checked="isDark ? 'true' : 'false'"
                @click="isDark = ! isDark; $flux.appearance = isDark ? 'dark' : 'light'"
                aria-label="Mode gelap" title="Mode terang atau gelap"
                class="relative inline-flex h-7 w-[52px] shrink-0 items-center rounded-full p-0.5 transition-colors duration-300"
                :class="isDark ? 'bg-gray-300' : 'bg-brand-green'">
            <span class="grid h-6 w-6 place-items-center rounded-full bg-white shadow-md transition-transform duration-300"
                  :class="isDark ? 'translate-x-0' : 'translate-x-[24px]'">
                <svg x-show="! isDark" class="h-3.5 w-3.5 fill-none stroke-brand-green stroke-2" aria-hidden="true"><use href="#i-sun"/></svg>
                <svg x-cloak x-show="isDark" class="h-3.5 w-3.5 fill-none stroke-gray-600 stroke-2" aria-hidden="true"><use href="#i-moon"/></svg>
            </span>
        </button>

        <div class="relative" x-data="{ open: false }" @click.outside="open = false"
             @keydown.escape.window="if (open) { open = false; $refs.profil.focus() }">
            <button type="button" x-ref="profil" @click="open = ! open" :aria-expanded="open ? 'true' : 'false'"
                    class="flex h-10 items-center gap-2.5 rounded-md pl-1.5 pr-2 transition hover:bg-gray-100 dark:hover:bg-zinc-800 sm:pr-3">
                <span class="grid h-8 w-8 place-items-center rounded-md bg-brand-dark font-display text-sm font-bold text-white dark:bg-zinc-700">{{ $pengguna->initials() }}</span>
                <span class="hidden text-left leading-tight sm:block">
                    <span class="block text-sm font-semibold dark:text-zinc-100">{{ $pengguna->name }}</span>
                    <span class="block text-xs text-gray-500 dark:text-zinc-400">{{ $role }}</span>
                </span>
                <svg class="hidden h-4 w-4 fill-none stroke-current stroke-2 text-gray-500 dark:text-zinc-400 sm:block" aria-hidden="true"><use href="#i-chev"/></svg>
            </button>

            <div x-cloak x-show="open" x-transition.opacity
                 class="absolute right-0 top-full z-30 mt-1.5 w-48 rounded-md border border-gray-200 bg-white py-1.5 shadow-lg dark:border-zinc-800 dark:bg-zinc-900">
                <a href="{{ route('profile.edit') }}" wire:navigate
                   class="flex items-center gap-2.5 px-3.5 py-2.5 text-sm font-medium transition hover:bg-gray-50 dark:text-zinc-200 dark:hover:bg-zinc-800">
                    <svg class="h-4 w-4 fill-none stroke-current stroke-2 text-gray-500 dark:text-zinc-400" aria-hidden="true"><use href="#i-user-cog"/></svg>
                    Pengaturan Akun
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" data-test="logout-button"
                            class="flex w-full items-center gap-2.5 px-3.5 py-2.5 text-left text-sm font-medium text-brand-green transition hover:bg-gray-50 dark:hover:bg-zinc-800">
                        <svg class="h-4 w-4 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-log-out"/></svg>
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
