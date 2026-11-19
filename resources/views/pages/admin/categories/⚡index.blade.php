<?php

use App\Models\Category;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Kategori')] #[Layout('layouts::admin')] class extends Component {
    use WithFileUploads;
    use WithPagination;

    public string $cari = '';

    public string $filterStatus = '';

    public ?int $editingId = null;
    public string $name = '';
    public string $slug = '';
    public bool $is_active = true;

    public $thumbnail = null;
    public ?string $existingThumbnail = null;
    public ?string $existingThumbnailUrl = null;
    public bool $removeThumbnail = false;

    public bool $slugManuallyEdited = false;

    public ?int $deletingId = null;

    public function updatedCari(): void
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
        return Category::query()
            ->withCount('products')
            ->when($this->cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->cari}%")
                ->orWhere('slug', 'like', "%{$this->cari}%")))
            ->when($this->filterStatus !== '', fn ($q) => $q->where('is_active', $this->filterStatus === 'aktif'))
            ->orderBy('id')
            ->paginate(15);
    }

    #[Computed]
    public function totalKategori(): int
    {
        return Category::query()->count();
    }

    #[Computed]
    public function kategoriAktif(): int
    {
        return Category::query()->where('is_active', true)->count();
    }

    #[Computed]
    public function kategoriTanpaProduk(): int
    {
        return Category::query()->doesntHave('products')->count();
    }

    public function updatedName(string $value): void
    {
        if ($this->editingId === null && ! $this->slugManuallyEdited) {
            $this->slug = Str::slug($value);
        }
    }

    public function updatedSlug(): void
    {
        $this->slugManuallyEdited = true;
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'slug', 'is_active', 'thumbnail', 'existingThumbnail', 'existingThumbnailUrl', 'removeThumbnail', 'slugManuallyEdited']);
        $this->is_active = true;
        Flux::modal('category-form')->show();
    }

    public function edit(int $id): void
    {
        $category = Category::query()->findOrFail($id);
        $this->reset(['thumbnail', 'removeThumbnail']);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->is_active = $category->is_active;
        $this->existingThumbnail = $category->thumbnail;
        $this->existingThumbnailUrl = $category->thumbnailUrl();
        $this->slugManuallyEdited = true;
        Flux::modal('category-form')->show();
    }

    public function clearThumbnail(): void
    {
        $this->reset('thumbnail');
        $this->removeThumbnail = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($this->editingId)],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($this->editingId)],
            'is_active' => ['boolean'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], attributes: [
            'name' => 'nama kategori',
            'slug' => 'slug',
            'thumbnail' => 'gambar',
        ]);

        $category = Category::query()->find($this->editingId);

        $data = [
            'name' => $validated['name'],
            'slug' => Str::slug($validated['slug']),
            'is_active' => $validated['is_active'],
        ];

        if ($this->thumbnail !== null) {
            delete_webp($category?->thumbnail);
            $data['thumbnail'] = store_webp($this->thumbnail, 'categories');
        } elseif ($this->removeThumbnail) {
            delete_webp($category?->thumbnail);
            $data['thumbnail'] = null;
        }

        Category::query()->updateOrCreate(['id' => $this->editingId], $data);

        Flux::modal('category-form')->close();
        Flux::toast(variant: 'success', text: 'Kategori disimpan.');
        $this->reset(['editingId', 'name', 'slug', 'is_active', 'thumbnail', 'existingThumbnail', 'existingThumbnailUrl', 'removeThumbnail']);
    }

    public function confirmDelete(int $id): void
    {
        $category = Category::query()->withCount('products')->findOrFail($id);

        if ($category->products_count > 0) {
            Flux::toast(variant: 'warning', text: 'Kategori masih memiliki produk. Pindahkan atau hapus produknya dahulu.');

            return;
        }

        $this->deletingId = $id;
        Flux::modal('category-delete')->show();
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            try {
                Category::query()->whereKey($this->deletingId)->delete();
                Flux::toast(variant: 'success', text: 'Kategori dihapus.');
            } catch (QueryException) {
                Flux::toast(variant: 'danger', text: 'Kategori tidak dapat dihapus.');
            }
        }

        Flux::modal('category-delete')->close();
        $this->reset('deletingId');
    }
}; ?>

