<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form method="POST" wire:submit="deleteUser" class="space-y-6">
        <div>
            <flux:heading size="lg">Yakin ingin menghapus akun Anda?</flux:heading>

            <flux:subheading>
                Setelah akun dihapus, seluruh data dan isinya hilang permanen. Masukkan kata sandi Anda untuk memastikan Anda benar-benar ingin menghapus akun ini.
            </flux:subheading>
        </div>

        <flux:input wire:model="password" label="Kata Sandi" type="password" viewable />

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="outline">Batal</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit" data-test="confirm-delete-user-button" :loading="false">
                <span wire:loading.remove wire:target="deleteUser">Hapus akun</span><span wire:loading wire:target="deleteUser">Memproses...</span>
            </flux:button>
        </div>
    </form>
</flux:modal>
