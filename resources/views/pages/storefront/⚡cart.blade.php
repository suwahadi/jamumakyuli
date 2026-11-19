<?php

use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Services\CartService;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::storefront')] class extends Component {
    public function rendering(View $view): void
    {
        $view->title(__('Keranjang Belanja'));
    }

    #[Computed]
    public function items()
    {
        return app(CartService::class)->items();
    }

    #[Computed]
    public function subtotal(): int
    {
        return app(CartService::class)->subtotal();
    }

    public function increment(string $key): void
    {
        $this->changeQty($key, 1);
    }

    public function decrement(string $key): void
    {
        $this->changeQty($key, -1);
    }

    private function changeQty(string $key, int $delta): void
    {
        $cart = app(CartService::class);
        $current = $cart->items()->firstWhere('key', $key);

        if ($current === null) {
            return;
        }

        try {
            $cart->update($key, $current['quantity'] + $delta);
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());
        }

        unset($this->items, $this->subtotal);
        $this->dispatch('cart-updated');
    }

    public function remove(string $key): void
    {
        app(CartService::class)->remove($key);
        unset($this->items, $this->subtotal);
        $this->dispatch('cart-updated');
        Flux::toast(variant: 'success', text: __('Barang dihapus dari keranjang.'));
    }

    public function clearAll(): void
    {
        app(CartService::class)->clear();
        unset($this->items, $this->subtotal);
        $this->dispatch('cart-updated');
        Flux::toast(variant: 'success', text: __('Semua barang dihapus dari keranjang.'));
    }

    #[Computed]
    public function rekomendasi()
    {
        return Product::query()
            ->where('is_active', true)
            ->whereNotIn('id', $this->items->pluck('product.id'))
            ->with(['category', 'variants', 'images'])
            ->inRandomOrder()
            ->take(12)
            ->get();
    }
}; ?>

<div>
<x-page-header :title="__('Keranjang Belanja')" :items="[
    ['label' => __('Beranda'), 'url' => route('home')],
    ['label' => __('Keranjang')],
]" />

