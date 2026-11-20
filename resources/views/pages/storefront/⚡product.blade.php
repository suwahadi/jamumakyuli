<?php

use App\Concerns\InteractsWithCart;
use App\Concerns\InteractsWithWishlist;
use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\Variant;
use App\Services\CartService;
use App\Services\WishlistService;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::storefront')] class extends Component
{
    use InteractsWithCart;
    use InteractsWithWishlist;

    public Product $product;

    public ?int $variantId = null;

    public $quantity = 1;

    public string $customText = '';

    public ?string $activeImage = null;

    public function mount(Product $product): void
    {
        abort_unless($product->is_active, 404);

        $this->product = $product->load('variants.image', 'category', 'images');
        $this->variantId = $product->variants->first()?->id;
        $this->activeImage = $this->resolveVariantImage($this->variantId);
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name);

        view()->share([
            'seoDescription' => (string) Str::of($this->product->description)->stripTags()->squish()->limit(160),
            'seoKeywords' => collect([$this->product->name, $this->product->category?->name, 'jamu herbal', setting('site_name', 'Jamu Mak Yuli')])->filter()->implode(', '),
            'seoImage' => $this->product->thumbnailUrl() ?? asset('assets/images/og-image.jpg'),
            'seoType' => 'product',
        ]);
    }

    public function updatedVariantId(): void
    {
        $this->activeImage = $this->resolveVariantImage($this->variantId);
        $this->updatedQuantity();
    }

    public function updatedQuantity(): void
    {
        $this->quantity = max(1, min((int) $this->quantity, max(1, $this->stock)));
    }

    public function stepImage(int $direction): void
    {

        $urls = $this->product->images->pluck('url')->values();

        if ($urls->count() < 2) {
            return;
        }

        $currentIndex = $urls->search($this->activeImage);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        $this->activeImage = $urls[($currentIndex + $direction + $urls->count()) % $urls->count()];
    }

    public function selectImage(string $url): void
    {
        $this->activeImage = $url;
    }

    private function resolveVariantImage(?int $variantId): ?string
    {
        $this->product->loadMissing('images', 'variants.image');
        $main = $this->product->thumbnailUrl();

        if ($variantId === null) {
            return $main;
        }

        return $this->product->variants->firstWhere('id', $variantId)?->image?->url ?? $main;
    }

    #[Computed]
    public function current(): Product|Variant
    {
        if ($this->product->hasVariants()) {
            return $this->product->variants->firstWhere('id', $this->variantId)
                ?? $this->product->variants->first();
        }

        return $this->product;
    }

    #[Computed]
    public function price(): int
    {
        return $this->current->effective_price;
    }

    #[Computed]
    public function compareAt(): ?int
    {
        $c = $this->current;

        if (! $c->isOnPromo()) {
            return null;
        }

        return $c instanceof Variant ? $c->price : $c->original_price;
    }

    #[Computed]
    public function stock(): int
    {
        return $this->current->stock;
    }

    #[Computed]
    public function sku(): string
    {
        return $this->current->sku;
    }

    #[Computed]
    public function weight(): int
    {
        return $this->current->weight;
    }

    #[Computed]
    public function wished(): bool
    {
        return app(WishlistService::class)->has($this->product->id);
    }

    #[Computed]
    public function related()
    {
        return Product::query()
            ->where('is_active', true)
            ->whereKeyNot($this->product->id)
            ->with(['category', 'variants', 'images'])
            ->inRandomOrder()
            ->take(12)
            ->get();
    }

    public function addCurrent(): void
    {
        $qty = max(1, min($this->quantity, $this->stock));

        try {
            app(CartService::class)->add($this->product->id, $qty, $this->variantId, $this->customText);
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());

            return;
        }

        $this->dispatch('cart-updated');
        $this->dispatch('cart-added');
    }
}; ?>
<div>
<x-page-header padding="py-4 sm:py-5" :items="[
    ['label' => __('Beranda'), 'url' => route('home')],
    ['label' => $product->category->name, 'url' => route('products.index', ['category' => $product->category->slug])],
    ['label' => $product->name],
]" />

