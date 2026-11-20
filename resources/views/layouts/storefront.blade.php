@php
    use App\Models\Category;
    use App\Models\Page;

    $navCategories = Category::query()->where('is_active', true)->orderBy('id')->get();
    $footerPages = Page::query()->where('is_active', true)->whereNotIn('slug', ['faq', 'about', 'privacy-policy'])->orderBy('created_at', 'asc')->get();

    $footerAbout = (string) setting('site_footer_about', 'Ramuan rempah kering untuk ikhtiar program hamil, sejak 2020.');
    $siteName = setting('site_name', 'Jamu Mak Yuli');
    $waLink = wa_href() ?? '#';
    $homeUrl = route('home', absolute: false);
    $catalogUrl = route('products.index');

    $socials = array_filter([
        'instagram' => setting('site_instagram'),
        'facebook' => setting('site_facebook'),
        'tiktok' => setting('site_tiktok'),
        'twitter' => setting('site_twitter'),
        'threads' => setting('site_threads'),
    ]);

    $marketplace = array_filter([
        'tokopedia' => setting('site_tokopedia'),
        'shopee' => setting('site_shopee'),
    ]);

    $hideBottomNav = request()->routeIs('checkout', 'products.show', 'cart.index');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="storefront bg-white text-ink antialiased dark:bg-zinc-950 dark:text-zinc-200">

        <a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-[100] focus:rounded-lg focus:bg-brand-green focus:px-4 focus:py-2 focus:text-white">{{ __('Lewati ke konten') }}</a>

        @include('partials.storefront.icons')

        <header id="site-header" class="sticky top-0 z-50 bg-white shadow-sm dark:bg-zinc-900 md:border-b md:border-gray-200 md:dark:border-zinc-800">
            <div class="container-x flex h-[84px] items-center gap-1 sm:gap-4">
                <a href="{{ $homeUrl }}" wire:navigate class="flex shrink-0 items-center" aria-label="{{ $siteName }} {{ __('beranda') }}">
                    <x-wordmark size="text-xl sm:text-[28px]" class="text-brand-dark dark:text-zinc-100" />
                </a>

                <div class="relative shrink-0" x-data="{ open: false }" @click.outside="open = false"
                     @keydown.escape.window="if (open) { open = false; $refs.catBtn.focus() }">
                    <button type="button" x-ref="catBtn" @click="open = ! open"
                            @keydown.down.prevent="open = true; $nextTick(() => $refs.catFirst.focus())"
                            :aria-expanded="open ? 'true' : 'false'"
                            aria-haspopup="true" aria-label="{{ __('Kategori') }}"
                            class="grid h-10 w-10 place-items-center rounded-md transition hover:bg-gray-100 dark:hover:bg-zinc-800">
                        <svg class="h-6 w-6 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-menu"/></svg>
                    </button>
                    <div x-cloak x-show="open" x-transition.opacity
                         class="absolute left-0 top-full z-50 mt-2 w-60 rounded-xl border border-gray-200 bg-white py-2 shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                        <p class="px-4 pb-2 pt-1 font-display text-xs font-bold tracking-widest text-gray-500 dark:text-zinc-400">{{ __('Kategori') }}</p>
                        @foreach ($navCategories as $category)
                            <a href="{{ route('products.index', ['category' => $category->slug]) }}" wire:navigate
                               @if ($loop->first) x-ref="catFirst" @endif
                               class="block px-4 py-2.5 text-sm font-medium transition hover:bg-gray-50 dark:hover:bg-zinc-800">{{ $category->name }}</a>
                        @endforeach
                    </div>
                </div>

                <form action="{{ $catalogUrl }}" method="GET" role="search"
                      class="mx-2 hidden h-11 max-w-xl flex-1 overflow-hidden rounded-md border-2 border-brand-dark dark:border-zinc-600 md:flex">
                    <label for="hdr-search" class="sr-only">{{ __('Cari produk') }}</label>
                    <input id="hdr-search" type="search" name="q" value="{{ request('q') }}"
                           placeholder="{{ __('Cari jamu atau ramuan...') }}"
                           class="flex-1 bg-transparent px-4 text-sm outline-none placeholder:text-gray-400 dark:text-zinc-100 dark:placeholder:text-zinc-500">
                    <button type="submit" class="grid place-items-center bg-brand-green px-6 text-white transition hover:bg-brand-green-dark" aria-label="{{ __('Cari') }}">
                        <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-search"/></svg>
                    </button>
                </form>

                <div class="ml-auto flex items-center gap-0 sm:gap-1">
                    <a href="{{ route('wishlist.index') }}" wire:navigate aria-label="{{ __('Wishlist') }}"
                       class="relative grid h-10 w-10 place-items-center rounded-md transition hover:bg-gray-100 dark:hover:bg-zinc-800">
                        <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-heart"/></svg>
                        @livewire('pages::storefront.wishlist-counter')
                    </a>

                    <a href="{{ route('cart.index') }}" wire:navigate aria-label="{{ __('Keranjang') }}"
                       class="relative grid h-10 w-10 place-items-center rounded-md transition hover:bg-gray-100 dark:hover:bg-zinc-800">
                        <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-cart"/></svg>
                        @livewire('pages::storefront.cart-counter')
                    </a>

                    <x-theme-toggle class="mx-1 hidden md:inline-flex" />

                    @auth
                        <livewire:notification-bell position="bottom" align="end" />
                        <a href="{{ route('dashboard') }}" wire:navigate aria-label="{{ __('Akun') }}"
                           class="hidden h-10 items-center gap-2 rounded-md px-3 transition hover:bg-gray-100 dark:hover:bg-zinc-800 md:flex">
                            <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-user"/></svg>
                            <span class="text-sm font-medium">{{ __('Akun') }}</span>
                        </a>
                    @else
                        <a href="{{ route('login') }}" wire:navigate aria-label="{{ __('Masuk') }}"
                           class="hidden h-10 items-center gap-2 rounded-md px-3 transition hover:bg-gray-100 dark:hover:bg-zinc-800 md:flex">
                            <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-user"/></svg>
                            <span class="text-sm font-medium">{{ __('Masuk') }}</span>
                        </a>
                        <div class="ml-2 hidden sm:inline-flex">
                            <x-btn variant="outline" :href="route('register')" wire:navigate>{{ __('Daftar') }}</x-btn>
                        </div>
                    @endauth

                </div>
            </div>
        </header>

        <div id="mobile-search" class="flex items-center gap-3 border-b border-gray-200 bg-white px-5 py-3 dark:border-zinc-800 dark:bg-zinc-900 md:hidden">
            <form action="{{ $catalogUrl }}" method="GET" role="search"
                  class="flex h-11 min-w-0 flex-1 overflow-hidden rounded-md border-2 border-brand-dark dark:border-zinc-600">
                <label for="m-search-input" class="sr-only">{{ __('Cari produk') }}</label>
                <input id="m-search-input" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Cari jamu...') }}"
                       class="flex-1 bg-transparent px-4 text-sm outline-none placeholder:text-gray-400 dark:text-zinc-100 dark:placeholder:text-zinc-500">
                <button type="submit" class="grid place-items-center bg-brand-green px-5 text-white" aria-label="{{ __('Cari') }}">
                    <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-search"/></svg>
                </button>
            </form>
            <x-theme-toggle class="inline-flex" />
        </div>

        <main id="konten">
            {{ $slot }}
        </main>

        <footer class="footer-site mt-14 bg-brand-dark text-white md:mt-24">
            <div class="container-x grid grid-cols-2 gap-10 pb-10 pt-14 md:grid-cols-4 xl:grid-cols-[1.25fr_1fr_1fr_1fr_1.35fr]">
                <div class="col-span-2 xl:col-span-1">
                    <a href="{{ $homeUrl }}" wire:navigate aria-label="{{ $siteName }} {{ __('beranda') }}" class="mb-4 inline-block">
                        <x-wordmark size="text-lg" class="text-white" />
                    </a>
                    <p class="max-w-xs text-sm leading-relaxed text-white/60">{{ $footerAbout }}</p>
                </div>

                <nav aria-label="{{ __('Navigasi') }}">
                    <h2 class="mb-4 font-display text-sm font-bold tracking-widest text-brand-green">{{ __('Navigasi') }}</h2>
                    <ul class="space-y-1 text-sm text-white/70">
                        <li><a href="{{ $homeUrl }}" wire:navigate class="inline-flex min-h-7 items-center transition hover:text-white">{{ __('Beranda') }}</a></li>
                        <li><a href="{{ $catalogUrl }}" wire:navigate class="inline-flex min-h-7 items-center transition hover:text-white">{{ __('Semua Produk') }}</a></li>
                        <li><a href="{{ route('categories') }}" wire:navigate class="inline-flex min-h-7 items-center transition hover:text-white">{{ __('Kategori') }}</a></li>
                        <li><a href="{{ route('track') }}" wire:navigate class="inline-flex min-h-7 items-center transition hover:text-white">{{ __('Lacak Pesanan') }}</a></li>
                        <li><a href="{{ route('kontak') }}" wire:navigate class="inline-flex min-h-7 items-center transition hover:text-white">{{ __('Hubungi Kami') }}</a></li>
                    </ul>
                </nav>

                <nav aria-label="{{ __('Kategori') }}">
                    <h2 class="mb-4 font-display text-sm font-bold tracking-widest text-brand-green">{{ __('Kategori') }}</h2>
                    <ul class="space-y-1 text-sm text-white/70">
                        @foreach ($navCategories->take(4) as $category)
                            <li><a href="{{ route('products.index', ['category' => $category->slug]) }}" wire:navigate class="inline-flex min-h-7 items-center transition hover:text-white">{{ $category->name }}</a></li>
                        @endforeach
                    </ul>
                </nav>

                <nav aria-label="{{ __('Bantuan') }}">
                    <h2 class="mb-4 font-display text-sm font-bold tracking-widest text-brand-green">{{ __('Bantuan') }}</h2>
                    <ul class="space-y-1 text-sm text-white/70">
                        <li><a href="{{ route('pages.show', 'faq') }}" wire:navigate class="inline-flex min-h-7 items-center transition hover:text-white">{{ __('FAQ') }}</a></li>
                        <li><a href="{{ route('pages.show', 'about') }}" wire:navigate class="inline-flex min-h-7 items-center transition hover:text-white">{{ __('Tentang Kami') }}</a></li>
                        @foreach ($footerPages as $page)
                            <li><a href="{{ route('pages.show', $page->slug) }}" wire:navigate class="inline-flex min-h-7 items-center transition hover:text-white">{{ $page->title }}</a></li>
                        @endforeach
                        <li><a href="{{ route('pages.show', 'privacy-policy') }}" wire:navigate class="inline-flex min-h-7 items-center transition hover:text-white">{{ __('Kebijakan Privasi') }}</a></li>
                    </ul>
                </nav>

                <div class="col-span-2 xl:col-span-1">
                    <h2 class="mb-4 font-display text-sm font-bold tracking-widest text-brand-green">{{ __('Customer Care') }}</h2>
                    @if (setting('site_whatsapp'))
                        <a href="{{ $waLink }}" target="_blank" rel="noopener" class="mb-2.5 flex items-center gap-2 text-sm text-white/80 transition hover:text-white">
                            <svg class="h-4 w-4 shrink-0 fill-current" aria-hidden="true"><use href="#i-wa"/></svg>
                            <span class="min-w-0 [overflow-wrap:anywhere]">{{ setting('site_whatsapp') }}</span>
                        </a>
                    @endif
                    @if (setting('site_email'))
                        <a href="mailto:{{ setting('site_email') }}" class="mb-2.5 flex items-center gap-2 text-sm text-white/80 transition hover:text-white">
                            <svg class="h-4 w-4 shrink-0 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-mail"/></svg>
                            <span class="min-w-0 [overflow-wrap:anywhere]">{{ setting('site_email') }}</span>
                        </a>
                    @endif
                    @if (setting('site_hours'))
                        <p class="mb-2.5 flex items-start gap-2 text-sm text-white/80">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-clock"/></svg>
                            <span>{{ setting('site_hours') }}</span>
                        </p>
                    @endif
                    @if (setting('site_address'))
                        <a href="{{ setting('site_maps_url') ?: '#' }}" target="_blank" rel="noopener" class="flex items-start gap-2 text-sm text-white/80 transition hover:text-white">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-pin"/></svg>
                            <span class="min-w-0">{{ setting('site_address') }}</span>
                        </a>
                    @endif
                    @if ($socials)
                        <div class="mt-5 flex gap-2">
                            @foreach ($socials as $platform => $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $platform === 'twitter' ? 'X' : ucfirst($platform) }} {{ $siteName }}"
                                   class="grid h-9 w-9 place-items-center rounded-md bg-white/10 text-white/80 transition hover:bg-brand-green hover:text-white">
                                    <svg class="h-[18px] w-[18px] fill-current" aria-hidden="true"><use href="#i-{{ $platform }}"/></svg>
                                </a>
                            @endforeach
                            @foreach ($marketplace as $platform => $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($platform) }} {{ $siteName }}"
                                   class="grid h-9 w-9 place-items-center rounded-md bg-white/10 text-white/80 transition hover:bg-brand-green hover:text-white">
                                    <svg class="h-[18px] w-[18px] fill-current" aria-hidden="true"><use href="#i-{{ $platform }}"/></svg>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="border-t border-white/10">
                <div class="container-x py-5 text-center text-xs text-white/70">
                    <p>{{ __('Hak cipta') }} &copy; {{ date('Y') }} {{ $siteName }}. {{ __('Seluruh hak dilindungi.') }}</p>
                </div>
            </div>
        </footer>

        @unless ($hideBottomNav)
        <nav class="bnav-safe fixed bottom-0 left-0 right-0 z-50 border-t border-black/10 bg-white/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95 md:hidden" aria-label="{{ __('Navigasi mobile') }}">
            <ul class="grid h-[58px] grid-cols-5">
                <li><a href="{{ $homeUrl }}" wire:navigate @class(['bnav', 'is-active' => request()->routeIs('home')])><svg class="h-[22px] w-[22px] fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-home"/></svg><span>{{ __('Beranda') }}</span></a></li>
                <li><a href="{{ route('categories') }}" wire:navigate @class(['bnav', 'is-active' => request()->routeIs('categories')])><svg class="h-[22px] w-[22px] fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-grid"/></svg><span>{{ __('Kategori') }}</span></a></li>
                <li><a href="{{ $catalogUrl }}" wire:navigate @class(['bnav', 'is-active' => request()->routeIs('products.*')])><svg class="h-[22px] w-[22px] fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-package"/></svg><span>{{ __('Produk') }}</span></a></li>
                <li><a href="{{ route('track') }}" wire:navigate @class(['bnav', 'is-active' => request()->routeIs('track')])><svg class="h-[22px] w-[22px] fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-track"/></svg><span>{{ __('Lacak') }}</span></a></li>
                <li><a href="{{ auth()->check() ? route('dashboard') : route('login') }}" wire:navigate @class(['bnav', 'is-active' => request()->routeIs('dashboard')])><svg class="h-[22px] w-[22px] fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-user"/></svg><span>{{ __('Akun') }}</span></a></li>
            </ul>
        </nav>
        @endunless

        <a href="{{ $waLink }}" target="_blank" rel="noopener" aria-label="{{ __('Chat WhatsApp') }} {{ $siteName }}"
           class="wa-fab fixed bottom-6 right-4 z-50 grid h-12 w-12 place-items-center rounded-full bg-[#0E7C6F] text-white shadow-card-hover transition-transform hover:scale-105 lg:h-14 lg:w-14">
            <svg class="h-6 w-6 fill-current lg:h-7 lg:w-7" aria-hidden="true"><use href="#i-wa"/></svg>
        </a>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        <script>
            (function () {
                var header = document.getElementById('site-header');
                function onScroll() {
                    if (!header) return;
                    header.classList.toggle('shadow-card', window.scrollY > 8);
                }
                document.addEventListener('scroll', onScroll, { passive: true });
                onScroll();
            })();
        </script>

        @fluxScripts
    </body>
</html>
