<?php

use App\Enums\UserRole;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pengguna')] #[Layout('layouts::admin')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $cari = '';

    #[Url(as: 'role', history: true)]
    public string $filterPeran = '';

    public ?int $editingId = null;
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $roleField = UserRole::CUSTOMER->value;
    public string $password = '';
    public string $password_confirmation = '';

    public ?int $deletingId = null;

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPeran(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function baris()
    {
        return User::query()
            ->when($this->cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->cari}%")
                ->orWhere('email', 'like', "%{$this->cari}%")
                ->orWhere('phone', 'like', "%{$this->cari}%")))
            ->when($this->filterPeran !== '', fn ($q) => $q->where('role', $this->filterPeran))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function totalPengguna(): int
    {
        return User::query()->count();
    }

    #[Computed]
    public function penggunaTerverifikasi(): int
    {
        return User::query()->whereNotNull('email_verified_at')->count();
    }

    #[Computed]
    public function totalAdmin(): int
    {
        return User::query()->where('role', UserRole::ADMIN)->count();
    }

    #[Computed]
    public function penggunaBelumVerifikasi(): int
    {
        return User::query()->whereNull('email_verified_at')->count();
    }

    public function roleOptions(): array
    {
        return UserRole::cases();
    }

    public function edit(int $id): void
    {
        $user = User::query()->findOrFail($id);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? '';
        $this->roleField = $user->role->value;
        $this->password = '';
        $this->password_confirmation = '';

        $this->resetValidation();
        Flux::modal('user-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'phone' => ['nullable', 'string', 'max:50'],
            'roleField' => ['required', Rule::enum(UserRole::class)],
            'password' => ['nullable', 'confirmed', Password::default()],
        ], attributes: [
            'name' => 'nama',
            'email' => 'email',
            'phone' => 'nomor telepon',
            'roleField' => 'role',
            'password' => 'password',
        ]);

        if ($this->editingId === auth()->id() && $validated['roleField'] !== UserRole::ADMIN->value) {
            Flux::toast(variant: 'warning', text: 'Anda tidak dapat menurunkan role akun Anda sendiri.');

            return;
        }

        $user = User::query()->findOrFail($this->editingId);
        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?: null,
            'role' => $validated['roleField'],
        ]);

        if ($validated['password'] ?? false) {
            $user->password = $validated['password'];
        }

        $user->save();

        Flux::modal('user-form')->close();
        Flux::toast(variant: 'success', text: 'Pengguna diperbarui.');
        $this->reset(['editingId', 'name', 'email', 'phone', 'roleField', 'password', 'password_confirmation']);
    }

    private function guardDeletable(int $id): ?User
    {
        if ($id === auth()->id()) {
            Flux::toast(variant: 'warning', text: 'Anda tidak dapat menghapus akun Anda sendiri.');

            return null;
        }

        $user = User::query()->findOrFail($id);

        if ($user->role === UserRole::ADMIN && User::query()->where('role', UserRole::ADMIN->value)->count() <= 1) {
            Flux::toast(variant: 'warning', text: 'Tidak dapat menghapus administrator terakhir.');

            return null;
        }

        return $user;
    }

    public function confirmDelete(int $id): void
    {
        if (! $this->guardDeletable($id)) {
            return;
        }

        $this->deletingId = $id;
        Flux::modal('user-delete')->show();
    }

    public function delete(): void
    {
        $user = $this->deletingId ? $this->guardDeletable($this->deletingId) : null;

        if ($user) {
            try {
                $user->delete();
                Flux::toast(variant: 'success', text: 'Pengguna dihapus.');
            } catch (QueryException) {
                Flux::toast(variant: 'danger', text: 'Pengguna tidak dapat dihapus karena masih terkait data lain.');
            }
        }

        Flux::modal('user-delete')->close();
        $this->reset('deletingId');
    }
}; ?>

