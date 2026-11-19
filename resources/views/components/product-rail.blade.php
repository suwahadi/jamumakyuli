@props([
    'heading',
    'products',
    'href' => null,
    'pad' => 'py-10 md:py-12',
])

@if ($products->isNotEmpty())
    <section {{ $attributes->class(['container-x', $pad]) }} x-data="productRail">
        <div class="mb-6 flex items-end justify-between">
            <h2 class="sec-tick font-display text-[16px] font-semibold tracking-tight dark:text-zinc-100 md:text-[19px]">{{ $heading }}</h2>
            @if ($href)
                <a href="{{ $href }}" wire:navigate class="link-underline -mb-3 flex min-h-11 items-center gap-1 font-display text-sm font-bold text-brand-green">
                    {{ __('Lihat Semua') }}
                    <svg class="h-4 w-4 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-arrow"/></svg>
                </a>
            @endif
        </div>

        <div class="relative">
            <div class="overflow-hidden" x-ref="viewport">
                <div class="flex gap-4 touch-pan-y" x-ref="track"
                     @touchstart.passive="onTouchStart($event)" @touchend.passive="onTouchEnd($event)">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" wire:key="rail-{{ $product->id }}"
                                        x-bind:inert="! visible({{ $loop->index }})"
                                        class="shrink-0 basis-[calc((100%-1rem)/2)] sm:basis-[calc((100%-2rem)/3)] lg:basis-[calc((100%-5rem)/6)]" />
                    @endforeach
                </div>
            </div>

            <button type="button" @click="prev()" aria-label="{{ __('Sebelumnya') }}" :class="index <= 0 && 'opacity-30 pointer-events-none'"
                    class="absolute -left-5 top-1/2 z-20 hidden h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-brand-green/80 text-white shadow-md transition hover:bg-brand-green sm:grid">
                <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-chev-left"/></svg>
            </button>
            <button type="button" @click="next()" aria-label="{{ __('Berikutnya') }}" :class="index >= max && 'opacity-30 pointer-events-none'"
                    class="absolute -right-5 top-1/2 z-20 hidden h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-brand-green/80 text-white shadow-md transition hover:bg-brand-green sm:grid">
                <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-chev-right"/></svg>
            </button>
        </div>

        <div class="mt-4 flex justify-center gap-3 sm:hidden">
            <button type="button" @click="prev()" aria-label="{{ __('Sebelumnya') }}" :class="index <= 0 && 'opacity-30 pointer-events-none'"
                    class="grid h-11 w-11 place-items-center rounded-md border-2 border-brand-dark text-brand-dark transition-colors hover:bg-brand-dark hover:text-white dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700">
                <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-chev-left"/></svg>
            </button>
            <button type="button" @click="next()" aria-label="{{ __('Berikutnya') }}" :class="index >= max && 'opacity-30 pointer-events-none'"
                    class="grid h-11 w-11 place-items-center rounded-md border-2 border-brand-dark text-brand-dark transition-colors hover:bg-brand-dark hover:text-white dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700">
                <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-chev-right"/></svg>
            </button>
        </div>
    </section>
@endif
