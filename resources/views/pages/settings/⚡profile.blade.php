<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengaturan Profil')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    public string $phone = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->phone = (string) Auth::user()->phone;
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id), [], [
            'name' => 'nama',
            'email' => 'email',
            'phone' => 'nomor telepon',
        ]);
        $validated['phone'] = filled($validated['phone'] ?? null) ? trim($validated['phone']) : null;

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: 'Profil diperbarui.');
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<div>
    <x-pages::settings.layout>
        <x-settings.card title="Informasi Profil" description="Perbarui nama, email, dan nomor telepon Anda">
            <form wire:submit="updateProfileInformation" class="space-y-5">
                <div class="grid gap-5 md:grid-cols-3">
                    <flux:input wire:model="name" label="Nama" type="text" required autofocus autocomplete="name" />
                    <flux:input wire:model="email" label="Email" type="email" required autocomplete="email" />
                    <flux:input wire:model="phone" label="Telepon" type="tel" inputmode="tel" autocomplete="tel" maxlength="20" placeholder="08xxxxxxxxxx" />
                </div>

                @if ($this->hasUnverifiedEmail)
                    <div class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                        Alamat email Anda belum terverifikasi.
                        <button type="button" wire:click.prevent="resendVerificationNotification" class="cursor-pointer font-semibold underline underline-offset-2">Kirim ulang email verifikasi</button>

                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 font-medium text-brand-green dark:text-green-400">Tautan verifikasi baru telah dikirim ke alamat email Anda.</p>
                        @endif
                    </div>
                @endif

                <div class="flex justify-end border-t border-zinc-100 pt-5 dark:border-zinc-800">
                    <flux:button variant="primary" type="submit" class="w-full sm:w-auto" data-test="update-profile-button" :loading="false">
                        <span wire:loading.remove wire:target="updateProfileInformation">Simpan</span><span wire:loading wire:target="updateProfileInformation">Memproses...</span>
                    </flux:button>
                </div>
            </form>
        </x-settings.card>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</div>
