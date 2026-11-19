<?php

use App\Concerns\InteractsWithCart;
use App\Concerns\InteractsWithWishlist;
use App\Services\WishlistService;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::storefront')] class extends Component {
    use InteractsWithCart;
    use InteractsWithWishlist;

    public function rendering(View $view): void
    {
        $view->title(__('Favorit Saya'));
    }

    #[Computed]
    public function products()
    {
        return app(WishlistService::class)->items();
    }
}; ?>

<div>
<x-page-header :title="__('Favorit Saya')" :items="[
    ['label' => __('Beranda'), 'url' => route('home')],
    ['label' => __('Favorit')],
]" />

<x-container class="py-8 lg:py-12">
    <p class="text-right text-[15px] font-bold uppercase text-primary" aria-live="polite">{{ __(':count Item', ['count' => $this->products->count()]) }}</p>

    @if ($this->products->isEmpty())
        <div class="mt-8 flex flex-col items-center justify-center rounded-lg border border-dashed border-line bg-white py-20 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <flux:icon.heart class="size-12 text-muted dark:text-zinc-400" />
            <p class="mt-3 text-body-2 dark:text-zinc-300">{{ __('Anda belum menyimpan produk favorit.') }}</p>
            <p class="mt-1 text-sm text-muted dark:text-zinc-400">{{ __('Tekan ikon hati pada kartu produk untuk menyimpannya di sini.') }}</p>
            <x-btn variant="primary" :href="route('products.index')" wire:navigate class="mt-4">{{ __('Jelajahi Katalog') }}</x-btn>
        </div>
    @else
        <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4 lg:gap-5">
            @foreach ($this->products as $product)
                <x-product-card :product="$product" removable :wire:key="'wish-'.$product->id" />
            @endforeach
        </div>
    @endif
</x-container>
</div>