<x-container class="py-8 lg:py-12">
    @if ($this->items->isEmpty())
        <div class="mt-8 flex flex-col items-center justify-center rounded-lg border border-dashed border-line bg-white py-20 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <div class="grid size-16 place-items-center rounded-full bg-[#F3F4F6] dark:bg-zinc-800">
                <flux:icon.shopping-cart class="size-7 text-muted dark:text-zinc-400" />
            </div>
            <h2 class="mt-4 font-display text-lg font-bold text-ink dark:text-zinc-100">{{ __('Keranjang Kamu Kosong') }}</h2>
            <p class="mt-1 text-sm text-body-2 dark:text-zinc-400">{{ __('Belum ada barang yang ditambahkan ke keranjang belanjamu.') }}</p>
            <x-btn variant="primary" :href="route('products.index')" wire:navigate class="mt-4">{{ __('Mulai Belanja') }}</x-btn>
        </div>
    @else
        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
            <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-ink dark:text-zinc-100">{{ __('Daftar Barang') }}</h2>
                    <button type="button" wire:click="clearAll" wire:confirm="{{ __('Hapus semua barang dari keranjang?') }}" class="-mr-2 flex min-h-11 items-center gap-1 px-2 text-xs font-semibold text-muted hover:text-sale dark:text-zinc-400">
                        <svg class="h-3.5 w-3.5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-trash"/></svg>
                        {{ __('Hapus Semua') }}
                    </button>
                </div>
                @foreach ($this->items as $item)
                    <div class="flex gap-3 rounded-lg border border-line bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900 sm:gap-4 sm:p-5" wire:key="cart-{{ $item['key'] }}">
                        <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-md border border-line bg-[#F3F4F6] dark:border-zinc-800 dark:bg-zinc-800">
                            @php $thumb = $item['variant']?->thumbnailUrl() ?? $item['product']->thumbnailUrl(); @endphp
                            @if ($thumb)
                                <img src="{{ $thumb }}" alt="{{ $item['product']->name }}" class="size-full object-cover" loading="lazy" />
                            @else
                                <div class="ph grid size-full place-items-center">
                                    <div class="ph-cap"><svg class="h-5 w-5" aria-hidden="true"><use href="#i-image"/></svg></div>
                                </div>
                            @endif
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('products.show', $item['product']->slug) }}" wire:navigate class="text-sm font-semibold text-ink hover:text-primary dark:text-zinc-100">
                                    {{ $item['product']->name }}
                                </a>
                                @if ($item['variant'])
                                    <p class="text-xs text-muted dark:text-zinc-400">{{ __('Varian:') }} {{ $item['variant']->name }}</p>
                                @endif
                                @if (! empty($item['custom_text']))
                                    <p class="text-xs font-medium text-primary">{{ __('Catatan Custom:') }} {{ $item['custom_text'] }}</p>
                                @endif
                                <p class="notranslate mt-1 text-price text-sm text-primary">{{ rupiah($item['price']) }}</p>
                                @if ($item['stock'] <= 0)
                                    <p class="mt-1 text-xs font-bold uppercase tracking-wide text-sale">{{ __('Habis') }}</p>
                                @elseif ($item['quantity'] > $item['stock'])
                                    <p class="mt-1 text-xs font-bold text-sale">{{ __('Sisa :jumlah', ['jumlah' => $item['stock']]) }}</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2 sm:flex-nowrap sm:gap-3">
                                <div class="flex items-center rounded-md border border-line dark:border-zinc-700">
                                    <flux:button size="sm" variant="subtle" icon="minus" wire:click="decrement('{{ $item['key'] }}')" :aria-label="__('Kurangi jumlah')" class="!size-11" />
                                    <span class="w-8 text-center text-sm font-bold text-ink dark:text-zinc-100" aria-live="polite">{{ $item['quantity'] }}</span>
                                    <flux:button size="sm" variant="subtle" icon="plus" wire:click="increment('{{ $item['key'] }}')" :aria-label="__('Tambah jumlah')" class="!size-11" />
                                </div>
                                <flux:button size="sm" variant="subtle" icon="trash" wire:click="remove('{{ $item['key'] }}')" :aria-label="__('Hapus dari keranjang')" class="!size-11 hover:bg-sale hover:text-white sm:order-last" />
                                <div class="notranslate ml-auto text-right text-price text-sm text-ink dark:text-zinc-100 sm:w-28">{{ rupiah($item['line_total']) }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="h-fit rounded-lg border border-line bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 lg:sticky lg:top-20">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-ink dark:text-zinc-100">{{ __('Ringkasan') }}</h2>
                <div class="mt-4 flex justify-between text-sm">
                    <span class="text-body-2 dark:text-zinc-400">{{ __('Subtotal') }}</span>
                    <span class="notranslate text-price text-ink dark:text-zinc-100">{{ rupiah($this->subtotal) }}</span>
                </div>
                <p class="mt-1 text-xs text-muted dark:text-zinc-400">{{ __('Ongkos kirim dihitung saat checkout.') }}</p>
                <x-btn variant="primary" :href="route('checkout')" wire:navigate class="mt-5 w-full">
                    {{ __('Lanjut ke Checkout') }}
                    <svg class="h-4 w-4 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-arrow"/></svg>
                </x-btn>
                <x-btn variant="outline" :href="route('products.index')" wire:navigate class="mt-2 w-full">{{ __('Lanjut Belanja') }}</x-btn>
            </div>
        </div>
    @endif
</x-container>

@if ($this->items->isNotEmpty())
    <div id="mobile-checkout-bar" class="bar-aksi fixed inset-x-0 bottom-0 z-[60] flex items-center gap-3 border-t border-gray-200 bg-white px-4 pt-2.5 shadow-[0_-8px_28px_-12px_rgba(0,0,0,.3)] dark:border-zinc-800 dark:bg-zinc-900 lg:hidden">
        <div class="shrink-0">
            <p class="mb-1 text-[11px] leading-none text-gray-500 dark:text-zinc-400">{{ __('Subtotal') }}</p>
            <p class="notranslate font-display text-lg font-bold leading-none text-brand-dark dark:text-zinc-100">{{ rupiah($this->subtotal) }}</p>
        </div>
        <a href="{{ route('checkout') }}" wire:navigate
           class="inline-flex h-11 flex-1 items-center justify-center gap-1.5 rounded-md bg-brand-green font-display text-xs font-bold tracking-wide text-white transition hover:bg-brand-green-dark">
            {{ __('Checkout') }}
            <svg class="h-4 w-4 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-arrow"/></svg>
        </a>
    </div>
@endif

<x-product-rail :heading="__('PRODUK REKOMENDASI')" :products="$this->rekomendasi" :href="route('products.index')" pad="pt-12 pb-12" />
</div>
