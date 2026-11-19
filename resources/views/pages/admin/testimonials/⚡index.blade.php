<?php

use App\Models\Testimonial;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Testimoni')] #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ?int $editingId = null;
    public string $name = '';
    public ?string $role = null;
    public string|int $rating = 5;
    public string $content = '';
    public $photo = null;

    public ?int $deletingId = null;

    #[Computed]
    public function testimonials()
    {
        return Testimonial::query()->orderBy('id')->paginate(15);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'role' => ['nullable', 'string', 'max:80'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['required', 'string', 'max:400'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate(attributes: [
            'name' => 'nama',
            'role' => 'peran',
            'rating' => 'rating',
            'content' => 'kutipan',
            'photo' => 'gambar',
        ]);

        if ($this->editingId === null && Testimonial::query()->count() >= Testimonial::MAX_ITEMS) {
            Flux::toast(variant: 'warning', text: 'Maksimal '.Testimonial::MAX_ITEMS.' testimoni.');

            return;
        }

        $data = [
            'name' => $validated['name'],
            'role' => $validated['role'] ?: null,
            'rating' => (int) $validated['rating'],
            'content' => $validated['content'],
        ];

        if ($this->photo !== null) {
            $testimonial = Testimonial::query()->find($this->editingId);
            delete_webp($testimonial?->image);
            $data['image'] = store_webp($this->photo, 'testimonials');
        }

        if ($this->editingId) {
            Testimonial::query()->whereKey($this->editingId)->update($data);
        } else {
            Testimonial::query()->create($data);
        }

        unset($this->testimonials);
        $this->resetForm();
        Flux::toast(variant: 'success', text: 'Testimoni disimpan.');
    }

    public function edit(int $id): void
    {
        $testimonial = Testimonial::query()->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $testimonial->id;
        $this->name = $testimonial->name;
        $this->role = (string) $testimonial->role;
        $this->rating = $testimonial->rating;
        $this->content = (string) $testimonial->content;
        $this->photo = null;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'role', 'rating', 'content', 'photo']);
        $this->rating = 5;
        $this->resetValidation();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        Flux::modal('testimonial-delete')->show();
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            Testimonial::query()->findOrFail($this->deletingId)->delete();
            unset($this->testimonials);
            Flux::toast(variant: 'success', text: 'Testimoni dihapus.');
        }

        Flux::modal('testimonial-delete')->close();
        $this->reset('deletingId');
    }
}; ?>

<div>
    <div class="mb-6 flex items-center gap-3">
        <h1 class="font-display text-2xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:text-[28px]">Testimoni</h1>
        <span class="inline-flex items-center gap-1.5 rounded-md bg-brand-green/10 px-2.5 py-1 text-xs font-semibold text-brand-green">{{ $this->testimonials->total() }} / {{ Testimonial::MAX_ITEMS }}</span>
    </div>

    <div class="mb-6 rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
            <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">{{ $editingId ? 'Ubah Testimoni' : 'Testimoni Baru' }}</h2>
        </div>
        <form wire:submit="save" class="space-y-5 p-5 sm:p-6">
            <div class="grid items-start gap-x-5 gap-y-4 sm:grid-cols-2">
                <flux:input wire:model="name" label="Nama" placeholder="Budi Santoso" />
                <flux:input wire:model="role" label="Peran" placeholder="Pelanggan Setia" />
                <flux:select wire:model="rating" label="Rating">
                    <flux:select.option value="1">1</flux:select.option>
                    <flux:select.option value="2">2</flux:select.option>
                    <flux:select.option value="3">3</flux:select.option>
                    <flux:select.option value="4">4</flux:select.option>
                    <flux:select.option value="5">5</flux:select.option>
                </flux:select>
                <flux:input type="file" wire:model="photo" label="Gambar bukti (opsional)" description:trailing="Tangkapan layar chat atau foto barang diterima. Tampil di kartu testimoni dan bisa diperbesar." accept="image/jpeg,image/png,image/webp" />
                <flux:textarea wire:model="content" label="Kutipan" placeholder="Partnya sesuai dan sampai cepat." class="sm:col-span-2" rows="3" />
            </div>

            <div class="flex justify-end gap-2">
                @if ($editingId)
                    <flux:button type="button" wire:click="resetForm" variant="ghost" class="cursor-pointer">Batal</flux:button>
                @endif
                <flux:button type="submit" variant="primary" class="cursor-pointer" :loading="false"><span wire:loading.remove wire:target="save">Simpan</span><span wire:loading wire:target="save">Memproses...</span></flux:button>
            </div>
        </form>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="divide-y divide-gray-100 dark:divide-zinc-800">
            @forelse ($this->testimonials as $testimonial)
                <div class="flex items-start gap-4 px-5 py-4 sm:px-6" wire:key="testimonial-{{ $testimonial->id }}">
                    @if ($testimonial->image_url)
                        <img src="{{ $testimonial->image_url }}" alt="{{ $testimonial->name }}" class="size-11 shrink-0 rounded-lg border border-gray-200 object-cover dark:border-zinc-700" />
                    @else
                        <div class="grid size-11 shrink-0 place-items-center rounded-lg border border-dashed border-gray-200 text-gray-300 dark:border-zinc-700 dark:text-zinc-600">
                            <flux:icon.photo class="size-5" />
                        </div>
                    @endif

                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-gray-700 dark:text-zinc-200">{{ $testimonial->content }}</p>
                        <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-zinc-100">
                            {{ $testimonial->name }}
                            @if ($testimonial->role)
                                <span class="font-normal text-gray-500 dark:text-zinc-400">, {{ $testimonial->role }}</span>
                            @endif
                        </p>
                        <p class="mt-0.5 text-xs font-bold text-brand-green">{{ str_repeat('★', $testimonial->rating).str_repeat('☆', 5 - $testimonial->rating) }}</p>
                    </div>

                    <div class="flex shrink-0 gap-1">
                        <flux:button wire:click="edit({{ $testimonial->id }})" size="xs" variant="ghost" icon="pencil-square" class="cursor-pointer" />
                        <flux:button wire:click="confirmDelete({{ $testimonial->id }})" size="xs" variant="ghost" icon="trash" class="cursor-pointer" />
                    </div>
                </div>
            @empty
                <div class="px-6 py-16 text-center text-gray-500 dark:text-zinc-400">Belum ada testimoni.</div>
            @endforelse
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-5 dark:border-zinc-800 sm:p-6">
            <p class="text-xs text-gray-500 dark:text-zinc-400">Menampilkan {{ $this->testimonials->count() }} dari {{ $this->testimonials->total() }}</p>
            {{ $this->testimonials->links() }}
        </div>
    </div>

    <flux:modal name="testimonial-delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Testimoni</flux:heading>
                <flux:subheading>Yakin untuk menghapus data ini? Tindakan ini tidak dapat dibatalkan.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="outline">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger">Ya, hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
