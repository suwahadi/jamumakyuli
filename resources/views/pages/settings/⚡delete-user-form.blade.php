<?php

use Livewire\Component;

new class extends Component {}; ?>

<x-settings.card title="Hapus Akun" description="Tutup akun ini secara permanen" danger>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="max-w-md text-sm text-zinc-600 dark:text-zinc-300">Akun yang sudah dihapus tidak bisa dipulihkan. Daftar favorit ikut terhapus, dan riwayat pesanan tidak lagi bisa dibuka dari akun ini.</p>

        <flux:modal.trigger name="confirm-user-deletion">
            <flux:button variant="danger" class="w-full shrink-0 sm:w-auto" data-test="delete-user-button">
                Hapus akun
            </flux:button>
        </flux:modal.trigger>
    </div>

    <livewire:pages::settings.delete-user-modal />
</x-settings.card>
