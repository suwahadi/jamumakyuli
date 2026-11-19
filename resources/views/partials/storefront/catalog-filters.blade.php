<div>
    <label class="mb-5 flex h-11 items-center gap-2 rounded-lg border border-line px-3 transition-colors focus-within:border-brand-green focus-within:ring-4 focus-within:ring-green-100 dark:border-zinc-800 dark:focus-within:ring-green-950">
        <span class="sr-only">{{ __('Cari produk') }}</span>
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Cari nama atau SKU') }}"
               class="w-full bg-transparent text-[16px] placeholder:text-muted focus:outline-none dark:text-zinc-100">
    </label>

    <details class="filter border-t border-line dark:border-zinc-800" open>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-3 text-[13px] font-semibold uppercase tracking-wide text-ink dark:text-zinc-100">
            {{ __('Urutkan') }}
            <svg class="f-chev h-4 w-4 shrink-0 fill-none stroke-muted stroke-2" aria-hidden="true"><use href="#i-chev"/></svg>
        </summary>
        <div class="pb-3 text-[14px] text-body-2 dark:text-zinc-300">
            @foreach (['baru' => __('Terbaru'), 'murah' => __('Harga Terendah'), 'mahal' => __('Harga Tertinggi'), 'nama' => __('Nama A sampai Z')] as $val => $label)
                <label class="-mx-2 flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 transition-colors hover:bg-brand-pink-soft hover:text-brand-green dark:hover:bg-green-950">
                    <input type="radio" wire:model.live="sort" value="{{ $val }}" class="h-4 w-4 shrink-0"> {{ $label }}
                </label>
            @endforeach
        </div>
    </details>

    <details class="filter border-t border-line dark:border-zinc-800" open>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-3 text-[13px] font-semibold uppercase tracking-wide text-ink dark:text-zinc-100">
            {{ __('Kategori') }}
            <svg class="f-chev h-4 w-4 shrink-0 fill-none stroke-muted stroke-2" aria-hidden="true"><use href="#i-chev"/></svg>
        </summary>
        <div class="pb-3 text-[14px] text-body-2 dark:text-zinc-300">
            @foreach ($this->categories as $category)
                <label class="-mx-2 flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 transition-colors hover:bg-brand-pink-soft hover:text-brand-green dark:hover:bg-green-950">
                    <input type="checkbox" wire:model.live="cats" value="{{ $category->slug }}" class="h-4 w-4 shrink-0"> {{ $category->name }}
                </label>
            @endforeach
        </div>
    </details>

    <details class="filter border-y border-line dark:border-zinc-800" open>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-3 text-[13px] font-semibold uppercase tracking-wide text-ink dark:text-zinc-100">
            {{ __('Harga') }}
            <svg class="f-chev h-4 w-4 shrink-0 fill-none stroke-muted stroke-2" aria-hidden="true"><use href="#i-chev"/></svg>
        </summary>
        <div class="pb-3 text-[14px] text-body-2 dark:text-zinc-300">
            @foreach (['a' => __('Di bawah Rp 500 ribu'), 'b' => __('Rp 500 ribu sampai Rp 1,5 juta'), 'c' => __('Di atas Rp 1,5 juta')] as $val => $label)
                <label class="-mx-2 flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 transition-colors hover:bg-brand-pink-soft hover:text-brand-green dark:hover:bg-green-950">
                    <input type="checkbox" wire:model.live="bands" value="{{ $val }}" class="h-4 w-4 shrink-0"> {{ $label }}
                </label>
            @endforeach
        </div>
    </details>

    <x-btn variant="outline" wire:click="clearFilters" class="mt-5 hidden w-full lg:inline-flex">
        {{ __('Reset Filter') }}
    </x-btn>
</div>
