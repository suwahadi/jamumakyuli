<?php

use App\Models\Slide;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Slide Hero')] #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ?int $editingId = null;

    public string $title = '';

    public string $content = '';

    public string $button_label = '';

    public string $url = '';

    public bool $is_active = true;

    public ?int $sort_order = null;

    public $photo = null;

    public ?string $existingImage = null;

    public ?int $deletingId = null;

    #[Computed]
    public function slides()
    {
        return Slide::query()->orderBy('sort_order')->orderBy('id')->paginate(15);
    }

    #[Computed]
    public function activeCount(): int
    {
        return Slide::query()->where('is_active', true)->count();
    }

    public function create(): void
    {
        $this->reset(['editingId', 'title', 'content', 'button_label', 'url', 'is_active', 'sort_order', 'photo', 'existingImage']);
        $this->is_active = true;
        Flux::modal('slide-form')->show();
    }

    public function edit(int $id): void
    {
        $slide = Slide::query()->findOrFail($id);
        $this->editingId = $slide->id;
        $this->title = $slide->title;
        $this->content = (string) $slide->content;
        $this->button_label = (string) $slide->button_label;
        $this->url = (string) $slide->url;
        $this->is_active = $slide->is_active;
        $this->sort_order = $slide->sort_order;
        $this->existingImage = $slide->image;
        $this->photo = null;
        Flux::modal('slide-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'button_label' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'photo' => [$this->editingId ? 'nullable' : 'required', 'image', 'max:4096'],
        ], attributes: [
            'title' => 'judul',
            'content' => 'konten',
            'button_label' => 'label tombol',
            'url' => 'URL tautan',
            'sort_order' => 'urutan',
            'photo' => 'gambar',
        ]);

        if ($this->is_active) {
            $activeOthers = Slide::query()
                ->where('is_active', true)
                ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
                ->count();

            if ($activeOthers >= Slide::MAX_ACTIVE) {
                Flux::toast(variant: 'warning', text: 'Maksimal hanya '.Slide::MAX_ACTIVE.' slide aktif. Nonaktifkan salah satu dahulu.');

                return;
            }
        }

        $imagePath = $this->existingImage;

        if ($this->photo) {
            $imagePath = store_webp($this->photo, 'slides');

            if ($this->existingImage) {
                delete_webp($this->existingImage);
            }
        }

        $slide = $this->editingId
            ? Slide::query()->findOrFail($this->editingId)
            : new Slide;

        $sortOrder = $validated['sort_order']
            ?? (int) (Slide::query()->max('sort_order') ?? 0) + 1;

        $slide->fill([
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'] ?? null,
            'button_label' => $validated['button_label'] ?? null,
            'url' => $validated['url'] ?? null,
            'is_active' => $this->is_active,
            'sort_order' => $sortOrder,
            'image' => $imagePath,
        ])->save();

        Flux::modal('slide-form')->close();
        Flux::toast(variant: 'success', text: 'Slide disimpan.');
        $this->reset(['editingId', 'title', 'content', 'button_label', 'url', 'is_active', 'sort_order', 'photo', 'existingImage']);
    }

    public function toggle(int $id): void
    {
        $slide = Slide::query()->findOrFail($id);

        if (! $slide->is_active && $this->activeCount >= Slide::MAX_ACTIVE) {
            Flux::toast(variant: 'warning', text: 'Maksimal hanya '.Slide::MAX_ACTIVE.' slide aktif. Nonaktifkan salah satu dahulu.');

            return;
        }

        $slide->update(['is_active' => ! $slide->is_active]);
        unset($this->activeCount);
        Flux::toast(variant: 'success', text: $slide->is_active ? 'Slide diaktifkan.' : 'Slide dinonaktifkan.');
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        Flux::modal('slide-delete')->show();
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            try {
                Slide::query()->findOrFail($this->deletingId)->delete();
                Flux::toast(variant: 'success', text: 'Slide dihapus.');
            } catch (QueryException) {
                Flux::toast(variant: 'danger', text: 'Slide tidak dapat dihapus.');
            }
        }

        Flux::modal('slide-delete')->close();
        $this->reset('deletingId');
    }
}; ?>

