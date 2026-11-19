<?php

use App\Concerns\PasswordValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Features;
use Livewire\Attributes\Title;
use Livewire\Component;
use Laravel\Passkeys\Actions\DeletePasskey;
use Livewire\Attributes\Locked;
new #[Title('Pengaturan Keamanan')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    #[Locked]
    public bool $canManagePasskeys;

    #[Locked]
    public array $passkeys = [];

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingPasskeyId = null;

    #[Locked]
    public string $deletingPasskeyName = '';

    public function mount(): void
    {
        $this->canManagePasskeys = Features::canManagePasskeys();

        if ($this->canManagePasskeys) {
            $this->loadPasskeys();
        }
    }

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: 'Kata sandi diperbarui.');
    }

    #[On('passkey-registered')]
    public function onPasskeyRegistered(): void
    {
        $this->loadPasskeys();

        Flux::toast(variant: 'success', text: 'Passkey ditambahkan.');
    }

    public function loadPasskeys(): void
    {
        $this->passkeys = auth()->user()->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get()
            ->map(fn ($passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'created_at_diff' => $passkey->created_at->diffForHumans(),
                'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
            ])
            ->toArray();
    }

    public function confirmDelete(int $passkeyId): void
    {
        $passkey = auth()->user()->passkeys()->findOrFail($passkeyId);

        $this->deletingPasskeyId = $passkey->id;
        $this->deletingPasskeyName = $passkey->name;
        $this->showDeleteModal = true;
    }

    public function deletePasskey(DeletePasskey $deletePasskey): void
    {
        if (! $this->deletingPasskeyId) {
            return;
        }

        $passkey = auth()->user()->passkeys()->findOrFail($this->deletingPasskeyId);

        $deletePasskey(auth()->user(), $passkey);

        $this->closeDeleteModal();
        $this->loadPasskeys();
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingPasskeyId = null;
        $this->deletingPasskeyName = '';
    }
}; ?>

<div>
    <x-pages::settings.layout>
        <x-settings.card title="Kata Sandi" description="Perbarui kata sandi secara berkala. Pakai yang panjang dan acak agar akun tetap aman.">
            <form method="POST" wire:submit="updatePassword" class="space-y-5">
                <flux:input
                    wire:model="current_password"
                    label="Kata sandi saat ini"
                    type="password"
                    required
                    autocomplete="current-password"
                    viewable
                />
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input
                        wire:model="password"
                        label="Kata sandi baru"
                        type="password"
                        required
                        autocomplete="new-password"
                        passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                        viewable
                    />
                    <flux:input
                        wire:model="password_confirmation"
                        label="Konfirmasi kata sandi"
                        type="password"
                        required
                        autocomplete="new-password"
                        passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                        viewable
                    />
                </div>

                <div class="flex justify-end border-t border-zinc-100 pt-5 dark:border-zinc-800">
                    <flux:button variant="primary" type="submit" class="w-full sm:w-auto" data-test="update-password-button" :loading="false">
                        <span wire:loading.remove wire:target="updatePassword">Simpan</span><span wire:loading wire:target="updatePassword">Memproses...</span>
                    </flux:button>
                </div>
            </form>
        </x-settings.card>

        @if ($canManagePasskeys)
            <x-settings.card title="Passkey" description="Kelola passkey Anda untuk masuk tanpa kata sandi">
                <div class="flex w-full flex-col space-y-6 text-sm" wire:cloak>
                    <div class="border rounded-lg border-zinc-200 dark:border-zinc-700 overflow-hidden">
                        @forelse ($passkeys as $passkey)
                            <div class="flex items-center justify-between p-4 {{ ! $loop->last ? 'border-b border-zinc-200 dark:border-zinc-700' : '' }}">
                                <div class="flex items-center gap-4">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                                        <flux:icon.key class="size-5 text-zinc-500 dark:text-zinc-400" />
                                    </div>
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2.5">
                                            <p class="font-medium tracking-tight">{{ $passkey['name'] }}</p>
                                            @if ($passkey['authenticator'])
                                                <flux:badge size="sm">{{ $passkey['authenticator'] }}</flux:badge>
                                            @endif
                                        </div>
                                        <p class="text-zinc-500 dark:text-zinc-400 text-xs">
                                            Ditambahkan {{ $passkey['created_at_diff'] }}
                                            @if ($passkey['last_used_at_diff'])
                                                <span class="opacity-50 mx-1">/</span>
                                                Terakhir dipakai {{ $passkey['last_used_at_diff'] }}
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    icon:variant="outline"
                                    wire:click="confirmDelete({{ $passkey['id'] }})"
                                    class="text-rose-700 hover:bg-rose-50 hover:text-rose-800 dark:text-rose-400 dark:hover:bg-rose-500/10"
                                    aria-label="Hapus passkey {{ $passkey['name'] }}"
                                />
                            </div>
                        @empty
                            <div class="p-8 text-center">
                                <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800">
                                    <flux:icon.key class="size-7 text-zinc-500 dark:text-zinc-400" />
                                </div>
                                <p class="font-medium">Belum ada passkey</p>
                                <flux:text class="mt-1">Tambahkan passkey untuk masuk tanpa kata sandi</flux:text>
                            </div>
                        @endforelse
                    </div>

                    <x-passkey-registration />
                </div>
            </x-settings.card>
        @endif
    </x-pages::settings.layout>

    <flux:modal
        name="delete-passkey-modal"
        class="max-w-md md:min-w-md"
        @close="closeDeleteModal"
        wire:model="showDeleteModal"
    >
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">Hapus passkey</flux:heading>
                <flux:text>
                    Yakin ingin menghapus passkey "{{ $deletingPasskeyName }}"? Passkey itu tidak bisa lagi dipakai untuk masuk.
                </flux:text>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <flux:button
                    variant="outline"
                    class="w-full"
                    wire:click="closeDeleteModal"
                >
                    Batal
                </flux:button>
                <flux:button
                    variant="danger"
                    class="w-full"
                    wire:click="deletePasskey"
                >
                    Hapus passkey
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