<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <h1 class="font-display text-2xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:text-[28px]">Kategori</h1>
        <flux:button wire:click="create" variant="primary" size="sm" class="cursor-pointer">Kategori Baru</flux:button>
    </div>

    <section class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3">
        <x-admin.summary-card label="Total Kategori" :value="$this->totalKategori" icon="folder-tree" tone="bg-brand-green/10 text-brand-green" />
        <x-admin.summary-card label="Kategori Aktif" :value="$this->kategoriAktif" icon="check-circle-2" tone="bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400" />
        <x-admin.summary-card class="col-span-2 sm:col-span-1" label="Kategori Tanpa Produk" :value="$this->kategoriTanpaProduk" icon="x-circle" tone="bg-brand-turmeric/15 text-brand-turmeric-ink" />
    </section>

    <div class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3 p-5 sm:p-6">
            <label class="min-w-0 flex-1">
                <span class="sr-only">Cari</span>
                <input type="search" wire:model.live.debounce.400ms="cari" placeholder="Cari nama kategori atau slug..." class="field">
            </label>
            <select wire:model.live="filterStatus" aria-label="Saring kategori menurut status" class="field w-full sm:w-auto">
                <option value="">Semua Status</option>
                <option value="aktif">Aktif</option>
                <option value="nonaktif">Nonaktif</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead>
                    <tr class="border-y border-gray-100 bg-gray-50/60 text-left font-display text-xs font-bold uppercase tracking-wide text-gray-400 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                        <th class="px-5 py-3.5 sm:px-6">Kategori</th>
                        <th class="px-3 py-3.5">Slug</th>
                        <th class="px-3 py-3.5 text-center">Jumlah Produk</th>
                        <th class="px-3 py-3.5">Status</th>
                        <th class="px-3 py-3.5 text-right sm:px-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                    @forelse ($this->baris as $category)
                        <tr class="transition hover:bg-gray-50/70 dark:hover:bg-zinc-800/50" wire:key="cat-{{ $category->id }}">
                            <td class="px-5 py-3.5 sm:px-6">
                                <div class="flex items-center gap-3">
                                    @if ($category->thumbnailUrl())
                                        <img src="{{ $category->thumbnailUrl() }}" alt="{{ $category->name }}" class="h-10 w-10 shrink-0 rounded-lg border border-gray-200 object-cover dark:border-zinc-700">
                                    @else
                                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-green/10 text-brand-green">
                                            <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-folder-tree"/></svg>
                                        </span>
                                    @endif
                                    <span class="font-semibold leading-tight text-gray-800 dark:text-zinc-100">{{ $category->name }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-3.5"><span class="font-mono text-[13px] text-gray-500 dark:text-zinc-400">{{ $category->slug }}</span></td>
                            <td class="px-3 py-3.5 text-center"><span class="font-display font-bold text-gray-700 dark:text-zinc-300">{{ $category->products_count }}</span></td>
                            <td class="px-3 py-3.5">
                                @if ($category->is_active)
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Aktif</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500 dark:bg-zinc-800 dark:text-zinc-400"><span class="h-1.5 w-1.5 rounded-full bg-gray-400 dark:bg-zinc-600"></span>Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5 sm:px-6">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button wire:click="edit({{ $category->id }})" size="xs" variant="ghost" icon="pencil-square" aria-label="Ubah kategori" class="cursor-pointer" />
                                    <flux:button wire:click="confirmDelete({{ $category->id }})" size="xs" variant="ghost" icon="trash" aria-label="Hapus kategori" class="cursor-pointer" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-zinc-400">Tidak ada data yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-5 dark:border-zinc-800 sm:p-6">
            <p class="text-xs text-gray-500 dark:text-zinc-400">Menampilkan {{ $this->baris->count() }} dari {{ $this->baris->total() }}</p>
            {{ $this->baris->links() }}
        </div>
    </div>

    <flux:modal name="category-form" class="md:w-96">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Ubah Kategori' : 'Kategori Baru' }}</flux:heading>

            <flux:input wire:model.live.debounce.400ms="name" label="Nama Kategori" />

            <flux:input wire:model.live="slug" label="Slug" />

            <div>
                <flux:label>Gambar Kategori</flux:label>

                @php $previewUrl = $thumbnail ? $thumbnail->temporaryUrl() : (! $removeThumbnail ? $existingThumbnailUrl : null); @endphp

                @if ($previewUrl)
                    <div class="mt-2 flex items-center gap-3">
                        <img src="{{ $previewUrl }}" alt="" class="size-16 shrink-0 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700" />
                        <flux:button type="button" size="xs" variant="subtle" wire:click="clearThumbnail" class="cursor-pointer">Hapus gambar</flux:button>
                    </div>
                @endif

                <div class="mt-2">
                    <flux:input type="file" wire:model="thumbnail" accept="image/jpeg,image/png,image/webp" />
                    <div wire:loading wire:target="thumbnail" class="mt-1 text-xs text-primary">Mengunggah & memproses gambar…</div>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">JPG/PNG/WebP ≤5MB.</p>
                    <flux:error name="thumbnail" />
                </div>
            </div>

            <flux:checkbox wire:model="is_active" label="Aktif" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="outline">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="category-delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Kategori</flux:heading>
                <flux:subheading>Yakin untuk menghapus data ini? Tindakan ini tidak dapat dibatalkan.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="outline">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger">Ya, hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
