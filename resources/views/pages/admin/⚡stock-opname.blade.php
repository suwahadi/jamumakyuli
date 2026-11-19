<?php

use App\Models\Product;
use App\Models\StockOpname;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Stok Opname')] #[Layout('layouts::admin')] class extends Component {
    use WithPagination;

    public ?int $productId = null;

    public ?int $physicalStock = null;

    public array $fisik = [];

    public string $note = '';

    public string $cari = '';

    #[Computed]
    public function produkTerpilih(): ?Product
    {
        return $this->productId
            ? Product::query()->with('variants')->find($this->productId)
            : null;
    }

    #[Computed]
    public function hasilCari()
    {
        if ($this->cari === '' || $this->productId !== null) {
            return collect();
        }

        return Product::query()
            ->withCount('variants')
            ->cari($this->cari)
            ->orderBy('name')
            ->limit(8)
            ->get();
    }

    public function pilihProduk(int $id): void
    {
        $this->productId = $id;
        $this->cari = '';
    }

    public function gantiProduk(): void
    {
        $this->reset(['productId', 'cari', 'physicalStock', 'note', 'fisik']);
    }

    #[Computed]
    public function riwayat()
    {
        return StockOpname::query()
            ->with(['product', 'variant'])
            ->latest()
            ->paginate(15);
    }

    public function simpan(): void
    {
        $this->validate([
            'productId' => ['required', 'exists:products,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ], attributes: ['productId' => 'produk']);

        $produk = Product::query()->with('variants')->findOrFail($this->productId);

        $produk->hasVariants()
            ? $this->simpanVarian($produk)
            : $this->simpanSederhana($produk);

        $this->reset(['productId', 'physicalStock', 'note', 'fisik']);
        unset($this->riwayat);

        Flux::toast(variant: 'success', text: 'Opname tersimpan dan stok diselaraskan.');
    }

    private function simpanSederhana(Product $produk): void
    {
        $this->validate(['physicalStock' => ['required', 'integer', 'min:0']]);

        $fisik = (int) $this->physicalStock;
        $stokSistem = $produk->stock;

        $this->tulis(function () use ($produk, $stokSistem, $fisik): void {
            $this->catat($produk->id, null, $stokSistem, $fisik);
            $produk->update(['stock' => $fisik]);
        });
    }

    private function simpanVarian(Product $produk): void
    {
        $this->validate(['fisik.*' => ['nullable', 'integer', 'min:0']], attributes: ['fisik.*' => 'stok fisik']);

        $terisi = collect($this->fisik)
            ->reject(fn ($nilai) => $nilai === null || $nilai === '')
            ->mapWithKeys(fn ($nilai, $id) => [(int) $id => (int) $nilai]);

        if ($terisi->keys()->diff($produk->variants->pluck('id'))->isNotEmpty()) {
            throw ValidationException::withMessages([
                'fisik' => 'Ada varian yang bukan milik produk ini.',
            ]);
        }

        if ($terisi->isEmpty()) {
            throw ValidationException::withMessages([
                'fisik' => 'Isi minimal satu varian untuk disimpan.',
            ]);
        }

        $this->tulis(function () use ($produk, $terisi): void {
            foreach ($terisi as $variantId => $fisik) {
                $varian = $produk->variants->firstWhere('id', $variantId);

                $this->catat($produk->id, $varian->id, $varian->stock, $fisik);
                $varian->update(['stock' => $fisik]);
            }
        });
    }

    private function tulis(callable $penulisan): void
    {
        DB::transaction($penulisan);
    }

    private function catat(int $productId, ?int $variantId, int $stokSistem, int $fisik): void
    {
        StockOpname::query()->create([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'user_id' => auth()->id(),
            'system_stock' => $stokSistem,
            'physical_stock' => $fisik,
            'note' => $this->note !== '' ? $this->note : null,
        ]);
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:text-[28px]">Stok Opname</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">Rekonsiliasi stok sistem dengan hasil hitung fisik di gudang.</p>
    </div>

    <section class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
            <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Input Opname</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">Pilih produk, masukkan hasil hitung fisik, lalu simpan.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 sm:p-6">
            <div class="sm:col-span-2">
                <label for="opname-cari" class="lbl">Produk <span class="req">*</span></label>

                @if ($this->produkTerpilih)
                    <div class="flex items-center justify-between gap-3 rounded-md border border-gray-300 bg-gray-50 px-3.5 py-2.5 dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-800 dark:text-zinc-100">{{ $this->produkTerpilih->name }}</p>
                            <p class="font-mono text-xs text-gray-500 dark:text-zinc-400">{{ $this->produkTerpilih->sku }}</p>
                        </div>
                        <button type="button" wire:click="gantiProduk" class="shrink-0 font-display text-xs font-bold text-brand-green hover:underline">Ganti</button>
                    </div>
                @else
                    <input type="search" id="opname-cari" wire:model.live.debounce.300ms="cari" placeholder="Cari nama produk atau SKU..." class="field">

                    @if ($this->cari !== '')
                        <div class="mt-2 max-h-56 divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200 dark:divide-zinc-800 dark:border-zinc-800">
                            @forelse ($this->hasilCari as $produk)
                                <button type="button" wire:click="pilihProduk({{ $produk->id }})" wire:key="cari-{{ $produk->id }}" class="flex w-full items-center justify-between gap-3 px-3.5 py-2.5 text-left text-sm transition hover:bg-gray-50 dark:hover:bg-zinc-800">
                                    <span class="min-w-0 truncate font-medium text-gray-800 dark:text-zinc-100">{{ $produk->name }}</span>
                                    <span class="shrink-0 font-mono text-xs text-gray-500 dark:text-zinc-400">
                                        {{ $produk->sku }}@if ($produk->variants_count > 0) · {{ $produk->variants_count }} varian @endif
                                    </span>
                                </button>
                            @empty
                                <p class="px-3.5 py-3 text-center text-xs text-gray-500 dark:text-zinc-400">Produk tidak ditemukan.</p>
                            @endforelse
                        </div>
                    @endif
                @endif

                <flux:error name="productId" />
            </div>

            @if ($this->produkTerpilih?->hasVariants())
                <div class="sm:col-span-2">
                    <label class="lbl">Hasil Hitung per Varian <span class="req">*</span></label>
                    <p class="mb-2 text-xs text-gray-500 dark:text-zinc-400">Kosongkan varian yang belum dihitung. Stoknya tidak akan disentuh.</p>

                    <div class="overflow-x-auto rounded-md border border-gray-200 dark:border-zinc-800">
                        <table class="w-full min-w-[520px] text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50/60 text-left font-display text-xs font-bold uppercase tracking-wide text-gray-400 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                                    <th class="px-4 py-2.5">Varian</th>
                                    <th class="px-4 py-2.5">SKU</th>
                                    <th class="px-4 py-2.5 text-right">Stok Sistem</th>
                                    <th class="w-36 px-4 py-2.5">Stok Fisik</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                @foreach ($this->produkTerpilih->variants as $varian)
                                    <tr wire:key="varian-{{ $varian->id }}">
                                        <td class="px-4 py-2.5 font-medium text-gray-800 dark:text-zinc-100">{{ $varian->name }}</td>
                                        <td class="px-4 py-2.5 font-mono text-xs text-gray-500 dark:text-zinc-400">{{ $varian->sku }}</td>
                                        <td class="px-4 py-2.5 text-right font-display font-bold text-gray-500 dark:text-zinc-400">{{ $varian->stock }}</td>
                                        <td class="px-4 py-2">
                                            <input type="number" min="0" step="1" wire:model="fisik.{{ $varian->id }}" aria-label="Stok fisik {{ $varian->name }}" class="field">
                                            <flux:error name="fisik.{{ $varian->id }}" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <flux:error name="fisik" :deep="false" />
                </div>
            @else
                <div>
                    <label class="lbl">Stok Sistem</label>
                    <p class="flex h-[42px] items-center rounded-lg border border-gray-300 bg-gray-50 px-3.5 font-display text-sm font-bold text-gray-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">{{ $this->produkTerpilih->stock ?? '-' }}</p>
                </div>

                <div>
                    <label for="opname-fisik" class="lbl">Stok Fisik <span class="req">*</span></label>
                    <input type="number" id="opname-fisik" min="0" step="1" wire:model="physicalStock" placeholder="cth. 15" class="field">
                    <flux:error name="physicalStock" />
                </div>
            @endif

            <div class="sm:col-span-2">
                <label for="opname-catatan" class="lbl">Catatan</label>
                <textarea id="opname-catatan" rows="3" wire:model="note" placeholder="Catatan hasil opname (opsional)" class="field"></textarea>
                <flux:error name="note" />
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
            <flux:button wire:click="simpan" variant="primary" class="cursor-pointer">Simpan Opname</flux:button>
        </div>
    </section>

    <section class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
            <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Riwayat Opname</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/60 text-left font-display text-xs font-bold uppercase tracking-wide text-gray-400 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                        <th class="px-5 py-3.5 sm:px-6">Produk</th>
                        <th class="px-3 py-3.5 text-center">Stok Sistem</th>
                        <th class="px-3 py-3.5 text-center">Stok Fisik</th>
                        <th class="px-3 py-3.5 text-center">Selisih</th>
                        <th class="px-3 py-3.5">Status</th>
                        <th class="px-3 py-3.5 sm:px-6">Tanggal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                    @forelse ($this->riwayat as $opname)
                        <tr class="transition hover:bg-gray-50/70 dark:hover:bg-zinc-800/50" wire:key="op-{{ $opname->id }}">
                            <td class="px-5 py-3.5 sm:px-6">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-gray-800 dark:text-zinc-100">{{ $opname->product->name }}</p>
                                    @if ($opname->variant)
                                        <p class="truncate text-xs text-gray-500 dark:text-zinc-400">{{ $opname->variant->name }}</p>
                                    @endif
                                </div>
                                <p class="font-mono text-[11px] text-gray-500 dark:text-zinc-400">{{ $opname->product->sku }}</p>
                            </td>
                            <td class="px-3 py-3.5 text-center font-display font-bold text-gray-700 dark:text-zinc-300">{{ $opname->system_stock }}</td>
                            <td class="px-3 py-3.5 text-center font-display font-bold text-gray-700 dark:text-zinc-300">{{ $opname->physical_stock }}</td>
                            <td class="px-3 py-3.5 text-center">
                                @if ($opname->difference === 0)
                                    <span class="font-display font-bold text-gray-500 dark:text-zinc-400">0</span>
                                @else
                                    <span class="font-display font-bold {{ $opname->difference > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-brand-green' }}">{{ $opname->difference > 0 ? '+' : '' }}{{ $opname->difference }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5">
                                @if ($opname->difference === 0)
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Sesuai</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-brand-green/10 px-2.5 py-1 text-xs font-semibold text-brand-green"><span class="h-1.5 w-1.5 rounded-full bg-brand-green"></span>Selisih</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5 text-gray-600 dark:text-zinc-400 sm:px-6">{{ tanggal_id($opname->created_at) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-zinc-400">Belum ada riwayat opname.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-5 dark:border-zinc-800 sm:p-6">
            <p class="text-xs text-gray-500 dark:text-zinc-400">Menampilkan {{ $this->riwayat->count() }} dari {{ $this->riwayat->total() }}</p>
            {{ $this->riwayat->links() }}
        </div>
    </section>
</div>
