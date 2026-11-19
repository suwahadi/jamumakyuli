@props([
    'product',
    'removable' => false,
])

@php
    $hasVariants = $product->hasVariants();
    $onPromo = $product->isOnPromo();
    $thumb = $product->thumbnailUrl();
    $soldOut = $product->isSoldOut();
    $url = route('products.show', $product->slug);

    $displayPrice = $product->fromPrice();
    $isCall = $displayPrice <= 0;

    $strikePrice = null;
    if ($hasVariants) {
        $fromVariant = $product->variants->sortBy(fn ($v) => $v->effective_price)->first();
        if ($fromVariant?->isOnPromo()) {
            $strikePrice = $fromVariant->price;
        }
    } elseif ($onPromo) {
        $strikePrice = $product->original_price;
    }

    $discount = ($strikePrice && $strikePrice > $displayPrice)
        ? (int) round((1 - $displayPrice / $strikePrice) * 100)
        : 0;
@endphp

<article {{ $attributes->class('card group relative flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:border-brand-green hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-brand-green') }}
         data-cat="{{ $product->category?->slug }}">
    <div class="relative overflow-hidden">
        <a href="{{ $url }}" wire:navigate class="block focus:outline-none" aria-label="{{ $product->name }}">
            @if ($thumb)
                <div class="aspect-square overflow-hidden bg-surface dark:bg-zinc-800">
                    <img src="{{ $thumb }}" alt="{{ $product->name }}" loading="lazy" width="600" height="600"
                         class="pimg h-full w-full object-cover">
                </div>
            @else
                <div class="ph grid aspect-square place-items-center">
                    <div class="ph-cap"><svg class="h-6 w-6" aria-hidden="true"><use href="#i-image"/></svg></div>
                </div>
            @endif
        </a>

        @if ($discount > 0)
            <span class="disc-badge absolute right-3 top-0 z-10 flex h-11 w-10 items-start justify-center bg-brand-turmeric pt-1.5 font-display text-[11px] font-extrabold leading-none text-brand-dark">{{ $discount }}%</span>
        @endif

        @if ($soldOut)
            <span class="absolute bottom-0 left-0 right-0 z-10 bg-ink/80 px-2 py-1 text-center font-display text-[10px] font-bold uppercase tracking-wide text-white">{{ __('Habis') }}</span>
        @endif
    </div>

    <div class="flex flex-1 flex-col px-2.5 py-3 sm:p-3.5">
        <div class="flex flex-wrap items-baseline gap-x-1 gap-y-1 sm:gap-x-1.5">
            @if ($isCall)
                <span class="font-display text-[13px] font-normal leading-none text-brand-green">{{ __('Hubungi kami') }}</span>
            @else
                @if ($strikePrice)
                    <span class="notranslate whitespace-nowrap text-[11px] leading-none tracking-tight text-gray-500 line-through dark:text-zinc-400">{{ rupiah($strikePrice) }}</span>
                @endif
                <span class="notranslate whitespace-nowrap font-display text-[13px] font-bold leading-none tracking-tight text-brand-green sm:text-sm">{{ rupiah($displayPrice) }}</span>
            @endif
        </div>

        <h3 class="mt-2 line-clamp-2 min-h-[2.6em] text-sm font-medium leading-snug dark:text-zinc-100">
            <a href="{{ $url }}" wire:navigate class="transition hover:text-brand-green">{{ $product->name }}</a>
        </h3>

        <div class="mt-auto flex items-center gap-2">
            <a href="{{ $url }}" wire:navigate class="grid h-11 flex-1 place-items-center rounded-md bg-brand-green font-display text-xs font-bold tracking-wide text-white transition hover:bg-brand-green-dark">{{ __('Lihat Detail') }}</a>
            @if ($removable)
                <button type="button" wire:click="toggleWishlist({{ $product->id }})" wire:loading.attr="disabled"
                        aria-label="{{ __('Hapus dari favorit') }}" title="{{ __('Hapus dari favorit') }}"
                        class="grid size-11 shrink-0 place-items-center rounded-md border border-brand-green/30 text-brand-green transition-colors hover:bg-brand-green hover:text-white">
                    <svg class="h-[18px] w-[18px] fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-trash"/></svg>
                </button>
            @endif
        </div>
    </div>
</article>
