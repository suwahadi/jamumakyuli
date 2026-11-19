<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StockOpname;
use App\Models\Variant;
use App\Support\AturanSku;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Form Produk')] #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    public ?Product $product = null;

    public ?int $category_id = null;

    public string $name = '';

    public string $slug = '';

    public string $sku = '';

    public ?int $original_price = null;

    public ?int $promo_price = null;

    public string $description = '';

    public ?int $weight = null;

    public int $stock = 0;

    #[Locked]
    public ?int $loadedStock = null;

    #[Locked]
    public array $loadedVariantStock = [];

    public bool $is_active = true;

    public array $variants = [];

    public array $existingImages = [];

    public array $photos = [];

    public array $removedImageIds = [];

    public ?string $mainKey = null;

    public array $imageOrder = [];

    private array $slotKeyToId = [];

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            $this->product = $product;
            $product->load(['variants', 'images']);
            $this->fill($product->only([
                'category_id', 'name', 'slug', 'sku', 'original_price', 'promo_price', 'description', 'weight', 'stock', 'is_active',
            ]));
            $this->loadedStock = (int) $product->stock;
            $this->loadedVariantStock = $product->variants->pluck('stock', 'id')->map(fn ($stock): int => (int) $stock)->all();
            $this->variants = $this->variantsHidden() ? [] : $product->variants->map(fn (Variant $v): array => [
                'id' => $v->id,
                'name' => $v->name,
                'sku' => $v->sku,
                'price' => $v->price,
                'promo_price' => $v->promo_price,
                'stock' => $v->stock,
                'weight' => $v->weight,
                'image_key' => $v->image_id ? "id:{$v->image_id}" : null,
            ])->all();

            $this->existingImages = $product->images->map(fn (ProductImage $img): array => [
                'id' => $img->id,
                'url' => $img->url,
                'is_main' => $img->is_main,
                'sort_order' => $img->sort_order,
            ])->all();

            $main = $product->images->firstWhere('is_main', true) ?? $product->images->first();
            $this->mainKey = $main ? "id:{$main->id}" : null;
        } else {
            $this->category_id = Category::query()->value('id');
        }
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->orderBy('name')->get();
    }

    #[Computed]
    public function imageSlots(): array
    {
        $slots = [];

        foreach ($this->existingImages as $img) {
            if (! in_array($img['id'], $this->removedImageIds, true)) {
                $slots[] = ['key' => "id:{$img['id']}", 'url' => $img['url'], 'new' => false, 'filename' => null];
            }
        }

        foreach ($this->photos as $photo) {
            $slots[] = ['key' => "tmp:{$photo->getFilename()}", 'url' => $photo->temporaryUrl(), 'new' => true, 'filename' => $photo->getFilename()];
        }

        $posisi = array_flip($this->imageOrder);
        usort($slots, fn (array $a, array $b): int => ($posisi[$a['key']] ?? PHP_INT_MAX) <=> ($posisi[$b['key']] ?? PHP_INT_MAX));

        return $slots;
    }

    public function sortImages(string $key, int $position): void
    {
        $keys = array_column($this->imageSlots(), 'key');

        if (! in_array($key, $keys, true)) {
            return;
        }

        $keys = array_values(array_diff($keys, [$key]));
        array_splice($keys, max(0, $position), 0, [$key]);
        $this->imageOrder = $keys;
        unset($this->imageSlots);
    }

    public function removeExisting(int $id): void
    {
        $this->removedImageIds[] = $id;
        $this->reconcileImageKeys();
        unset($this->imageSlots);
    }

    public function removeNewPhoto(string $filename): void
    {
        $this->photos = array_values(array_filter(
            $this->photos,
            fn ($photo): bool => $photo->getFilename() !== $filename,
        ));
        $this->reconcileImageKeys();
        unset($this->imageSlots);
    }

    public function updatedPhotos(): void
    {
        unset($this->imageSlots);
    }

    private function reconcileImageKeys(): void
    {
        $keys = array_column($this->imageSlots(), 'key');

        if ($this->mainKey !== null && ! in_array($this->mainKey, $keys, true)) {
            $this->mainKey = null;
        }

        foreach ($this->variants as $i => $variant) {
            if (($variant['image_key'] ?? null) !== null && ! in_array($variant['image_key'], $keys, true)) {
                $this->variants[$i]['image_key'] = null;
            }
        }
    }

    public function addVariant(): void
    {
        $this->variants[] = ['id' => null, 'name' => '', 'sku' => '', 'price' => null, 'promo_price' => null, 'stock' => 0, 'weight' => null, 'image_key' => null];
    }

    public function removeVariant(int $index): void
    {
        unset($this->variants[$index]);
        $this->variants = array_values($this->variants);
    }

    public function variantsHidden(): bool
    {
        return (bool) config('storefront.hidden.product_variants');
    }

    public function save(): void
    {
        if ($this->variantsHidden()) {
            $this->variants = [];
        }

        $productId = $this->product?->id;
        $this->slug = Str::slug($this->slug);
        $adaVarian = $this->variants !== [];
        $wajibTanpaVarian = $adaVarian ? 'nullable' : 'required';

        if ($adaVarian && trim($this->sku) === '') {
            $this->sku = trim((string) $this->variants[0]['sku']);
        }

        $hargaVarianTermurah = $adaVarian && $this->original_price === null
            ? collect($this->variants)->pluck('price')->filter(fn ($harga): bool => is_numeric($harga))->min()
            : null;

        try {
            $validated = $this->validate([
                'category_id' => ['required', 'exists:categories,id'],
                'name' => ['required', 'string', 'max:255'],
                'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId)],
                'sku' => [$wajibTanpaVarian, 'string', 'max:100'],
                'original_price' => [$wajibTanpaVarian, 'integer', 'min:0'],
                'promo_price' => ['nullable', 'integer', 'min:0', ...match (true) {
                    $this->original_price !== null => ['lt:original_price'],
                    $hargaVarianTermurah !== null => ['lt:'.$hargaVarianTermurah],
                    default => [],
                }],
                'description' => ['required', 'string'],
                'weight' => [$wajibTanpaVarian, 'integer', 'min:1'],
                'stock' => [$wajibTanpaVarian, 'integer', 'min:0'],
                'is_active' => ['boolean'],
                'photos' => ['array'],
                'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'variants' => ['array'],
                'variants.*.name' => ['required', 'string', 'max:255'],
                'variants.*.sku' => ['required', 'string', 'max:100'],
                'variants.*.price' => ['required', 'integer', 'min:0'],
                'variants.*.promo_price' => ['nullable', 'integer', 'min:0'],
                'variants.*.stock' => ['required', 'integer', 'min:0'],
                'variants.*.weight' => ['required', 'integer', 'min:1'],
                'variants.*.image_key' => ['nullable', 'string'],
            ], messages: [
                'slug.unique' => 'Slug sudah dipakai produk lain.',
                ...($hargaVarianTermurah === null ? [] : [
                    'promo_price.lt' => 'Harga promo harus di bawah harga varian termurah ('.rupiah((int) $hargaVarianTermurah).') karena harga asli diambil dari harga itu.',
                ]),
            ], attributes: [
                'category_id' => 'kategori',
                'name' => 'nama produk',
                'slug' => 'slug',
                'sku' => 'SKU produk',
                'description' => 'deskripsi',
                'stock' => 'stok',
                'original_price' => 'harga asli',
                'promo_price' => 'harga promo',
                'weight' => 'berat',
                'photos.*' => 'gambar',
                'variants.*.name' => 'nama varian',
                'variants.*.sku' => 'SKU varian',
                'variants.*.price' => 'harga varian',
                'variants.*.stock' => 'stok varian',
                'variants.*.weight' => 'berat varian',
            ]);
        } catch (ValidationException $e) {
            $this->dispatch('form-invalid');

            throw $e;
        }

        if (count($this->imageSlots()) > 6) {
            $this->addError('photos', 'Maksimal 6 gambar per produk. Hapus sebagian sebelum menambah.');

            return;
        }

        $aturanValid = $this->variantsHidden() ? $this->validasiSkuProdukLain() : $this->validateVariantBusinessRules();

        if (! $aturanValid) {
            $this->dispatch('form-invalid');

            return;
        }

        $data = collect($validated)->except(['variants', 'photos'])->all();

        if ($adaVarian) {
            $data['sku'] = $this->sku;
            $data['original_price'] ??= (int) collect($this->variants)->min(fn (array $v): int => (int) $v['price']);
            $data['weight'] ??= (int) $this->variants[0]['weight'];
        }

        $data['slug'] = $this->slug !== '' ? $this->slug : Product::uniqueSlug($this->name, $productId);

        if ($this->product?->exists && (int) ($data['stock'] ?? 0) === $this->loadedStock) {
            unset($data['stock']);
        }

        if ($this->product?->exists) {
            $this->product->update($data);
        } else {
            $this->product = Product::query()->create($data);
        }

        $this->persistImages();

        if (! $this->variantsHidden()) {
            $this->syncVariants();
        }

        Flux::toast(variant: 'success', text: 'Produk berhasil disimpan.');

        if ($productId) {
            $this->redirectRoute('admin.products.edit', $this->product, navigate: true);
        } else {
            $this->redirectRoute('admin.products.index', navigate: true);
        }
    }

    private function persistImages(): void
    {
        foreach ($this->removedImageIds as $id) {
            $this->product->images()->whereKey($id)->get()->each->delete();
        }

        $this->slotKeyToId = [];
        foreach ($this->product->images()->get() as $img) {
            $this->slotKeyToId["id:{$img->id}"] = $img->id;
        }

        foreach ($this->photos as $photo) {
            $row = $this->product->images()->create([
                'path' => store_webp($photo, 'products'),
                'is_main' => false,
                'sort_order' => 0,
            ]);
            $this->slotKeyToId["tmp:{$photo->getFilename()}"] = $row->id;
        }

        $this->product->images()->update(['is_main' => false]);
        $mainId = $this->slotKeyToId[$this->mainKey] ?? null;
        $mainId ??= $this->product->images()->orderBy('sort_order')->orderBy('id')->value('id');
        if ($mainId) {
            $this->product->images()->whereKey($mainId)->update(['is_main' => true]);
        }

        $order = 0;
        foreach ($this->imageSlots() as $slot) {
            $id = $this->slotKeyToId[$slot['key']] ?? null;
            if ($id) {
                $this->product->images()->whereKey($id)->update(['sort_order' => $order++]);
            }
        }
    }

    private function validasiSkuProdukLain(): bool
    {
        $normal = fn (string $sku): string => mb_strtoupper(trim($sku));
        $produkId = $this->product?->id;
        $milikProduk = $produkId
            ? array_map($normal, [$this->product->sku, ...$this->product->variants()->pluck('sku')])
            : [];

        $isian = ['sku' => $this->sku];

        foreach ($this->variants as $i => $variant) {
            $isian["variants.{$i}.sku"] = $variant['sku'];
        }

        $valid = true;

        foreach ($isian as $bidang => $sku) {
            if (in_array($normal($sku), $milikProduk, true)) {
                continue;
            }

            $pemakai = AturanSku::pemakaiLain($sku, $produkId);

            if ($pemakai) {
                $this->addError($bidang, "SKU ini sudah dipakai produk \"{$pemakai->name}\".");
                $valid = false;
            }
        }

        return $valid;
    }

    private function validateVariantBusinessRules(): bool
    {
        $valid = $this->validasiSkuProdukLain();
        $pasangan = [];

        foreach ($this->variants as $i => $variant) {
            $kunci = mb_strtoupper(trim($variant['sku']).'|'.trim($variant['name']));

            if (isset($pasangan[$kunci])) {
                $this->addError("variants.{$i}.sku", 'SKU dan nama varian ini sudah ada di produk ini.');
                $valid = false;
            }

            $pasangan[$kunci] = true;

            if ($variant['promo_price'] !== null && $variant['promo_price'] !== '' && (int) $variant['promo_price'] >= (int) $variant['price']) {
                $this->addError("variants.{$i}.promo_price", 'Harga promo harus lebih kecil dari harga varian.');
                $valid = false;
            }
        }

        if ($this->product?->exists) {
            $removedIds = $this->product->variants()
                ->whereNotIn('id', collect($this->variants)->pluck('id')->filter()->all() ?: [0])
                ->pluck('id');

            if ($removedIds->isNotEmpty() && StockOpname::query()->whereIn('variant_id', $removedIds)->exists()) {
                $this->addError('variants', 'Varian yang pernah dihitung di stok opname tidak bisa dihapus.');
                $valid = false;
            }
        }

        return $valid;
    }

    private function syncVariants(): void
    {
        $keepIds = collect($this->variants)->pluck('id')->filter()->all();

        try {
            $this->product->variants()->whereNotIn('id', $keepIds ?: [0])->delete();
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'variants' => 'Varian yang pernah dihitung di stok opname tidak bisa dihapus.',
            ]);
        }

        foreach ($this->variants as $variant) {
            $imageKey = $variant['image_key'] ?? null;

            $payload = [
                'name' => $variant['name'],
                'sku' => $variant['sku'],
                'price' => (int) $variant['price'],
                'promo_price' => filled($variant['promo_price']) ? (int) $variant['promo_price'] : null,
                'stock' => (int) $variant['stock'],
                'weight' => (int) $variant['weight'],
                'image_id' => $imageKey !== null ? ($this->slotKeyToId[$imageKey] ?? null) : null,
            ];

            if ($variant['id'] && (int) $variant['stock'] === ($this->loadedVariantStock[$variant['id']] ?? null)) {
                unset($payload['stock']);
            }

            if ($variant['id']) {
                $this->product->variants()->whereKey($variant['id'])->update($payload);
            } else {
                $this->product->variants()->create($payload);
            }
        }
    }
}; ?>

