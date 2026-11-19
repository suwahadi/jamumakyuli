<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Variant;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Produk')] #[Layout('layouts::admin')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $cari = '';

    public string $filterKategori = '';

    public string $filterStatus = '';

    public ?int $deletingId = null;

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    public function updatedFilterKategori(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function baris()
    {
        return Product::query()
            ->with('category', 'variants', 'images')
            ->withCount(['orderItems', 'variants'])
            ->cari($this->cari)
            ->when($this->filterKategori !== '', fn ($q) => $q->where('category_id', $this->filterKategori))
            ->when($this->filterStatus !== '', fn ($q) => $q->where('is_active', $this->filterStatus === 'aktif'))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function kategoriOptions()
    {
        return Category::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function totalProduk(): int
    {
        return Product::query()->count();
    }

    #[Computed]
    public function produkAktif(): int
    {
        return Product::query()->where('is_active', true)->count();
    }

    #[Computed]
    public function stokMenipis(): int
    {
        return Product::query()->active()->stokMenipis()->count();
    }

    #[Computed]
    public function nilaiPersediaan(): int
    {
        $induk = (int) Product::query()
            ->doesntHave('variants')
            ->selectRaw('SUM(CASE WHEN stock > 0 THEN stock * COALESCE(promo_price, original_price) ELSE 0 END) as total')
            ->value('total');

        $varian = (int) Variant::query()
            ->selectRaw('SUM(CASE WHEN stock > 0 THEN stock * COALESCE(promo_price, price) ELSE 0 END) as total')
            ->value('total');

        return $induk + $varian;
    }

    public function toggleActive(int $id): void
    {
        $product = Product::query()->findOrFail($id);
        $product->update(['is_active' => ! $product->is_active]);
        Flux::toast(variant: 'success', text: $product->is_active ? 'Produk diaktifkan.' : 'Produk dinonaktifkan.');
    }

    public function confirmDelete(int $id): void
    {
        $product = Product::query()->withCount('orderItems')->findOrFail($id);

        if ($product->order_items_count > 0) {
            Flux::toast(variant: 'warning', text: 'Produk sudah memiliki riwayat transaksi sehingga tidak bisa dihapus. Nonaktifkan saja.');

            return;
        }

        $this->deletingId = $id;
        Flux::modal('product-delete')->show();
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            try {
                Product::query()->whereKey($this->deletingId)->delete();
                Flux::toast(variant: 'success', text: 'Produk dihapus.');
            } catch (QueryException) {
                Flux::toast(variant: 'danger', text: 'Produk tidak dapat dihapus karena masih terkait data lain.');
            }
        }

        Flux::modal('product-delete')->close();
        $this->reset('deletingId');
    }
}; ?>

<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <h1 class="font-display text-2xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:text-[28px]">Produk</h1>
        <flux:button :href="route('admin.products.create')" wire:navigate variant="primary" size="sm" class="cursor-pointer">Produk Baru</flux:button>
    </div>

    <section class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-admin.summary-card label="Total Produk" :value="$this->totalProduk" icon="package" tone="bg-brand-green/10 text-brand-green" />
        <x-admin.summary-card label="Produk Aktif" :value="$this->produkAktif" icon="check-circle-2" tone="bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400" />
        <x-admin.summary-card class="col-span-2 sm:col-span-1" label="Stok Menipis" :value="$this->stokMenipis" icon="alert-triangle" tone="bg-brand-turmeric/15 text-brand-turmeric-ink" />
        <x-admin.summary-card class="col-span-2 sm:col-span-1" label="Nilai Persediaan" :value="rupiah($this->nilaiPersediaan)" icon="piggy-bank" tone="bg-sky-50 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400" />
    </section>

    <div class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3 p-5 sm:p-6">
            <label class="min-w-0 flex-1">
                <span class="sr-only">Cari</span>
                <input type="search" wire:model.live.debounce.400ms="cari" placeholder="Cari nama produk atau SKU..." class="field">
            </label>
            <select wire:model.live="filterKategori" aria-label="Saring produk menurut kategori" class="field w-full sm:w-auto">
                <option value="">Semua Kategori</option>
                @foreach ($this->kategoriOptions as $kategori)
                    <option value="{{ $kategori->id }}">{{ $kategori->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterStatus" aria-label="Saring produk menurut status" class="field w-full sm:w-auto">
                <option value="">Semua Status</option>
                <option value="aktif">Aktif</option>
                <option value="nonaktif">Nonaktif</option>
            </select>
        </div>

        <ul class="divide-y divide-gray-100 border-t border-gray-100 dark:divide-zinc-800 dark:border-zinc-800 sm:hidden">
            @forelse ($this->baris as $product)
                <li class="flex gap-3 px-5 py-4" wire:key="prod-hp-{{ $product->id }}">
                    @if ($product->thumbnailUrl())
                        <img src="{{ $product->thumbnailUrl() }}" alt="{{ $product->name }}" class="size-14 shrink-0 rounded-md border border-gray-200 object-cover dark:border-zinc-700">
                    @else
                        <span class="grid size-14 shrink-0 place-items-center rounded-md bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-zinc-400">
                            <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-image"/></svg>
                        </span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('admin.products.edit', $product) }}" wire:navigate class="line-clamp-2 text-sm font-semibold leading-snug text-gray-800 dark:text-zinc-100">{{ $product->name }}</a>
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                            <span class="font-display text-sm font-bold text-brand-green">{{ rupiah($product->variants_count > 0 ? $product->fromPrice() : ($product->isOnPromo() ? $product->promo_price : $product->original_price)) }}</span>
                            <span class="text-gray-600 dark:text-zinc-300">Stok <span class="font-bold {{ $product->totalStock() === 0 ? 'text-brand-green' : ($product->stokTerendah() <= \App\Models\Product::AMBANG_STOK_MENIPIS ? 'text-brand-turmeric-ink' : '') }}">{{ $product->totalStock() }}</span></span>
                        </div>
                        <div class="mt-2 flex items-center gap-1">
                            <button type="button" wire:click="toggleActive({{ $product->id }})" class="mr-auto inline-flex min-h-11 cursor-pointer items-center">
                                @if ($product->is_active)
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Aktif</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-zinc-800 dark:text-zinc-400"><span class="h-1.5 w-1.5 rounded-full bg-gray-400 dark:bg-zinc-600"></span>Nonaktif</span>
                                @endif
                            </button>
                            @if ($product->is_active)
                                <flux:button :href="route('products.show', $product)" target="_blank" variant="ghost" icon="eye" aria-label="Lihat produk di toko (tab baru)" class="!size-11 cursor-pointer" />
                            @endif
                            <flux:button :href="route('admin.products.edit', $product)" wire:navigate variant="ghost" icon="pencil-square" aria-label="Ubah produk" class="!size-11 cursor-pointer" />
                            <flux:button wire:click="confirmDelete({{ $product->id }})" variant="ghost" icon="trash" aria-label="Hapus produk" class="!size-11 cursor-pointer" />
                        </div>
                    </div>
                </li>
            @empty
                <li class="px-5 py-12 text-center text-sm text-gray-500 dark:text-zinc-400">Tidak ada data yang cocok.</li>
            @endforelse
        </ul>

        <div class="hidden overflow-x-auto sm:block">
            <table class="w-full min-w-[820px] text-sm">
                <thead>
                    <tr class="border-y border-gray-100 bg-gray-50/60 text-left font-display text-xs font-bold uppercase tracking-wide text-gray-400 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                        <th class="px-5 py-3.5 sm:px-6">Produk</th>
                        <th class="px-3 py-3.5">SKU</th>
                        <th class="px-3 py-3.5">Kategori</th>
                        <th class="px-3 py-3.5">Harga</th>
                        <th class="px-3 py-3.5">Stok</th>
                        <th class="px-3 py-3.5">Status</th>
                        <th class="px-3 py-3.5 text-right sm:px-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                    @forelse ($this->baris as $product)
                        <tr class="transition hover:bg-gray-50/70 dark:hover:bg-zinc-800/50" wire:key="prod-{{ $product->id }}">
                            <td class="px-5 py-3.5 sm:px-6">
                                <div class="flex items-center gap-3">
                                    @if ($product->thumbnailUrl())
                                        <img src="{{ $product->thumbnailUrl() }}" alt="{{ $product->name }}" class="h-10 w-10 shrink-0 rounded-md border border-gray-200 object-cover dark:border-zinc-700">
                                    @else
                                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-gray-100 text-gray-400 dark:bg-zinc-800 dark:text-zinc-400">
                                            <svg class="h-4 w-4 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-image"/></svg>
                                        </span>
                                    @endif
                                    <span class="font-semibold leading-tight text-gray-800 dark:text-zinc-100">{{ $product->name }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-3.5"><span class="font-mono text-[13px] text-gray-500 dark:text-zinc-400">{{ $product->sku }}</span></td>
                            <td class="px-3 py-3.5"><span class="inline-flex rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $product->category->name }}</span></td>
                            <td class="whitespace-nowrap px-3 py-3.5">
                                @if ($product->variants_count > 0)
                                    <span class="text-[11px] text-gray-500 dark:text-zinc-400">mulai </span><span class="font-display font-bold text-brand-green">{{ rupiah($product->fromPrice()) }}</span>
                                @elseif ($product->isOnPromo())
                                    <span class="block text-[11px] leading-none text-gray-400 line-through dark:text-zinc-400">{{ rupiah($product->original_price) }}</span>
                                    <span class="font-display font-bold text-brand-green">{{ rupiah($product->promo_price) }}</span>
                                @else
                                    <span class="font-display font-bold text-brand-green">{{ rupiah($product->original_price) }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5">
                                <span class="font-display font-bold {{ $product->totalStock() === 0 ? 'text-brand-green' : ($product->stokTerendah() <= \App\Models\Product::AMBANG_STOK_MENIPIS ? 'text-brand-turmeric-ink' : 'text-gray-800 dark:text-zinc-100') }}">{{ $product->totalStock() }}</span>
                                @if ($product->variants_count > 0)
                                    <span class="text-[11px] text-gray-500 dark:text-zinc-400">({{ $product->variants_count }} var)</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5">
                                <button type="button" wire:click="toggleActive({{ $product->id }})" class="cursor-pointer">
                                    @if ($product->is_active)
                                        <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Aktif</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500 dark:bg-zinc-800 dark:text-zinc-400"><span class="h-1.5 w-1.5 rounded-full bg-gray-400 dark:bg-zinc-600"></span>Nonaktif</span>
                                    @endif
                                </button>
                            </td>
                            <td class="px-3 py-3.5 sm:px-6">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($product->is_active)
                                        <flux:button :href="route('products.show', $product)" target="_blank" size="xs" variant="ghost" icon="eye" aria-label="Lihat produk di toko (tab baru)" class="cursor-pointer" />
                                    @endif
                                    <flux:button :href="route('admin.products.edit', $product)" wire:navigate size="xs" variant="ghost" icon="pencil-square" aria-label="Ubah produk" class="cursor-pointer" />
                                    <flux:button wire:click="confirmDelete({{ $product->id }})" size="xs" variant="ghost" icon="trash" aria-label="Hapus produk" class="cursor-pointer" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-zinc-400">Tidak ada data yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-5 dark:border-zinc-800 sm:p-6">
            <p class="text-xs text-gray-500 dark:text-zinc-400">Menampilkan {{ $this->baris->count() }} dari {{ $this->baris->total() }}</p>
            {{ $this->baris->links() }}
        </div>
    </div>

    <flux:modal name="product-delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Produk</flux:heading>
                <flux:subheading>Yakin untuk menghapus data ini? Tindakan ini tidak dapat dibatalkan.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="outline">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger">Ya, hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
