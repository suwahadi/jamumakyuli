<?php

use App\Models\AboutImage;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Sekilas Tentang')] #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    public $photo = null;

    public ?int $deletingId = null;

    #[Computed]
    public function items()
    {
        return AboutImage::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function create(): void
    {
        $this->reset('photo');
        $this->resetValidation();
        Flux::modal('about-image-form')->show();
    }

    public function save(): void
    {
        $this->validate(
            ['photo' => ['required', 'image', 'max:4096']],
            attributes: ['photo' => 'gambar'],
        );

        if ($this->items->count() >= AboutImage::MAX_ITEMS) {
            Flux::toast(variant: 'warning', text: 'Maksimal '.AboutImage::MAX_ITEMS.' gambar. Hapus salah satu dahulu.');

            return;
        }

        AboutImage::query()->create([
            'image' => store_webp($this->photo, 'about'),
            'sort_order' => (int) (AboutImage::query()->max('sort_order') ?? 0) + 1,
        ]);

        unset($this->items);
        Flux::modal('about-image-form')->close();
        Flux::toast(variant: 'success', text: 'Gambar ditambahkan.');
        $this->reset('photo');
    }

    public function naik(int $id): void
    {
        $this->geser($id, -1);
    }

    public function turun(int $id): void
    {
        $this->geser($id, 1);
    }

    private function geser(int $id, int $arah): void
    {
        $urut = $this->items->values();
        $posisi = $urut->search(fn (AboutImage $item): bool => $item->id === $id);

        if ($posisi === false) {
            return;
        }

        $tujuan = $posisi + $arah;

        if ($tujuan < 0 || $tujuan >= $urut->count()) {
            return;
        }

        $tukar = $urut->all();
        [$tukar[$posisi], $tukar[$tujuan]] = [$tukar[$tujuan], $tukar[$posisi]];

        foreach ($tukar as $index => $item) {
            $item->update(['sort_order' => $index]);
        }

        unset($this->items);
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        Flux::modal('about-image-delete')->show();
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            try {
                AboutImage::query()->findOrFail($this->deletingId)->delete();
                Flux::toast(variant: 'success', text: 'Gambar dihapus.');
            } catch (QueryException) {
                Flux::toast(variant: 'danger', text: 'Gambar tidak dapat dihapus.');
            }
        }

        unset($this->items);
        Flux::modal('about-image-delete')->close();
        $this->reset('deletingId');
    }
}; ?>

<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="flex items-center gap-3">
            <h1 class="font-display text-2xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:text-[28px]">Sekilas Tentang</h1>
            <span class="inline-flex items-center gap-1.5 rounded-md bg-brand-green/10 px-2.5 py-1 text-xs font-semibold text-brand-green">{{ $this->items->count() }} / {{ \App\Models\AboutImage::MAX_ITEMS }} gambar</span>
        </div>
        <flux:button wire:click="create" variant="primary" size="sm" class="cursor-pointer"
                     :disabled="$this->items->count() >= \App\Models\AboutImage::MAX_ITEMS">Tambah Gambar</flux:button>
    </div>

    @if ($this->items->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <p class="font-display text-sm font-bold text-gray-500 dark:text-zinc-400">Belum ada gambar</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">Selama kosong, data tidak akan ditampilkan di beranda.</p>
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
            @foreach ($this->items as $index => $item)
                <div wire:key="about-{{ $item->id }}"
                     class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="relative aspect-square bg-gray-100 dark:bg-zinc-800">
                        <img src="{{ $item->image_url }}" alt="" class="h-full w-full object-cover">
                        <span class="absolute left-2 top-2 rounded-md bg-black/70 px-2 py-1 font-display text-[10px] font-bold uppercase tracking-wide text-white">
                            {{ $index === 0 ? 'Panel besar' : 'Panel '.($index + 1) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-1 px-2 py-2">
                        <div class="flex items-center gap-1">
                            <flux:button wire:click="naik({{ $item->id }})" size="xs" variant="ghost" class="cursor-pointer"
                                         :disabled="$index === 0" aria-label="Naikkan urutan">
                                <flux:icon.chevron-up class="size-4" />
                            </flux:button>
                            <flux:button wire:click="turun({{ $item->id }})" size="xs" variant="ghost" class="cursor-pointer"
                                         :disabled="$index === $this->items->count() - 1" aria-label="Turunkan urutan">
                                <flux:icon.chevron-down class="size-4" />
                            </flux:button>
                        </div>

                        <flux:button wire:click="confirmDelete({{ $item->id }})" size="xs" variant="ghost"
                                     class="cursor-pointer text-rose-700 dark:text-rose-400" aria-label="Hapus gambar">
                            <flux:icon.trash class="size-4" />
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal name="about-image-form" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">Tambah Gambar</flux:heading>

            <div>
                <flux:input type="file" wire:model="photo" label="Gambar" accept="image/*" />
                <p wire:loading wire:target="photo" class="mt-1 text-xs text-primary">Mengunggah gambar…</p>
                <flux:text size="sm" class="mt-1 text-zinc-400">Disarankan berbentuk persegi. Otomatis dikompres ke WebP.</flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="outline" class="cursor-pointer">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" class="cursor-pointer" :loading="false"><span wire:loading.remove wire:target="save">Simpan</span><span wire:loading wire:target="save">Memproses...</span></flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="about-image-delete" class="w-full max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">Hapus gambar ini?</flux:heading>
            <flux:text>Gambar akan dihapus dari beranda dan berkasnya dibuang dari penyimpanan.</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="outline" class="cursor-pointer">Batal</flux:button>
                </flux:modal.close>
                <flux:button wire:click="delete" variant="danger" class="cursor-pointer">Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