<div>
    <div class="flex flex-wrap items-center gap-3">
        <flux:button :href="route('admin.products.index')" wire:navigate size="sm" variant="ghost" icon="arrow-left" aria-label="Kembali ke daftar produk" />
        <h1 class="text-heading whitespace-nowrap text-[26px] text-zinc-900 sm:text-[30px] dark:text-white">{{ $product?->exists ? 'Ubah Produk' : 'Produk Baru' }}</h1>
        @if ($product?->exists && $product->is_active)
            <flux:button :href="route('products.show', $product)" target="_blank" variant="outline" icon="eye" aria-label="Lihat Produk di toko (tab baru)" class="ml-auto max-sm:!h-11">Lihat Produk</flux:button>
        @endif
    </div>

    <form wire:submit="save" class="mt-6 space-y-6"
          x-data="{
              scrollToError() {
                  requestAnimationFrame(() => {
                      const el = this.$el.querySelector('[data-invalid], [data-flux-error]');
                      if (! el) return;
                      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                      const field = el.matches('input, select, textarea') ? el : el.closest('[data-flux-field]')?.querySelector('input, select, textarea');
                      if (field) field.focus({ preventScroll: true });
                  });
              }
          }"
          x-on:form-invalid.window="scrollToError()">
        <section class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
                <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Informasi Dasar</h2>
            </div>
            <div class="space-y-4 p-5 sm:p-6">
                <div class="grid items-start gap-4 sm:grid-cols-2">
                    <flux:input wire:model="name" label="Nama Produk" label:badge="*" aria-required="true" />
                    <flux:input wire:model="slug" label="Slug" placeholder="Otomatis dari nama produk" maxlength="255" />
                    <flux:field>
                        <flux:label :badge="$variants === [] ? '*' : null"><span>{{ $this->variantsHidden() ? 'SKU Produk' : 'SKU Produk (induk)' }}@if ($variants !== [])<span class="font-normal text-gray-500 dark:text-zinc-400"> - Boleh dikosongkan, diisi otomatis dari SKU varian pertama.</span>@endif</span></flux:label>
                        <flux:input wire:model="sku" :aria-required="$variants === [] ? 'true' : 'false'" />
                        <flux:error name="sku" />
                    </flux:field>
                    <flux:select wire:model="category_id" label="Kategori" label:badge="*" aria-required="true">
                        @foreach ($this->categories as $cat)
                            <flux:select.option :value="$cat->id">{{ $cat->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <x-wysiwyg model="description" label="Deskripsi *" placeholder="Tulis deskripsi produk: bahan, cara merebus, aturan minum…" />
                <flux:checkbox wire:model="is_active" label="Aktif (tampil di toko)" />
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
                <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Harga dan Stok</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">{{ $this->variantsHidden() ? 'Harga promo boleh dikosongkan.' : ($variants === [] ? 'Wajib diisi untuk produk tanpa varian.' : 'Boleh dikosongkan karena produk ini memakai harga, berat, dan stok dari varian di bawah.') }}</p>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-4">
                <flux:input wire:model="original_price" type="number" label="Harga Asli (Rp)" :label:badge="$variants === [] ? '*' : null" :aria-required="$variants === [] ? 'true' : 'false'" />
                <flux:input wire:model="promo_price" type="number" label="Harga Promo (Rp)" />
                <flux:input wire:model="weight" type="number" label="Berat (gram)" :label:badge="$variants === [] ? '*' : null" :aria-required="$variants === [] ? 'true' : 'false'" />
                <flux:input wire:model="stock" type="number" min="0" label="Stok" :label:badge="$variants === [] ? '*' : null" :aria-required="$variants === [] ? 'true' : 'false'" />
            </div>
        </section>

        @unless ($this->variantsHidden())
        <section class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
                <div>
                    <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Varian (opsional)</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">Jika diisi, pembeli wajib memilih varian dan harga/stok mengikuti varian.</p>
                </div>
                <flux:button type="button" wire:click="addVariant" size="sm" variant="outline" icon="plus" class="shrink-0 cursor-pointer">Tambah Varian</flux:button>
            </div>
            <div class="space-y-3 p-5 sm:p-6">
                @forelse ($variants as $i => $variant)
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/40" wire:key="var-{{ $i }}">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                            <flux:input wire:model="variants.{{ $i }}.name" label="Nama" label:badge="*" aria-required="true" placeholder="100mm" class="col-span-2 sm:col-span-1" />
                            <flux:input wire:model="variants.{{ $i }}.sku" label="SKU" label:badge="*" aria-required="true" placeholder="A001-01" class="col-span-2 sm:col-span-1" />
                            <flux:input wire:model="variants.{{ $i }}.price" type="number" label="Harga" label:badge="*" aria-required="true" />
                            <flux:input wire:model="variants.{{ $i }}.promo_price" type="number" label="Promo" />
                            <flux:input wire:model="variants.{{ $i }}.stock" type="number" label="Stok" label:badge="*" aria-required="true" />
                            <flux:input wire:model="variants.{{ $i }}.weight" type="number" label="Berat" label:badge="*" aria-required="true" />
                        </div>
                        @if (count($this->imageSlots()))
                            <div class="mt-3">
                                <flux:select wire:model="variants.{{ $i }}.image_key" label="Gambar varian (opsional)" class="sm:max-w-xs">
                                    <flux:select.option value="">Ikuti gambar utama</flux:select.option>
                                    @foreach ($this->imageSlots() as $slot)
                                        <flux:select.option value="{{ $slot['key'] }}">Gambar #{{ $loop->iteration }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                        @endif
                        <div class="mt-2 flex justify-end">
                            <flux:button type="button" wire:click="removeVariant({{ $i }})" size="sm" variant="ghost" icon="trash" class="cursor-pointer !text-red-600 hover:!bg-red-50 dark:!text-red-400 dark:hover:!bg-red-500/10">Hapus Varian</flux:button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Belum ada varian. Produk akan dijual dengan harga & stok produk di atas.</p>
                @endforelse

                <flux:error name="variants" :deep="false" />
            </div>
        </section>
        @endunless

        <section class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
                <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Gambar</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">Maksimal 6 gambar. JPG/PNG/WebP ≤5MB. Klik gambar untuk memperbesar, geser untuk mengubah urutan (keyboard: panah kiri/kanan).</p>
            </div>
            <div class="p-5 sm:p-6" x-data="productLightbox()">
                @if (count($this->imageSlots()))
                    @php($slotUrls = array_column($this->imageSlots(), 'url'))
                    <div wire:sort="sortImages" wire:sort:config="{ delay: 150, delayOnTouchOnly: true }" class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
                        @foreach ($this->imageSlots() as $slot)
                            <div wire:key="slot-{{ $slot['key'] }}" wire:sort:item="{{ $slot['key'] }}"
                                 class="relative overflow-hidden rounded-lg border bg-white dark:bg-zinc-900 {{ $mainKey === $slot['key'] ? 'border-primary ring-2 ring-primary' : 'border-zinc-200 dark:border-zinc-700' }}">
                                <button type="button" @click="open({{ $loop->index }}, @js($slotUrls))"
                                        @keydown.arrow-left.prevent="isOpen || $wire.sortImages(@js($slot['key']), {{ $loop->index - 1 }})"
                                        @keydown.arrow-right.prevent="isOpen || $wire.sortImages(@js($slot['key']), {{ $loop->index + 1 }})"
                                        aria-label="Perbesar gambar #{{ $loop->iteration }}" class="block w-full cursor-zoom-in">
                                    <img src="{{ $slot['url'] }}" alt="" draggable="false" class="aspect-square w-full object-cover" />
                                </button>
                                <span class="pointer-events-none absolute left-1 top-1 rounded-sm bg-slate-900/70 px-1.5 py-0.5 font-mono text-[10px] font-bold text-white">#{{ $loop->iteration }}</span>
                                @if ($mainKey === $slot['key'])
                                    <span class="pointer-events-none absolute right-1 top-1 rounded-sm bg-primary px-1.5 py-0.5 font-mono text-[10px] font-bold text-white">UTAMA</span>
                                @endif
                                <div wire:sort:ignore class="flex items-center justify-between gap-1 border-t border-zinc-100 p-1.5 dark:border-zinc-700">
                                    @if ($mainKey === $slot['key'])
                                        <span class="text-[10px] text-zinc-500 dark:text-zinc-400">Gambar utama</span>
                                    @else
                                        <button type="button" wire:click="$set('mainKey', '{{ $slot['key'] }}')" class="text-[10px] font-medium text-primary hover:underline dark:text-white">Jadikan utama</button>
                                    @endif
                                    @if ($slot['new'])
                                        <flux:button type="button" size="xs" variant="subtle" icon="trash" aria-label="Hapus foto" wire:click="removeNewPhoto('{{ $slot['filename'] }}')" />
                                    @else
                                        <flux:button type="button" size="xs" variant="subtle" icon="trash" aria-label="Hapus foto" wire:click="removeExisting({{ (int) \Illuminate\Support\Str::after($slot['key'], 'id:') }})" />
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4">
                    <flux:input type="file" wire:model="photos" label="Tambah Gambar" multiple accept="image/jpeg,image/png,image/webp" />
                    <div wire:loading wire:target="photos" class="mt-1 text-xs text-primary">Mengunggah & memproses gambar…</div>
                    <flux:error name="photos" />
                    <flux:error name="photos.*" />
                </div>

                <x-product-lightbox />
            </div>
        </section>

        <div class="ml-auto grid w-full grid-cols-2 gap-2 sm:w-96">
            <flux:button :href="route('admin.products.index')" wire:navigate variant="outline" class="w-full">Batal</flux:button>
            <flux:button type="submit" variant="primary" :loading="false" class="w-full"><span wire:loading.remove wire:target="save">Simpan Produk</span><span wire:loading wire:target="save">Memproses...</span></flux:button>
        </div>
    </form>
</div>
