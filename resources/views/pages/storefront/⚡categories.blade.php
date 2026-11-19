<?php

use App\Models\Category;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::storefront')] class extends Component
{
    public function rendering(View $view): void
    {
        $view->title(__('Kategori Produk'));
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->where('is_active', true)->withCount('products')->orderBy('id')->get();
    }
}; ?>

<div>
<x-page-header :title="__('Kategori Produk')" :items="[
    ['label' => __('Beranda'), 'url' => route('home')],
    ['label' => __('Kategori Produk')],
]" />

<x-container class="py-8 pb-24 lg:py-12 lg:pb-16">
    <p class="max-w-2xl text-sm text-body-2 dark:text-zinc-300">{{ __('Pilih ramuan sesuai kebutuhan Anda, dari paket promil pasutri hingga ramuan rempah harian.') }}</p>

    <ul class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 md:gap-4 lg:grid-cols-4">
        @foreach ($this->categories as $category)
            <li>
                <a href="{{ route('products.index', ['category' => $category->slug]) }}" wire:navigate
                   class="cat-card flex h-full items-center gap-3.5 rounded-2xl border border-black/[0.07] bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900 md:p-5">
                    <span class="grid h-11 w-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-brand-pink-soft text-brand-green dark:bg-brand-green/10">
                        @if ($category->thumbnailUrl())
                            <img src="{{ $category->thumbnailUrl() }}" alt="{{ $category->name }}" class="h-full w-full object-cover" loading="lazy">
                        @else
                            <svg class="h-[22px] w-[22px] fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-package"/></svg>
                        @endif
                    </span>
                    <span class="min-w-0">
                        <span class="block font-display text-[14px] font-bold dark:text-zinc-100 md:text-[15px]">{{ $category->name }}</span>
                        @if ($category->products_count > 0)
                            <span class="mt-0.5 block text-[12px] text-gray-500 dark:text-zinc-400">{{ __(':count produk', ['count' => $category->products_count]) }}</span>
                        @endif
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</x-container>
</div>