<div>
    <div class="mb-1 flex flex-wrap items-end justify-between gap-4">
        <div class="flex items-center gap-3">
            <h1 class="font-display text-2xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:text-[28px]">Slide Hero</h1>
            <span class="inline-flex items-center gap-1.5 rounded-md bg-brand-green/10 px-2.5 py-1 text-xs font-semibold text-brand-green">{{ $this->activeCount }} / {{ \App\Models\Slide::MAX_ACTIVE }} aktif</span>
        </div>
        <flux:button wire:click="create" variant="primary" size="sm" class="cursor-pointer">Slide Baru</flux:button>
    </div>

    <p class="mb-6 text-sm text-gray-500 dark:text-zinc-400">Maksimal 6 slide aktif tampil pada hero beranda. Slide bergeser otomatis tiap 3 detik.</p>

    <div class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead>
                    <tr class="border-y border-gray-100 bg-gray-50/60 text-left font-display text-xs font-bold uppercase tracking-wide text-gray-400 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                        <th class="px-5 py-3.5 sm:px-6">Gambar</th>
                        <th class="px-3 py-3.5">Judul</th>
                        <th class="px-3 py-3.5 text-right">Urutan</th>
                        <th class="px-3 py-3.5">Tombol</th>
                        <th class="px-3 py-3.5">Status</th>
                        <th class="px-3 py-3.5 text-right sm:px-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                    @forelse ($this->slides as $slide)
                        <tr class="transition hover:bg-gray-50/70 dark:hover:bg-zinc-800/50" wire:key="slide-{{ $slide->id }}">
                            <td class="px-5 py-3.5 sm:px-6">
                                <img src="{{ $slide->image_url }}" alt="{{ $slide->title }}" class="h-12 w-20 rounded-md border border-gray-200 object-cover dark:border-zinc-700" />
                            </td>
                            <td class="px-3 py-3.5"><span class="font-semibold leading-tight text-gray-800 dark:text-zinc-100">{{ $slide->title ?: '-' }}</span></td>
                            <td class="px-3 py-3.5 text-right"><span class="font-mono text-gray-600 dark:text-zinc-300">{{ $slide->sort_order }}</span></td>
                            <td class="px-3 py-3.5 text-gray-600 dark:text-zinc-300">{{ $slide->button_label ?: '-' }}</td>
                            <td class="px-3 py-3.5">
                                <button type="button" wire:click="toggle({{ $slide->id }})" class="cursor-pointer">
                                    @if ($slide->is_active)
                                        <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Aktif</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500 dark:bg-zinc-800 dark:text-zinc-400"><span class="h-1.5 w-1.5 rounded-full bg-gray-400 dark:bg-zinc-600"></span>Nonaktif</span>
                                    @endif
                                </button>
                            </td>
                            <td class="px-3 py-3.5 sm:px-6">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button wire:click="edit({{ $slide->id }})" size="xs" variant="ghost" icon="pencil-square" class="cursor-pointer" />
                                    <flux:button wire:click="confirmDelete({{ $slide->id }})" size="xs" variant="ghost" icon="trash" class="cursor-pointer" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-zinc-400">Belum ada slide.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-5 dark:border-zinc-800 sm:p-6">
            <p class="text-xs text-gray-500 dark:text-zinc-400">Menampilkan {{ $this->slides->count() }} dari {{ $this->slides->total() }}</p>
            {{ $this->slides->links() }}
        </div>
    </div>

    <flux:modal name="slide-form" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Ubah Slide' : 'Slide Baru' }}</flux:heading>

            <flux:input wire:model="title" label="Judul (opsional)" placeholder="Mis. Promo Akhir Tahun" />

            @php $preview = $photo?->temporaryUrl() ?? ($existingImage ? Storage::disk('public')->url($existingImage) : null); @endphp
            <div>
                <flux:label>Gambar</flux:label>
                @if ($preview)
                    <div class="mt-2 overflow-hidden rounded-xl ring-1 ring-zinc-200 dark:ring-zinc-700">
                        <img src="{{ $preview }}" alt="Pratinjau" class="aspect-[21/9] w-full object-cover" />
                    </div>
                @endif
                <div class="mt-2">
                    <flux:input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" />
                </div>
                <p wire:loading wire:target="photo" class="mt-1 text-xs text-primary">Mengunggah gambar…</p>
                <flux:text size="sm" class="mt-1 text-zinc-400">Rasio lebar disarankan (mis. 21:9). Otomatis dikompres ke WebP.</flux:text>
            </div>

            <flux:textarea wire:model="content" label="Konten (opsional)" rows="3" placeholder="Teks pendukung pada slide." />

            @unless (config('storefront.hidden.slide_button'))
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="button_label" label="Label Tombol (opsional)" placeholder="Mis. Belanja Sekarang" />
                    <flux:input wire:model="url" label="URL Tautan (opsional)" placeholder="/products" />
                </div>
            @endunless

            <div class="grid grid-cols-2 items-center gap-4">
                <flux:input wire:model="sort_order" type="number" label="Urutan (opsional)" min="0" placeholder="Auto" />
                <flux:checkbox wire:model="is_active" label="Aktif" class="mt-6" />
            </div>

            <div class="grid grid-cols-2 gap-2">
                <flux:modal.close><flux:button variant="outline" class="w-full">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" :loading="false" class="w-full"><span wire:loading.remove wire:target="save">Simpan</span><span wire:loading wire:target="save">Memproses...</span></flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="slide-delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Slide</flux:heading>
                <flux:subheading>Yakin untuk menghapus data ini? Tindakan ini tidak dapat dibatalkan.</flux:subheading>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <flux:modal.close><flux:button variant="outline" class="w-full">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger" class="w-full">Ya, hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
