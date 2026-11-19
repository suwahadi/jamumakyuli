@props([
    'menu' => [],
    'home' => null,
    'subtitle' => '',
])

@php
    $beranda = $home ?? route('home');

    $aktifDiGrup = function (array $entri): bool {
        foreach ($entri['items'] ?? [] as $item) {
            if (request()->routeIs(...(array) ($item['match'] ?? $item['route']))) {
                return true;
            }
        }

        return false;
    };
@endphp

<aside class="fixed top-0 z-50 flex h-screen w-[264px] shrink-0 flex-col bg-brand-dark text-white transition-transform duration-300 lg:sticky lg:z-30 lg:translate-x-0"
       :class="sidebar ? 'translate-x-0' : '-translate-x-full'">
    <div class="flex h-[64px] shrink-0 items-center gap-2 border-b border-white/10 px-5">
        <a href="{{ $beranda }}" wire:navigate class="flex flex-col leading-none">
            <x-wordmark accent="text-green-300" class="text-white" />
            <span class="mt-1 text-xs font-semibold text-white/60">{{ $subtitle }}</span>
        </a>
        <button type="button" @click="sidebar = false" aria-label="Tutup menu"
                class="ml-auto grid h-9 w-9 place-items-center rounded-md hover:bg-white/10 lg:hidden">
            <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-x"/></svg>
        </button>
    </div>

    <nav class="thin-bar flex-1 space-y-1 overflow-y-auto px-3 py-4">
        @foreach ($menu as $entri)
            @if (isset($entri['route']))
                <a href="{{ route($entri['route']) }}" wire:navigate @click="sidebar = false"
                   @class([
                       'nav-link nav-item relative flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium text-white/90 hover:bg-white/10',
                       'active' => request()->routeIs(...(array) ($entri['match'] ?? $entri['route'])),
                   ])>
                    <span class="nav-bar"></span>
                    <svg class="h-[18px] w-[18px] shrink-0 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#{{ $entri['icon'] }}"/></svg>
                    <span class="truncate">{{ $entri['label'] }}</span>
                </a>
            @else
                <div class="pt-2" x-data="{ open: @js($aktifDiGrup($entri)) }">
                    <button type="button" @click="open = ! open" :aria-expanded="open ? 'true' : 'false'"
                            class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-white/70 transition hover:bg-white/5 hover:text-white">
                        <svg class="h-[18px] w-[18px] shrink-0 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#{{ $entri['icon'] }}"/></svg>
                        <span class="flex-1 text-left font-display text-[13px] font-semibold">{{ $entri['group'] }}</span>
                        <svg class="h-4 w-4 shrink-0 fill-none stroke-current stroke-2 transition-transform duration-250" :class="open && 'rotate-180'" aria-hidden="true"><use href="#i-chev"/></svg>
                    </button>

                    <div class="grid transition-[grid-template-rows] duration-250" :class="open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'">
                        <div class="overflow-hidden">
                            <div class="space-y-0.5 pl-2 pt-1">
                                @foreach ($entri['items'] as $item)
                                    <a href="{{ route($item['route']) }}" wire:navigate @click="sidebar = false"
                                       @class([
                                           'nav-link nav-item relative flex items-center gap-3 rounded-md py-2 pl-3 pr-3 text-[13px] text-white/70 hover:bg-white/10 hover:text-white',
                                           'active' => request()->routeIs(...(array) ($item['match'] ?? $item['route'])),
                                       ])>
                                        <span class="nav-bar"></span>
                                        <svg class="h-[18px] w-[18px] shrink-0 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#{{ $item['icon'] }}"/></svg>
                                        <span class="truncate">{{ $item['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </nav>

    <div class="shrink-0 border-t border-white/10 p-3">
        {{ $slot }}
    </div>
</aside>

<div x-cloak x-show="sidebar" x-transition.opacity @click="sidebar = false"
     class="fixed inset-0 z-40 bg-black/50 lg:hidden"></div>
