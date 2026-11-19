<?php

use App\Models\Page;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Halaman')] #[Layout('layouts::admin')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $cari = '';

    public ?int $editingId = null;
    public string $pageTitle = '';
    public string $slug = '';
    public string $content = '';
    public bool $is_active = true;

    public ?int $deletingId = null;

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function pages()
    {
        return Page::query()
            ->when($this->cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$this->cari}%")
                ->orWhere('slug', 'like', "%{$this->cari}%")))
            ->orderBy('title')
            ->paginate(15);
    }

    public function create(): void
    {
        $this->reset(['editingId', 'pageTitle', 'slug', 'content', 'is_active']);
        $this->is_active = true;
        Flux::modal('page-form')->show();
    }

    public function edit(int $id): void
    {
        $page = Page::query()->findOrFail($id);
        $this->editingId = $page->id;
        $this->pageTitle = $page->title;
        $this->slug = $page->slug;
        $this->content = $page->content;
        $this->is_active = $page->is_active;
        Flux::modal('page-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'pageTitle' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_active' => ['boolean'],
        ], attributes: ['pageTitle' => 'judul', 'slug' => 'slug', 'content' => 'konten']);

        $custom = trim($this->slug) !== '';
        $slug = Str::slug($custom ? $this->slug : $validated['pageTitle']);
        $field = $custom ? 'slug' : 'pageTitle';

        if ($slug === '') {
            throw ValidationException::withMessages([
                $field => $custom
                    ? 'Slug tidak valid. Gunakan huruf atau angka.'
                    : 'Judul tidak menghasilkan slug yang valid. Gunakan judul lain.',
            ]);
        }

        if (Page::isReservedSlug($slug)) {
            throw ValidationException::withMessages([
                $field => 'Slug "'.$slug.'" dipakai sistem. Gunakan slug lain.',
            ]);
        }

        $slugExists = Page::query()
            ->where('slug', $slug)
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->exists();

        if ($slugExists) {
            throw ValidationException::withMessages([
                $field => 'Slug "'.$slug.'" sudah dipakai halaman lain. Gunakan slug yang berbeda.',
            ]);
        }

        $page = $this->editingId
            ? Page::query()->findOrFail($this->editingId)
            : new Page();

        $page->fill([
            'title' => $validated['pageTitle'],
            'slug' => $slug,
            'content' => $validated['content'],
            'is_active' => $validated['is_active'],
        ])->save();

        Flux::modal('page-form')->close();
        Flux::toast(variant: 'success', text: 'Halaman disimpan.');
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        Flux::modal('page-delete')->show();
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            Page::query()->whereKey($this->deletingId)->delete();
            Flux::toast(variant: 'success', text: 'Halaman dihapus.');
        }

        Flux::modal('page-delete')->close();
        $this->reset('deletingId');
    }
}; ?>

<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <h1 class="font-display text-2xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:text-[28px]">Halaman Statis</h1>
        <flux:button wire:click="create" variant="primary" size="sm" class="cursor-pointer">Halaman Baru</flux:button>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3 p-5 sm:p-6">
            <label class="min-w-0 flex-1">
                <span class="sr-only">Cari</span>
                <input type="search" wire:model.live.debounce.400ms="cari" placeholder="Cari judul atau slug..." class="field">
            </label>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr class="border-y border-gray-100 bg-gray-50/60 text-left font-display text-xs font-bold uppercase tracking-wide text-gray-400 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                        <th class="px-5 py-3.5 sm:px-6">Judul</th>
                        <th class="px-3 py-3.5">Slug</th>
                        <th class="px-3 py-3.5">Status</th>
                        <th class="px-3 py-3.5 text-right sm:px-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                    @forelse ($this->pages as $page)
                        <tr class="transition hover:bg-gray-50/70 dark:hover:bg-zinc-800/50" wire:key="pg-{{ $page->id }}">
                            <td class="px-5 py-3.5 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-green/10 text-brand-green">
                                        <svg class="h-4 w-4 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-file-text"/></svg>
                                    </span>
                                    <span class="font-semibold leading-tight text-gray-800 dark:text-zinc-100">{{ $page->title }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-3.5"><span class="font-mono text-[13px] text-gray-500 dark:text-zinc-400">/{{ $page->slug }}</span></td>
                            <td class="px-3 py-3.5">
                                @if ($page->is_active)
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Aktif</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500 dark:bg-zinc-800 dark:text-zinc-400"><span class="h-1.5 w-1.5 rounded-full bg-gray-400 dark:bg-zinc-600"></span>Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5 sm:px-6">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($page->is_active)
                                        <flux:button :href="route('pages.show', $page)" target="_blank" size="xs" variant="ghost" icon="eye" aria-label="Lihat halaman di toko (tab baru)" class="cursor-pointer" />
                                    @endif
                                    <flux:button wire:click="edit({{ $page->id }})" size="xs" variant="ghost" icon="pencil-square" aria-label="Ubah halaman" class="cursor-pointer" />
                                    <flux:button wire:click="confirmDelete({{ $page->id }})" size="xs" variant="ghost" icon="trash" aria-label="Hapus halaman" class="cursor-pointer" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-zinc-400">Tidak ada data yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-5 dark:border-zinc-800 sm:p-6">
            <p class="text-xs text-gray-500 dark:text-zinc-400">Menampilkan {{ $this->pages->count() }} dari {{ $this->pages->total() }}</p>
            {{ $this->pages->links() }}
        </div>
    </div>

    <flux:modal name="page-form" class="w-full max-w-[calc(100vw-2rem)] md:w-[60rem] lg:w-[76rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Ubah Halaman' : 'Halaman Baru' }}</flux:heading>
            <div class="grid gap-5 md:grid-cols-2">
                <flux:input wire:model="pageTitle" label="Judul" />
                <flux:input wire:model="slug" label="Slug" :placeholder="Str::slug($pageTitle) ?: 'otomatis-dari-judul'" />
            </div>
            <x-wysiwyg model="content" label="Konten" placeholder="Tulis konten halaman. Gunakan toolbar untuk heading, daftar, tautan, gambar…" />
            <flux:checkbox wire:model="is_active" label="Aktif (tampil di footer toko)" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="outline" class="cursor-pointer">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" class="cursor-pointer" :loading="false"><span wire:loading.remove wire:target="save">Simpan</span><span wire:loading wire:target="save">Memproses...</span></flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="page-delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Halaman</flux:heading>
                <flux:subheading>Yakin untuk menghapus data ini? Tindakan ini tidak dapat dibatalkan.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="outline">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger">Ya, hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
