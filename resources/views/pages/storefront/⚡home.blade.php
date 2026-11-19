<?php

use App\Models\AboutImage;
use App\Models\Category;
use App\Models\Product;
use App\Models\Slide;
use App\Models\Testimonial;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::storefront')] class extends Component
{
    public function rendering(View $view): void
    {
        $view->title(__('Beranda'));
    }

    #[Computed]
    public function slides()
    {
        return Slide::query()->active()->orderBy('sort_order')->get();
    }

    #[Computed]
    public function aboutImages()
    {
        return AboutImage::query()->tampil()->get();
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->where('is_active', true)->withCount('products')->orderBy('id')->get();
    }

    #[Computed]
    public function promilProducts()
    {
        return $this->productsInCategory(Category::PROMIL_SLUG);
    }

    #[Computed]
    public function herbalProducts()
    {
        return $this->productsInCategory(Category::HERBAL_SLUG);
    }

    private function productsInCategory(string $slug)
    {
        return Product::query()->active()
            ->with('category', 'variants', 'images')
            ->whereHas('category', fn ($q) => $q->where('slug', $slug))
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function testimonials()
    {
        return Testimonial::query()->whereNotNull('content')->orderBy('id')->get();
    }
}; ?>

@php
    $inisial = fn (string $nama): string => collect(explode(' ', trim($nama)))
        ->filter()
        ->take(2)
        ->map(fn ($kata) => mb_strtoupper(mb_substr($kata, 0, 1)))
        ->implode('');

    $catalogUrl = route('products.index');
    $slides = $this->slides;
    $gridCategories = $this->categories;
@endphp

<div>
    <h1 class="sr-only">{{ setting('site_name', 'Jamu Mak Yuli') }}: {{ setting('site_tagline', __('Semangat menjemput buah hati ❤️')) }}</h1>

    <section aria-label="{{ __('Sorotan') }} {{ setting('site_name', 'Jamu Mak Yuli') }}" class="container-x pb-3 pt-6">
        <div class="relative select-none overflow-hidden rounded-xl bg-brand-dark">
            @if ($slides->count() > 1)
                <div x-data="{
                        active: 0,
                        count: {{ $slides->count() }},
                        timer: null,
                        start() {
                            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                            this.stop();
                            this.timer = setInterval(() => this.next(), 5000);
                        },
                        stop() { if (this.timer) { clearInterval(this.timer); this.timer = null; } },
                        next() { this.active = (this.active + 1) % this.count; },
                        prev() { this.active = (this.active - 1 + this.count) % this.count; },
                     }"
                     x-init="start()" @mouseenter="stop()" @mouseleave="start()"
                     class="relative grid">
                    @foreach ($slides as $i => $slide)
                        <div class="relative col-start-1 row-start-1 h-[262px] w-full sm:h-[356px] lg:h-[416px]"
                             x-show="active === {{ $i }}" x-transition.opacity.duration.500ms @if ($i !== 0) x-cloak @endif>
                            <img src="{{ $slide->image_url }}" alt="{{ $slide->title ?? setting('site_name', 'Jamu Mak Yuli') }}"
                                 class="absolute inset-0 h-full w-full object-cover" @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif>
                            <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/45 to-transparent"></div>
                            @if (filled($slide->title) || filled($slide->content))
                                <div class="relative flex h-full max-w-xl flex-col justify-center pl-16 pr-14 sm:pl-20 sm:pr-16">
                                    @if (filled($slide->title))
                                        <h2 class="font-display text-[17.6px] font-medium leading-[1.02] tracking-tight text-white sm:text-[26.4px] lg:text-[33px]">{{ $slide->title }}</h2>
                                    @endif
                                    @if (filled($slide->content))
                                        <p class="mt-3 max-w-md text-sm text-white/75 sm:text-base">{{ $slide->content }}</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                    <button type="button" @click="prev(); start()" aria-label="{{ __('Sebelumnya') }}"
                            class="absolute left-3 top-1/2 z-20 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-brand-dark shadow-md transition-colors hover:bg-white">
                        <svg class="h-5 w-5" aria-hidden="true"><use href="#i-chev-left"/></svg>
                    </button>
                    <button type="button" @click="next(); start()" aria-label="{{ __('Berikutnya') }}"
                            class="absolute right-3 top-1/2 z-20 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-brand-dark shadow-md transition-colors hover:bg-white">
                        <svg class="h-5 w-5" aria-hidden="true"><use href="#i-chev-right"/></svg>
                    </button>
                    <div class="absolute bottom-0 left-1/2 z-20 flex -translate-x-1/2 items-center">
                        @foreach ($slides as $i => $slide)
                            <button type="button" @click="active = {{ $i }}; start()" aria-label="{{ __('Ke slide') }} {{ $i + 1 }}"
                                    class="grid h-11 place-items-center px-2">
                                <span class="block h-2.5 rounded-full transition-all duration-300"
                                      :class="active === {{ $i }} ? 'w-6 bg-white' : 'w-2.5 bg-white/40'"></span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @elseif ($slides->count() === 1)
                <div class="relative h-[262px] w-full sm:h-[356px] lg:h-[416px]">
                    <img src="{{ $slides->first()->image_url }}" alt="{{ $slides->first()->title ?? setting('site_name', 'Jamu Mak Yuli') }}"
                         class="absolute inset-0 h-full w-full object-cover" fetchpriority="high">
                    <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/45 to-transparent"></div>
                    @if (filled($slides->first()->title) || filled($slides->first()->content))
                        <div class="relative flex h-full max-w-xl flex-col justify-center pl-16 pr-14 sm:pl-20 sm:pr-16">
                            @if (filled($slides->first()->title))
                                <h2 class="font-display text-[17.6px] font-medium leading-[1.02] tracking-tight text-white sm:text-[26.4px] lg:text-[33px]">{{ $slides->first()->title }}</h2>
                            @endif
                            @if (filled($slides->first()->content))
                                <p class="mt-3 max-w-md text-sm text-white/75 sm:text-base">{{ $slides->first()->content }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            @else
                <div class="ph h-[262px] w-full sm:h-[356px] lg:h-[416px]" role="img"
                     aria-label="{{ setting('site_name', 'Jamu Mak Yuli') }}. {{ __('Ramuan rempah kering untuk ikhtiar program hamil pasutri.') }}">
                    <div class="ph-cap"><svg class="h-8 w-8" aria-hidden="true"><use href="#i-image"/></svg></div>
                </div>
            @endif
        </div>
    </section>

    <x-trust-bar class="mt-8 md:mt-10" />

    <section id="kategori" aria-label="{{ __('Kategori Produk') }}" class="container-x scroll-mt-24 py-12 md:py-14">
        @php
            $kolomKategori = match (min($gridCategories->count(), 4)) {
                1 => 'mx-auto max-w-sm grid-cols-1',
                2 => 'mx-auto max-w-3xl grid-cols-2',
                3 => 'grid-cols-2 lg:grid-cols-3',
                default => 'grid-cols-2 lg:grid-cols-4',
            };
        @endphp
        <ul class="grid gap-x-4 gap-y-8 md:gap-y-10 {{ $kolomKategori }}">
            @foreach ($gridCategories as $category)
                <li>
                    <a href="{{ route('products.index', ['category' => $category->slug]) }}" wire:navigate
                       class="group flex flex-col items-center text-center">
                        <h3 class="mb-2.5 font-display text-sm font-medium text-brand-dark dark:text-zinc-100 md:text-base">{{ $category->name }}</h3>
                        <div class="flex aspect-[4/3] w-full items-center justify-center overflow-hidden rounded-xl">
                            @if ($category->thumbnailUrl())
                                <img src="{{ $category->thumbnailUrl() }}" alt="{{ $category->name }}"
                                     class="max-h-[75%] max-w-[75%] object-contain" loading="lazy">
                            @else
                                <div class="ph grid h-full w-full place-items-center rounded-xl" role="img" aria-label="{{ __('Ilustrasi') }} {{ $category->name }}">
                                    <div class="ph-cap"><svg class="h-6 w-6" aria-hidden="true"><use href="#i-image"/></svg></div>
                                </div>
                            @endif
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    <div id="produk">
        @if ($this->promilProducts->isNotEmpty())
            <div class="mt-12 bg-brand-pink-soft pb-10 dark:bg-zinc-900 md:mt-20 md:pb-14">
                <x-product-rail id="sorotan-promil" :heading="__('Paket Promil Pasutri')" :products="$this->promilProducts" :href="$catalogUrl" pad="pt-10 md:pt-14" />
                <p class="container-x mt-6 max-w-2xl text-[15px] text-body-2 dark:text-zinc-300">{{ __('Pilih Paket Promil Ideal bila berat badan istri di bawah 60 kg, dan Paket Promil Gemoy bila di atas 60 kg.') }}</p>
            </div>
        @endif

        <x-product-rail :heading="__('Ramuan Rempah')" :products="$this->herbalProducts" :href="$catalogUrl" pad="pt-12 md:pt-16" />
    </div>

    <section aria-labelledby="sekilas-h" class="container-x mt-12 md:mt-20">
        <div class="mb-7 max-w-2xl">
            <h2 id="sekilas-h" class="sec-tick font-display text-[16px] font-semibold tracking-tight dark:text-zinc-100 md:text-[19px]">{{ __('Sekilas Tentang :toko', ['toko' => setting('site_name', 'Jamu Mak Yuli')]) }}</h2>
            <p class="mt-4 text-[15px] leading-relaxed text-body-2 dark:text-zinc-300">{{ __('Jamu Mak Yuli adalah ramuan jamu rempah tradisional yang berfokus pada kesehatan dan program hamil. Berawal dari jamu rumahan pada 2020, kini kami menyajikannya dalam bentuk rempah kering yang bisa Anda rebus sendiri di rumah.') }}</p>
            <a href="{{ route('pages.show', 'about') }}" wire:navigate class="link-underline mt-3 inline-flex min-h-11 items-center font-display text-sm font-bold text-brand-green">{{ __('Kenali Jamu Mak Yuli') }}</a>
        </div>
        @if ($this->aboutImages->isNotEmpty())
        <div class="grid auto-rows-[130px] grid-cols-2 gap-3 sm:auto-rows-[150px] md:auto-rows-[180px] md:grid-cols-4 md:gap-4">
            @foreach ($this->aboutImages as $i => $gambar)
                <div class="overflow-hidden rounded-xl {{ $i === 0 ? 'col-span-2 row-span-2' : '' }}">
                    <img src="{{ $gambar->image_url }}" alt="{{ __('Ilustrasi') }} {{ setting('site_name', 'Jamu Mak Yuli') }}"
                         loading="lazy" decoding="async" class="h-full w-full object-cover">
                </div>
            @endforeach
        </div>
        @endif
    </section>

    @if ($this->testimonials->isNotEmpty())
    <section id="testimoni" aria-labelledby="testimoni-h" class="scroll-mt-24 -mb-14 pt-12 pb-[84px] md:-mb-24 md:pt-16"
             x-data="{
                 pos: 0,
                 count: {{ $this->testimonials->count() }},
                 perView: 4,
                 step: 1,
                 timer: null,
                 onResize: null,
                 get max() { return Math.max(0, this.count - this.perView) },
                 calc() {
                     if (window.innerWidth < 1024) { this.perView = 2; this.step = 2; }
                     else { this.perView = 4; this.step = 1; }
                     if (this.pos > this.max) this.pos = 0;
                 },
                 next() { this.pos = this.pos + this.step > this.max ? 0 : this.pos + this.step },
                 prev() { this.pos = this.pos - this.step < 0 ? this.max : this.pos - this.step },
                 start() {
                     if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                     this.stop();
                     this.timer = setInterval(() => this.next(), 4000);
                 },
                 stop() { if (this.timer) { clearInterval(this.timer); this.timer = null; } },
                 destroy() {
                     this.stop();
                     window.removeEventListener('resize', this.onResize);
                 },
             }"
             x-init="calc(); start(); onResize = () => calc(); window.addEventListener('resize', onResize)"
             @mouseenter="stop()" @mouseleave="start()">
    <div class="container-x">
        <div class="mb-7 flex items-end justify-between gap-4">
            <div>
                <h2 id="testimoni-h" class="sec-tick font-display text-[16px] font-semibold tracking-tight text-brand-dark dark:text-zinc-100 md:text-[19px]">{{ __('Testimoni Pelanggan') }}</h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-zinc-400">{{ __('Cerita dari pelanggan Jamu Mak Yuli.') }}</p>
            </div>
            <div class="hidden shrink-0 items-center gap-2 sm:flex">
                <button type="button" @click="prev(); start()" aria-label="{{ __('Sebelumnya') }}" class="grid h-11 w-11 place-items-center rounded-md border-2 border-brand-dark text-brand-dark transition-colors hover:bg-brand-dark hover:text-white dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700">
                    <svg class="h-5 w-5" aria-hidden="true"><use href="#i-chev-left"/></svg>
                </button>
                <button type="button" @click="next(); start()" aria-label="{{ __('Berikutnya') }}" class="grid h-11 w-11 place-items-center rounded-md border-2 border-brand-dark text-brand-dark transition-colors hover:bg-brand-dark hover:text-white dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700">
                    <svg class="h-5 w-5" aria-hidden="true"><use href="#i-chev-right"/></svg>
                </button>
            </div>
        </div>
        <div class="overflow-hidden py-4">
            <div class="-mx-1.5 overflow-hidden md:-mx-2"
                 @touchstart="$data.tx = $event.changedTouches[0].clientX"
                 @touchend="let dx = $event.changedTouches[0].clientX - $data.tx; if (Math.abs(dx) > 40) { dx < 0 ? next() : prev(); start(); }">
                <div class="flex transition-transform duration-500 ease-out" :style="`transform: translateX(-${pos * (100 / perView)}%)`">
                    @foreach ($this->testimonials as $testimonial)
                        <div class="w-full shrink-0 px-1.5 sm:w-1/2 md:px-2 lg:w-1/4" wire:key="testimonial-{{ $testimonial->id }}">
                            <div class="relative flex h-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                                <svg class="pointer-events-none absolute right-4 top-3 h-11 w-11 text-brand-green/[0.06]" aria-hidden="true"><use href="#i-quote"/></svg>
                                <div class="relative flex items-center gap-0.5">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <svg class="h-4 w-4 {{ $i <= $testimonial->rating ? 'text-brand-turmeric-ink' : 'text-gray-300 dark:text-zinc-700' }}" aria-hidden="true"><use href="#i-star"/></svg>
                                    @endfor
                                </div>
                                <p class="relative mt-3 flex-1 text-sm leading-relaxed text-gray-600 dark:text-zinc-300">{{ $testimonial->content }}</p>
                                @if ($testimonial->image_url)
                                    <div x-data="productLightbox()" class="relative mt-4">
                                        <a href="{{ $testimonial->image_url }}" target="_blank" rel="noopener" x-on:click.prevent="open(0, [$el.href])"
                                           aria-label="{{ __('Perbesar gambar testimoni dari :nama', ['nama' => $testimonial->name]) }}"
                                           class="-mx-6 block aspect-[4/3] cursor-zoom-in overflow-hidden border-t border-gray-100 bg-gray-50 dark:border-zinc-800 dark:bg-zinc-800">
                                            <img src="{{ $testimonial->image_url }}" alt="" loading="lazy" class="size-full object-cover object-top">
                                        </a>
                                        <x-product-lightbox :label="__('Gambar testimoni')" />
                                    </div>
                                @endif
                                <div class="relative mt-5 flex items-center gap-3 border-t border-gray-100 pt-5 dark:border-zinc-800">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-brand-dark font-display text-sm font-bold text-white dark:bg-zinc-700">{{ $inisial($testimonial->name) }}</span>
                                    <div class="min-w-0">
                                        <p class="truncate font-display text-sm font-medium leading-tight dark:text-zinc-100">{{ $testimonial->name }}</p>
                                        @if ($testimonial->role)
                                            <p class="truncate text-xs text-gray-500 dark:text-zinc-400">{{ $testimonial->role }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="mt-6 flex items-center justify-center gap-2 sm:hidden">
            <button type="button" @click="prev(); start()" aria-label="{{ __('Sebelumnya') }}" class="grid h-11 w-11 place-items-center rounded-md border-2 border-brand-dark text-brand-dark transition-colors hover:bg-brand-dark hover:text-white dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700">
                <svg class="h-5 w-5" aria-hidden="true"><use href="#i-chev-left"/></svg>
            </button>
            <button type="button" @click="next(); start()" aria-label="{{ __('Berikutnya') }}" class="grid h-11 w-11 place-items-center rounded-md border-2 border-brand-dark text-brand-dark transition-colors hover:bg-brand-dark hover:text-white dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700">
                <svg class="h-5 w-5" aria-hidden="true"><use href="#i-chev-right"/></svg>
            </button>
        </div>
    </div>
</section>
    @endif
</div>
