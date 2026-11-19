<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::storefront')] class extends Component {
    public function rendering(View $view): void
    {
        $view->title(__('Katalog Produk'));
    }

    #[Url(as: 'q', history: true)]
    public string $search = '';

    public array $cats = [];

    public array $bands = [];

    public string $sort = 'baru';

    public int $perPage = 12;

    private const BANDS = [
        'a' => [0, 500_000],
        'b' => [500_000, 1_500_000],
        'c' => [1_500_000, null],
    ];

    public function mount(): void
    {
        if (($cat = request()->query('category')) && is_string($cat)) {
            $this->cats = [$cat];
        }
    }

    public function updatedSearch(): void
    {
        $this->perPage = 12;
    }

    public function updatedCats(): void
    {
        $this->perPage = 12;
    }

    public function updatedBands(): void
    {
        $this->perPage = 12;
    }

    public function updatedSort(): void
    {
        $this->perPage = 12;
    }

    public function loadMore(): void
    {
        $this->perPage += 12;
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->where('is_active', true)->orderBy('id')->get();
    }

    private function baseQuery()
    {
        $priceSql = Product::effectivePriceSql();

        return Product::query()
            ->active()
            ->with('category', 'variants', 'images')
            ->cari($this->search)
            ->when($this->cats !== [], fn ($q) => $q->whereHas('category', fn ($c) => $c->whereIn('slug', $this->cats)))
            ->when($this->bands !== [], fn ($q) => $q->where(function ($w) use ($priceSql) {
                foreach ($this->bands as $band) {
                    [$min, $max] = self::BANDS[$band] ?? [null, null];
                    $w->orWhere(function ($o) use ($priceSql, $min, $max) {
                        $o->whereRaw("{$priceSql} >= ?", [$min]);
                        if ($max !== null) {
                            $o->whereRaw("{$priceSql} <= ?", [$max]);
                        }
                    });
                }
            }))
            ->when($this->sort === 'murah', fn ($q) => $q->orderByRaw("{$priceSql} asc"))
            ->when($this->sort === 'mahal', fn ($q) => $q->orderByRaw("{$priceSql} desc"))
            ->when($this->sort === 'nama', fn ($q) => $q->orderBy('name'))
            ->when($this->sort === 'baru' || ! in_array($this->sort, ['murah', 'mahal', 'nama'], true), fn ($q) => $q->latest());
    }

    #[Computed]
    public function products()
    {
        return $this->baseQuery()->take($this->perPage)->get();
    }

    #[Computed]
    public function totalProducts(): int
    {
        return $this->baseQuery()->count();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'cats', 'bands']);
        $this->sort = 'baru';
        $this->perPage = 12;
    }
}; ?>

<div>
<x-page-header :title="__('Katalog Produk')" :items="[
    ['label' => __('Beranda'), 'url' => route('home')],
    ['label' => __('Katalog')],
]" />

<div x-data="{ drawer: false }">
    <x-container class="py-8 lg:py-10">
        <div class="grid items-start gap-8 lg:grid-cols-[260px_1fr] lg:gap-10">
            <div>
                <x-btn variant="dark" @click="drawer = true" class="w-full lg:hidden">
                    <svg class="h-4 w-4 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-sliders"/></svg>
                    {{ __('Filter dan Urutkan') }}
                </x-btn>

                <aside class="mt-4 hidden lg:mt-0 lg:block" aria-label="{{ __('Filter produk') }}">
                    @include('partials.storefront.catalog-filters')
                </aside>
            </div>

            <section aria-label="{{ __('Daftar produk') }}">
                <div class="mb-5 flex items-center justify-between gap-4">
                    <p class="text-[15px] font-bold uppercase text-primary" aria-live="polite">{{ $this->totalProducts }} {{ __('Produk') }}</p>
                </div>

                @if ($this->products->isEmpty())
                    <div class="flex flex-col items-center justify-center rounded-lg border border-line bg-white py-20 text-center dark:border-zinc-800 dark:bg-zinc-900">
                        <svg class="h-10 w-10 fill-none stroke-muted stroke-2" aria-hidden="true"><use href="#i-search"/></svg>
                        <h3 class="mt-3 text-[18px] font-bold text-ink dark:text-zinc-100">{{ __('Belum ada yang cocok') }}</h3>
                        <p class="mt-1 text-[15px] text-body-2 dark:text-zinc-300">{{ __('Coba longgarkan filter atau ubah kata kunci pencarian') }}</p>
                        <x-btn variant="primary" wire:click="clearFilters" class="mt-5">{{ __('Reset semua filter') }}</x-btn>
                    </div>
                @else
                    <h2 class="sr-only">{{ __('Daftar produk') }}</h2>
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4 lg:gap-5">
                        @foreach ($this->products as $product)
                            <x-product-card :product="$product" :wire:key="'p-'.$product->id" />
                        @endforeach
                    </div>

                    @if ($this->products->count() < $this->totalProducts)
                        <div class="mt-8 flex justify-center">
                            <x-btn variant="ghost" wire:click="loadMore" wire:loading.attr="disabled" class="border border-gray-300 dark:border-zinc-700">
                                <span wire:loading.remove wire:target="loadMore">{{ __('Lebih banyak') }}</span>
                                <span wire:loading wire:target="loadMore">{{ __('Memuat...') }}</span>
                            </x-btn>
                        </div>
                    @endif
                @endif
            </section>
        </div>
    </x-container>

    <div x-cloak x-show="drawer" class="fixed inset-0 z-[100] lg:hidden" x-transition.opacity>
        <div class="absolute inset-0 bg-black/50" @click="drawer = false"></div>
        <div class="absolute inset-x-0 bottom-0 flex max-h-[86vh] flex-col rounded-t-3xl bg-white dark:bg-zinc-900"
             x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
            <div class="flex items-center justify-between border-b border-line px-5 py-4 dark:border-zinc-800">
                <h3 class="text-heading text-[20px] text-ink dark:text-zinc-100">{{ __('Filter dan Urutkan') }}</h3>
                <button type="button" @click="drawer = false" aria-label="{{ __('Tutup') }}" class="grid h-10 w-10 place-items-center rounded-md bg-black/5 dark:bg-white/10">
                    <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-x"/></svg>
                </button>
            </div>
            <div class="overflow-y-auto px-5 py-2">
                @include('partials.storefront.catalog-filters')
            </div>
            <div class="flex gap-3 border-t border-line px-5 py-4 dark:border-zinc-800">
                <x-btn variant="outline" wire:click="clearFilters" class="flex-1">{{ __('Reset') }}</x-btn>
                <x-btn variant="primary" @click="drawer = false" class="flex-1">{{ __('Lihat hasil') }}</x-btn>
            </div>
        </div>
    </div>
</div>
</div>