@php
    $product->loadMissing('images');
    $galleryUrls = $product->images->pluck('url')->values();
    $hasGallery = $galleryUrls->isNotEmpty();
    $activeIndex = max(0, (int) $galleryUrls->search($activeImage));
    $discount = ($this->compareAt && $this->compareAt > $this->price)
        ? (int) round((1 - $this->price / $this->compareAt) * 100)
        : 0;
    $bisaDibeli = $this->price > 0;
    $soldOut = $this->stock <= 0;
    $waProduk = wa_href(\App\Support\WhatsAppMessage::productInquiry($product));
@endphp

<x-container class="py-8 md:py-10">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:gap-10">

        <div class="self-start lg:sticky lg:top-[100px]" x-data="productLightbox(@js($galleryUrls))">
            <div class="overflow-hidden rounded-xl border border-line bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="relative aspect-square select-none">
                    @if ($activeImage)
                        <img x-ref="mainImg" src="{{ $activeImage }}" alt="{{ $product->name }}"
                             @if ($hasGallery)
                                 @click="openBySrc($refs.mainImg.currentSrc || $refs.mainImg.src)"
                                 class="size-full cursor-zoom-in object-cover"
                             @else
                                 class="size-full object-cover"
                             @endif />
                    @else
                        <div class="ph grid size-full place-items-center" role="img" aria-label="{{ __('Ilustrasi') }} {{ $product->name }}">
                            <div class="ph-cap"><svg class="h-6 w-6" aria-hidden="true"><use href="#i-image"/></svg></div>
                        </div>
                    @endif

                    @if ($discount > 0)
                        <span class="pointer-events-none absolute left-3.5 top-3.5 z-10 inline-flex items-center rounded-md bg-brand-green px-2.5 py-1 font-display text-sm font-bold text-white">-{{ $discount }}%</span>
                    @endif

                    @if ($hasGallery)
                        <button type="button" @click="open({{ $activeIndex }})" aria-label="{{ __('Perbesar gambar') }}"
                                class="absolute right-3.5 top-3.5 z-10 grid size-9 place-items-center rounded-md border border-line bg-white/90 text-gray-500 backdrop-blur transition hover:border-brand-dark hover:bg-brand-dark hover:text-white dark:border-zinc-700 dark:bg-zinc-900/90 dark:text-zinc-300">
                            <flux:icon.arrows-pointing-out class="size-[18px]" />
                        </button>
                    @endif

                    @if ($hasGallery && $galleryUrls->count() > 1)
                        <button type="button" wire:click="stepImage(-1)" aria-label="{{ __('Sebelumnya') }}"
                                class="absolute left-3 top-1/2 z-10 grid size-10 -translate-y-1/2 place-items-center rounded-full border border-line bg-white/90 text-brand-dark shadow backdrop-blur transition hover:border-brand-green hover:bg-brand-green hover:text-white dark:border-zinc-700 dark:bg-zinc-900/90 dark:text-zinc-100">
                            <flux:icon.chevron-left class="size-5" />
                        </button>
                        <button type="button" wire:click="stepImage(1)" aria-label="{{ __('Berikutnya') }}"
                                class="absolute right-3 top-1/2 z-10 grid size-10 -translate-y-1/2 place-items-center rounded-full border border-line bg-white/90 text-brand-dark shadow backdrop-blur transition hover:border-brand-green hover:bg-brand-green hover:text-white dark:border-zinc-700 dark:bg-zinc-900/90 dark:text-zinc-100">
                            <flux:icon.chevron-right class="size-5" />
                        </button>

                        <div class="absolute bottom-0 left-1/2 z-10 flex -translate-x-1/2 items-center">
                            @foreach ($galleryUrls as $i => $url)
                                <button type="button" wire:click="selectImage('{{ $url }}')" aria-label="{{ __('Ke gambar') }} {{ $i + 1 }}"
                                        class="grid h-11 place-items-center px-2">
                                    <span class="block h-2 rounded-full transition {{ $i === $activeIndex ? 'w-5 bg-brand-green' : 'w-2 bg-white/70' }}"></span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @if ($hasGallery)
                <div class="mt-2.5 grid grid-cols-4 gap-2.5">
                    @foreach ($product->images as $img)
                        <button type="button" wire:click="selectImage('{{ $img->url }}')"
                                aria-label="{{ __('Lihat gambar') }} {{ $loop->iteration }}" @if ($activeImage === $img->url) aria-current="true" @endif
                                class="overflow-hidden rounded-lg border-2 transition hover:border-brand-green {{ $activeImage === $img->url ? 'border-brand-green' : 'border-line dark:border-zinc-700' }}">
                            <img src="{{ $img->url }}" alt="" class="aspect-square size-full object-cover" />
                        </button>
                    @endforeach
                </div>

                <x-product-lightbox />
            @endif
        </div>

        <div class="flex flex-col">
            @if ($product->category)
                <div class="mb-3 flex items-center gap-2">
                    <a href="{{ route('products.index', ['category' => $product->category->slug]) }}" wire:navigate
                       class="inline-flex items-center rounded bg-brand-turmeric/10 px-2.5 py-1 font-display text-xs font-bold tracking-wide text-brand-turmeric-ink transition hover:bg-brand-turmeric/20 dark:text-amber-300">{{ $product->category->name }}</a>
                </div>
            @endif

            <h1 class="font-display text-xl font-bold leading-[1.2] tracking-tight text-brand-dark dark:text-zinc-100 sm:text-[22px]">{{ $product->name }}</h1>
            <p class="notranslate mt-2 font-mono text-[12px] text-gray-500 dark:text-zinc-400">{{ __('SKU') }}: {{ $this->sku }} | {{ __('Berat') }}: {{ number_format($this->weight, 0, ',', '.') }} ({{ __('gram') }})</p>

            <div class="mt-5 rounded-xl border border-line bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                @if ($this->price <= 0)
                    <span class="font-display text-xl font-bold leading-none text-brand-green sm:text-2xl">{{ __('Hubungi kami') }}</span>
                    <p class="mt-2 text-sm text-body-2 dark:text-zinc-300">{{ __('Harga produk ini tersedia melalui penawaran. Silakan hubungi tim kami untuk ketersediaan dan harga.') }}</p>
                @else
                    @if ($this->compareAt)
                        <div class="flex flex-wrap items-center gap-2.5">
                            <span class="notranslate text-sm text-gray-400 line-through dark:text-zinc-400">{{ rupiah($this->compareAt) }}</span>
                            @if ($discount > 0)
                                <span class="inline-flex items-center rounded bg-brand-green/10 px-2 py-0.5 font-display text-[11px] font-bold text-brand-green">{{ __('Hemat') }} {{ $discount }}%</span>
                            @endif
                        </div>
                    @endif
                    <div class="mt-1 flex items-end gap-2">
                        <span class="notranslate font-display text-xl font-bold leading-none text-brand-green sm:text-2xl">{{ rupiah($this->price) }}</span>
                        <span class="mb-0.5 text-xs text-gray-500 dark:text-zinc-400">/ {{ __('pcs') }}</span>
                    </div>
                @endif

                @if ($soldOut)
                    <div class="mt-4 flex items-center gap-2 text-sm">
                        <span class="text-gray-600 dark:text-zinc-400">{{ __('Stok:') }}</span>
                        <span class="rounded bg-ink/80 px-2 py-0.5 font-display text-xs font-bold uppercase tracking-wide text-white">{{ __('Habis') }}</span>
                    </div>
                @elseif ($this->stock <= 5)
                    <div class="mt-4 flex items-center gap-2 text-sm">
                        <span class="text-gray-600 dark:text-zinc-400">{{ __('Stok:') }}</span>
                        <span class="font-display font-bold text-brand-turmeric-ink">{{ __('tersisa') }} {{ $this->stock }}</span>
                    </div>
                @else
                    <div class="mt-4 flex items-center gap-2 text-sm">
                        <span class="text-gray-600 dark:text-zinc-400">{{ __('Stok:') }}</span>
                        <span class="font-display font-bold text-emerald-600 dark:text-emerald-400">{{ $this->stock }} {{ __('tersedia') }}</span>
                    </div>
                @endif

                @if ($product->variants->isNotEmpty())
                    <div class="mt-5">
                        <p class="font-display text-sm font-bold text-gray-500 dark:text-zinc-400">{{ __('Varian') }}</p>
                        <div class="mt-2 flex flex-wrap gap-2" role="radiogroup" aria-label="{{ __('Pilih varian') }}">
                            @foreach ($product->variants as $variant)
                                @php
                                    $isActiveVariant = $variantId === $variant->id;
                                    $variantSoldOut = $variant->stock <= 0;
                                @endphp
                                <label wire:key="var-{{ $variant->id }}"
                                       class="relative inline-flex min-w-[3rem] cursor-pointer items-center justify-center rounded-md border-2 px-4 py-2 text-sm font-semibold uppercase tracking-wide transition
                                           {{ $isActiveVariant
                                               ? 'border-brand-green bg-brand-green text-white'
                                               : 'border-line bg-white text-ink hover:border-brand-green hover:text-brand-green dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100' }}">
                                    <input type="radio" wire:model.live="variantId" value="{{ $variant->id }}" class="sr-only" @disabled($variantSoldOut) />
                                    {{ $variant->name }}@if ($variantSoldOut) <span class="ml-1 text-[10px] normal-case">({{ __('Habis') }})</span>@endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($product->custom_text_enabled && $bisaDibeli)
                    <div class="mt-5 border-t border-line pt-5 dark:border-zinc-800">
                        <flux:input wire:model.live.debounce.300ms="customText" maxlength="60"
                                    label="{{ __('Catatan Custom') }}"
                                    placeholder="{{ __('Tulis permintaan Anda') }}" />
                    </div>
                @endif

                @if ($bisaDibeli)
                    <div class="mt-5 flex items-center gap-3">
                        <span class="font-display text-sm font-bold text-gray-500 dark:text-zinc-400">{{ __('Jumlah') }}</span>
                        <div class="flex h-11 items-center overflow-hidden rounded-md border-2 border-line dark:border-zinc-700">
                            <button type="button" aria-label="{{ __('Kurangi') }}"
                                    @click="$wire.set('quantity', Math.max(1, (parseInt($wire.quantity) || 1) - 1))"
                                    class="grid h-full w-11 place-items-center text-gray-500 transition hover:bg-gray-100 hover:text-brand-green dark:text-zinc-300 dark:hover:bg-zinc-800">
                                <flux:icon.minus class="size-4" />
                            </button>
                            <input type="text" inputmode="numeric" wire:model.live.debounce.500ms="quantity"
                                   aria-label="{{ __('Jumlah') }}"
                                   @input="
                                       const angka = $el.value.replace(/[^0-9]/g, '');
                                       $el.value = angka === '' ? '' : String(Math.min({{ $this->stock }}, Math.max(1, parseInt(angka))));
                                   "
                                   @blur="if ($el.value === '') $wire.set('quantity', 1)"
                                   class="h-full w-12 border-x-2 border-line bg-transparent text-center font-display text-lg font-bold outline-none dark:border-zinc-700 dark:text-zinc-100" />
                            <button type="button" aria-label="{{ __('Tambah') }}"
                                    @click="$wire.set('quantity', Math.min({{ $this->stock }}, (parseInt($wire.quantity) || 1) + 1))"
                                    class="grid h-full w-11 place-items-center text-gray-500 transition hover:bg-gray-100 hover:text-brand-green dark:text-zinc-300 dark:hover:bg-zinc-800">
                                <flux:icon.plus class="size-4" />
                            </button>
                        </div>
                        <button type="button" wire:click="toggleWishlist({{ $product->id }})" aria-label="{{ __('Tambah ke favorit') }}"
                                @class([
                                    'grid size-11 place-items-center rounded-md border-2 transition',
                                    'border-brand-green bg-brand-green text-white' => $this->wished,
                                    'border-line bg-white text-gray-400 hover:border-brand-green hover:bg-brand-green hover:text-white dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400' => ! $this->wished,
                                ])>
                            <flux:icon.heart :variant="$this->wished ? 'solid' : 'outline'" class="size-5" />
                        </button>
                    </div>

                    <div class="mt-4 hidden items-center justify-between border-t border-line pt-4 dark:border-zinc-800 lg:flex">
                        <span class="text-xs text-gray-500 dark:text-zinc-400">{{ __('Subtotal') }}</span>
                        <span class="notranslate font-display text-lg font-bold text-brand-dark dark:text-zinc-100">{{ rupiah($this->price * max(1, (int) $quantity)) }}</span>
                    </div>

                    <div class="mt-4 hidden gap-3 lg:grid {{ $waProduk ? 'grid-cols-2' : 'grid-cols-1' }}">
                        <button type="button" wire:click="addCurrent" wire:loading.attr="disabled"
                                @disabled($soldOut || ($product->custom_text_enabled && trim($customText) === ''))
                                class="inline-flex h-12 items-center justify-center gap-2 rounded-md bg-brand-green font-display text-sm font-bold tracking-wide text-white transition hover:bg-brand-green-dark disabled:cursor-not-allowed disabled:opacity-60">
                            <svg class="h-[18px] w-[18px] fill-none stroke-current stroke-2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-cart"/></svg>
                            {{ __('Tambah Keranjang') }}
                        </button>
                        @if ($waProduk)
                            <a href="{{ $waProduk }}" target="_blank" rel="noopener"
                               class="inline-flex h-12 items-center justify-center gap-2 rounded-md bg-[#0E7C6F] font-display text-sm font-bold tracking-wide text-white transition hover:brightness-95">
                                <svg class="h-[18px] w-[18px] fill-current" aria-hidden="true"><use href="#i-wa"/></svg>
                                {{ __('Tanya via WhatsApp') }}
                            </a>
                        @endif
                    </div>
                @elseif ($this->price <= 0 && $waProduk)
                    <div class="mt-5">
                        <a href="{{ $waProduk }}" target="_blank" rel="noopener"
                           class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-md bg-[#0E7C6F] font-display text-sm font-bold tracking-wide text-white transition hover:brightness-95">
                            <svg class="h-[18px] w-[18px] fill-current" aria-hidden="true"><use href="#i-wa"/></svg>
                            {{ __('Tanya via WhatsApp') }}
                        </a>
                    </div>
                @endif
            </div>

            @php
                $shareUrl = route('products.show', $product->slug);
                $waShare = 'https://wa.me/?text='.rawurlencode($product->name.' - '.$shareUrl);
                $tgShare = 'https://t.me/share/url?url='.rawurlencode($shareUrl).'&text='.rawurlencode($product->name);
                $fbShare = 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($shareUrl);
                $xShare = 'https://twitter.com/intent/tweet?text='.rawurlencode($product->name).'&url='.rawurlencode($shareUrl);
            @endphp
            <div class="mt-5 flex items-center gap-2 px-1" x-data="{ copied: false, t: null }">
                <span class="mr-1 flex shrink-0 items-center gap-1.5 font-display text-sm text-gray-500 dark:text-zinc-400">
                    <flux:icon.share class="size-4" /> {{ __('Bagikan:') }}
                </span>

                <a href="{{ $fbShare }}" target="_blank" rel="noopener" aria-label="{{ __('Bagikan ke Facebook') }}" title="Facebook"
                   class="grid size-9 place-items-center rounded-md bg-gray-100 text-gray-500 transition hover:bg-[#1877F2] hover:text-white dark:bg-zinc-800 dark:text-zinc-400">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="size-[18px]"><path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07c0 6.02 4.39 11 10.13 11.88v-8.4H7.08v-3.48h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.69.24 2.69.24v2.97h-1.52c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.48h-2.8v8.4C19.61 23.05 24 18.07 24 12.07"/></svg>
                </a>

                <a href="{{ $xShare }}" target="_blank" rel="noopener" aria-label="{{ __('Bagikan ke X') }}" title="X"
                   class="grid size-9 place-items-center rounded-md bg-gray-100 text-gray-500 transition hover:bg-black hover:text-white dark:bg-zinc-800 dark:text-zinc-400">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="size-4"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </a>

                <a href="{{ $tgShare }}" target="_blank" rel="noopener" aria-label="{{ __('Bagikan ke Telegram') }}" title="Telegram"
                   class="grid size-9 place-items-center rounded-md bg-gray-100 text-gray-500 transition hover:bg-[#229ED9] hover:text-white dark:bg-zinc-800 dark:text-zinc-400">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="size-[18px]"><path d="M23.91 3.79 20.3 20.84c-.25 1.21-.98 1.5-2 .94l-5.5-4.07-2.66 2.57c-.3.3-.55.56-1.1.56-.72 0-.6-.27-.84-.95L6.3 13.7l-5.45-1.7c-1.18-.36-1.19-1.16.26-1.75l21.26-8.2c.97-.43 1.9.24 1.53 1.73z"/></svg>
                </a>

                <a href="{{ $waShare }}" target="_blank" rel="noopener" aria-label="{{ __('Bagikan ke WhatsApp') }}" title="WhatsApp"
                   class="grid size-9 place-items-center rounded-md bg-gray-100 text-gray-500 transition hover:bg-[#0E7C6F] hover:text-white dark:bg-zinc-800 dark:text-zinc-400">
                    <svg class="size-[18px] fill-current" aria-hidden="true"><use href="#i-wa"/></svg>
                </a>

                <button type="button" aria-label="{{ __('Salin tautan') }}" :title="copied ? @js(__('Tersalin')) : @js(__('Salin tautan'))"
                        @click="navigator.clipboard?.writeText(@js($shareUrl)); copied = true; clearTimeout(t); t = setTimeout(() => copied = false, 2000)"
                        class="grid size-9 place-items-center rounded-md transition"
                        :class="copied ? 'bg-brand-green text-white' : 'bg-gray-100 text-gray-500 hover:bg-brand-dark hover:text-white dark:bg-zinc-800 dark:text-zinc-400'">
                    <span x-show="!copied"><flux:icon.link class="size-[18px]" /></span>
                    <span x-show="copied" x-cloak><flux:icon.check class="size-[18px]" /></span>
                </button>
            </div>
        </div>
    </div>

    <section class="mt-12">
        <h2 class="sec-tick mb-5 font-display text-[16px] font-semibold tracking-tight text-brand-dark dark:text-zinc-100 md:text-[19px]">{{ __('DETAIL PRODUK') }}</h2>
        <div class="rounded-xl border border-line bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            @if (trim(strip_tags((string) $product->description)) !== '')
                <div class="rich-text max-w-none text-[15px] leading-relaxed text-gray-700 dark:text-zinc-300">{!! $product->description !!}</div>
            @else
                <p class="text-[15px] leading-relaxed text-gray-700 dark:text-zinc-300">{{ __('Deskripsi produk ini belum tersedia. Tanyakan detail ramuan langsung ke kami.') }}</p>
                <a href="{{ $waProduk ?? '#' }}" target="_blank" rel="noopener"
                   class="mt-3 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-primary underline-offset-4 hover:underline">
                    <svg class="h-4 w-4 fill-current" aria-hidden="true"><use href="#i-wa"/></svg>
                    {{ __('Tanya lewat WhatsApp') }}
                </a>
            @endif
        </div>
    </section>

    <div x-data="{ open: false }" x-cloak @cart-added.window="open = true">
        <div x-show="open" class="fixed inset-0 z-[120] flex items-center justify-center p-4"
             x-transition.opacity.duration.200ms
             @keydown.window.escape="open = false"
             role="dialog" aria-modal="true" aria-label="{{ __('Produk ditambahkan ke keranjang') }}">
            <div class="absolute inset-0 bg-ink/60 backdrop-blur-sm" @click="open = false"></div>

            <div class="relative w-full max-w-[21rem] rounded-lg border border-line bg-white p-5 text-center shadow-[0_4px_12px_rgba(0,0,0,0.10)] dark:border-zinc-800 dark:bg-zinc-900"
                 x-show="open"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
                <div class="mx-auto grid size-12 place-items-center rounded-full bg-primary/15">
                    <span class="grid size-9 place-items-center rounded-full bg-primary text-white">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7" /></svg>
                    </span>
                </div>
                <p class="mt-3 text-[13px] font-semibold text-ink dark:text-zinc-100">{{ __('Produk berhasil dimasukkan ke keranjang.') }}</p>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <x-btn variant="primary" :href="route('cart.index')" wire:navigate class="whitespace-nowrap px-3">
                        {{ __('Lihat Keranjang') }}
                    </x-btn>
                    <x-btn variant="soft" @click="open = false" class="whitespace-nowrap px-3">
                        {{ __('Lanjutkan Belanja') }}
                    </x-btn>
                </div>
            </div>
        </div>
    </div>
</x-container>

<x-product-rail :heading="__('PRODUK REKOMENDASI')" :products="$this->related" :href="route('products.index')" pad="pb-12 md:pb-16" />

@if ($bisaDibeli)
    <div id="mobile-buy-bar" class="bar-aksi fixed inset-x-0 bottom-0 z-[60] flex items-center gap-3 border-t border-line bg-white px-4 pt-2.5 shadow-[0_-8px_28px_-12px_rgba(0,0,0,.3)] dark:border-zinc-800 dark:bg-zinc-900 lg:hidden">
        <div class="shrink-0">
            <p class="mb-1 text-[11px] leading-none text-gray-500 dark:text-zinc-400">{{ __('Subtotal') }}</p>
            <p class="notranslate font-display text-lg font-bold leading-none text-brand-dark dark:text-zinc-100">{{ rupiah($this->price * max(1, (int) $quantity)) }}</p>
        </div>
        <div class="grid flex-1 gap-2 {{ $waProduk ? 'grid-cols-2' : 'grid-cols-1' }}">
            <button type="button" wire:click="addCurrent" wire:loading.attr="disabled"
                    @disabled($soldOut || ($product->custom_text_enabled && trim($customText) === ''))
                    class="inline-flex h-11 items-center justify-center gap-1.5 rounded-md bg-brand-green font-display text-xs font-bold tracking-wide text-white transition hover:bg-brand-green-dark disabled:cursor-not-allowed disabled:opacity-60">
                <svg class="h-4 w-4 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-cart"/></svg>
                {{ __('Keranjang') }}
            </button>
            @if ($waProduk)
                <a href="{{ $waProduk }}" target="_blank" rel="noopener"
                   class="inline-flex h-11 items-center justify-center gap-1.5 rounded-md bg-[#0E7C6F] font-display text-xs font-bold tracking-wide text-white transition hover:brightness-95">
                    <svg class="h-4 w-4 fill-current" aria-hidden="true"><use href="#i-wa"/></svg>
                    {{ __('WhatsApp') }}
                </a>
            @endif
        </div>
    </div>
@endif
</div>
