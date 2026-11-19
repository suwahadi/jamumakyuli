@if ($order->status === \App\Enums\OrderStatus::PENDING)
    <flux:modal.trigger name="confirm-paid">
        <flux:button variant="primary" class="w-full cursor-pointer">Tandai Lunas</flux:button>
    </flux:modal.trigger>
    <flux:modal.trigger name="confirm-cancel">
        <flux:button variant="ghost" class="w-full cursor-pointer border border-rose-600/30 !text-rose-700 hover:bg-rose-50 dark:border-rose-500/30 dark:!text-rose-400 dark:hover:bg-rose-500/10">Batalkan Pesanan</flux:button>
    </flux:modal.trigger>
@elseif ($order->status === \App\Enums\OrderStatus::PAID)
    <flux:modal.trigger name="confirm-shipped">
        <flux:button variant="primary" class="w-full cursor-pointer">Tandai Dikirim</flux:button>
    </flux:modal.trigger>
    <flux:modal.trigger name="confirm-cancel">
        <flux:button variant="ghost" class="w-full cursor-pointer border border-rose-600/30 !text-rose-700 hover:bg-rose-50 dark:border-rose-500/30 dark:!text-rose-400 dark:hover:bg-rose-500/10">Batalkan Pesanan</flux:button>
    </flux:modal.trigger>
@elseif ($order->status === \App\Enums\OrderStatus::SHIPPED)
    <flux:modal.trigger name="confirm-delivered">
        <flux:button variant="primary" icon="check-badge" class="w-full cursor-pointer">Tandai Diterima</flux:button>
    </flux:modal.trigger>
    <x-admin.state-note icon="truck" tone="sky" title="Pesanan telah dikirim">
        Pelanggan sudah dikirimi email berisi nomor resi.
    </x-admin.state-note>
@elseif ($order->status === \App\Enums\OrderStatus::DELIVERED)
    <x-admin.state-note icon="check-badge" tone="teal" title="Pesanan sudah diterima">
        Paket sampai di tangan pelanggan. Tidak ada tindakan lanjutan.
    </x-admin.state-note>
@else
    <x-admin.state-note icon="x-circle" tone="rose" title="Pesanan dibatalkan">
        Stok sudah dikembalikan.
    </x-admin.state-note>
@endif