<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <h1 class="font-display text-2xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:text-[28px]">Pengguna</h1>
    </div>

    <section class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-admin.summary-card label="Total Pengguna" :value="$this->totalPengguna" icon="users" tone="bg-brand-green/10 text-brand-green" />
        <x-admin.summary-card label="Terverifikasi" :value="$this->penggunaTerverifikasi" icon="user-check" tone="bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400" />
        <x-admin.summary-card label="Admin" :value="$this->totalAdmin" icon="badge-check" tone="bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400" />
        <x-admin.summary-card label="Belum Verifikasi" :value="$this->penggunaBelumVerifikasi" icon="user-x" tone="bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-zinc-400" />
    </section>

    <div class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3 p-5 sm:p-6">
            <label class="min-w-0 flex-1">
                <span class="sr-only">Cari</span>
                <input type="search" wire:model.live.debounce.400ms="cari" placeholder="Cari nama, email, atau telepon..." class="field">
            </label>
            <select wire:model.live="filterPeran" aria-label="Saring pengguna menurut peran" class="field w-full sm:w-auto">
                <option value="">Semua Peran</option>
                @foreach ($this->roleOptions() as $opt)
                    <option value="{{ $opt->value }}">{{ $opt->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[920px] text-sm">
                <thead>
                    <tr class="border-y border-gray-100 bg-gray-50/60 text-left font-display text-xs font-bold uppercase tracking-wide text-gray-400 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                        <th class="px-5 py-3.5 sm:px-6">Pengguna</th>
                        <th class="px-3 py-3.5">Peran</th>
                        <th class="px-3 py-3.5">Telepon</th>
                        <th class="px-3 py-3.5">Bergabung</th>
                        <th class="px-3 py-3.5">Status</th>
                        <th class="px-3 py-3.5 text-right sm:px-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                    @forelse ($this->baris as $user)
                        <tr class="transition hover:bg-gray-50/70 dark:hover:bg-zinc-800/50" wire:key="user-{{ $user->id }}">
                            <td class="px-5 py-3.5 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-brand-dark font-display text-xs font-bold text-white dark:bg-zinc-700">{{ $user->initials() }}</span>
                                    <div class="min-w-0">
                                        <p class="text-[13px] font-semibold leading-tight text-gray-800 dark:text-zinc-100">{{ $user->name }}</p>
                                        <p class="mt-0.5 text-[11px] leading-tight text-gray-500 dark:text-zinc-400">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3.5">
                                @if ($user->role === \App\Enums\UserRole::ADMIN)
                                    <span class="inline-flex rounded-md bg-brand-green/10 px-2.5 py-1 text-xs font-semibold text-brand-green">{{ $user->role->label() }}</span>
                                @else
                                    <span class="inline-flex rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-600 dark:bg-blue-500/15 dark:text-blue-400">{{ $user->role->label() }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5 font-mono text-xs text-gray-600 dark:text-zinc-400">{{ $user->phone ?: '-' }}</td>
                            <td class="whitespace-nowrap px-3 py-3.5 text-gray-600 dark:text-zinc-400">{{ tanggal_id($user->created_at) }}</td>
                            <td class="px-3 py-3.5">
                                @if ($user->email_verified_at)
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Terverifikasi</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500 dark:bg-zinc-800 dark:text-zinc-400"><span class="h-1.5 w-1.5 rounded-full bg-gray-400 dark:bg-zinc-600"></span>Belum verifikasi</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5 sm:px-6">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button wire:click="edit({{ $user->id }})" size="xs" variant="ghost" icon="pencil-square" class="cursor-pointer" />
                                    @unless ($user->id === auth()->id())
                                        <flux:button wire:click="confirmDelete({{ $user->id }})" size="xs" variant="ghost" icon="trash" class="cursor-pointer" />
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-zinc-400">Tidak ada data yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-5 dark:border-zinc-800 sm:p-6">
            <p class="text-xs text-gray-500 dark:text-zinc-400">Menampilkan {{ $this->baris->count() }} dari {{ $this->baris->total() }}</p>
            {{ $this->baris->links() }}
        </div>
    </div>

    <flux:modal name="user-form" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">Ubah Pengguna</flux:heading>

            <flux:input wire:model="name" label="Nama" />
            <flux:input wire:model="email" type="email" label="Email" />
            <flux:input wire:model="phone" label="Nomor Telepon" placeholder="Opsional" />

            <flux:select wire:model="roleField" label="Role" class="cursor-pointer">
                @foreach ($this->roleOptions() as $opt)
                    <flux:select.option :value="$opt->value">{{ $opt->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:separator variant="subtle" />

            <flux:input wire:model="password" type="password" label="Password Baru" viewable
                description="Kosongkan jika tidak ingin mengubah password." />
            <flux:input wire:model="password_confirmation" type="password" label="Konfirmasi Password" viewable />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="outline">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="user-delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Pengguna</flux:heading>
                <flux:subheading>Yakin untuk menghapus data ini? Tindakan ini tidak dapat dibatalkan.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="outline">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger">Ya, hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
