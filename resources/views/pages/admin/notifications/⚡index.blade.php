<?php

use App\Enums\NotificationType;
use App\Models\Order;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Notifikasi')] #[Layout('layouts::admin')] class extends Component {
    use WithPagination;

    #[Url(history: true)]
    public string $filter = 'all';

    public ?string $deletingId = null;

    public function updating($property): void
    {
        if ($property === 'filter') {
            $this->resetPage();
        }
    }

    #[Computed]
    public function pesananAda(): array
    {
        $nomor = collect($this->notifications->items())->pluck('data.order_number')->filter()->unique()->all();

        return Order::query()->whereIn('order_number', $nomor)->pluck('order_number')->flip()->all();
    }

    #[Computed]
    public function notifications()
    {
        return auth()->user()->notifications()
            ->when($this->filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->whereKey($id)->first();
        $notification?->markAsRead();

        $this->dispatch('notifications-updated');
        unset($this->notifications, $this->unreadCount);

        $orderNumber = $notification?->data['order_number'] ?? null;

        if ($orderNumber === null) {
            return;
        }

        if (! Order::query()->where('order_number', $orderNumber)->exists()) {
            Flux::toast(variant: 'warning', text: 'Pesanan ini sudah dihapus.');

            return;
        }

        $this->redirect(route('admin.orders.show', $orderNumber), navigate: true);
    }

    public function markRead(string $id): void
    {
        auth()->user()->notifications()->whereKey($id)->first()?->markAsRead();
        unset($this->notifications, $this->unreadCount);
        $this->dispatch('notifications-updated');
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
        unset($this->notifications, $this->unreadCount);
        $this->dispatch('notifications-updated');
        Flux::toast(variant: 'success', text: 'Semua notifikasi ditandai dibaca.');
    }

    public function confirmDelete(string $id): void
    {
        $this->deletingId = $id;
        Flux::modal('notification-delete')->show();
    }

    public function delete(): void
    {
        if ($this->deletingId !== null) {
            auth()->user()->notifications()->whereKey($this->deletingId)->delete();
            unset($this->notifications, $this->unreadCount);
            $this->dispatch('notifications-updated');
            Flux::toast(variant: 'success', text: 'Notifikasi dihapus.');
        }

        Flux::modal('notification-delete')->close();
        $this->reset('deletingId');
    }
}; ?>

<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <h1 class="font-display text-2xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:text-[28px]">Notifikasi</h1>
        @if ($this->unreadCount > 0)
            <flux:button wire:click="markAllRead" size="sm" variant="subtle" class="cursor-pointer">
                Tandai semua dibaca
            </flux:button>
        @endif
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
        <flux:button wire:click="$set('filter', 'all')" size="sm" :variant="$filter === 'all' ? 'primary' : 'ghost'" class="cursor-pointer">Semua</flux:button>
        <flux:button wire:click="$set('filter', 'unread')" size="sm" :variant="$filter === 'unread' ? 'primary' : 'ghost'" class="cursor-pointer">
            Belum dibaca
            @if ($this->unreadCount > 0)
                <span class="ml-1 rounded-full bg-brand-green px-1.5 text-[10px] font-bold text-white">{{ $this->unreadCount }}</span>
            @endif
        </flux:button>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead>
                    <tr class="border-y border-gray-100 bg-gray-50/60 text-left font-display text-xs font-bold uppercase tracking-wide text-gray-400 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                        <th class="px-5 py-3.5 sm:px-6">Notifikasi</th>
                        <th class="px-3 py-3.5">Pesanan</th>
                        <th class="px-3 py-3.5">Status</th>
                        <th class="px-3 py-3.5">Waktu</th>
                        <th class="px-3 py-3.5 text-right sm:px-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                    @forelse ($this->notifications as $notification)
                        @php($data = $notification->data)
                        @php($type = NotificationType::tryFrom($data['type'] ?? ''))
                        <tr class="transition hover:bg-gray-50/70 dark:hover:bg-zinc-800/50 {{ $notification->read_at ? '' : 'bg-brand-green/5 dark:bg-brand-green/10' }}" wire:key="notif-{{ $notification->id }}">
                            <td class="px-5 py-3.5 sm:px-6">
                                <div class="flex items-start gap-3">
                                    <flux:icon :name="$type?->icon() ?? 'bell'" class="mt-0.5 size-5 text-gray-500 dark:text-zinc-400" />
                                    <div>
                                        <p class="font-semibold text-gray-800 dark:text-zinc-100">{{ $data['title'] ?? 'Notifikasi' }}</p>
                                        <p class="text-xs text-gray-500 dark:text-zinc-400">{{ $data['message'] ?? '' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5">
                                @if (! empty($data['order_number']) && isset($this->pesananAda[$data['order_number']]))
                                    <a href="{{ route('admin.orders.show', $data['order_number']) }}" wire:navigate class="font-mono text-xs font-semibold text-brand-green hover:underline dark:text-zinc-100">{{ $data['order_number'] }}</a>
                                @elseif (! empty($data['order_number']))
                                    <span class="font-mono text-xs text-gray-500 dark:text-zinc-400">{{ $data['order_number'] }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-zinc-400">Pesanan sudah dihapus</span>
                                @else
                                    <span class="text-gray-500 dark:text-zinc-400">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5">
                                @if ($notification->read_at)
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500 dark:bg-zinc-800 dark:text-zinc-400"><span class="h-1.5 w-1.5 rounded-full bg-gray-400 dark:bg-zinc-600"></span>Dibaca</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-brand-green/10 px-2.5 py-1 text-xs font-semibold text-brand-green"><span class="h-1.5 w-1.5 rounded-full bg-brand-green"></span>Baru</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5 text-gray-500 dark:text-zinc-400">{{ tanggal_id($notification->created_at) }}</td>
                            <td class="px-3 py-3.5 sm:px-6">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button wire:click="open('{{ $notification->id }}')" size="xs" variant="ghost" icon="eye" class="cursor-pointer" />
                                    @unless ($notification->read_at)
                                        <flux:button wire:click="markRead('{{ $notification->id }}')" size="xs" variant="ghost" icon="check" class="cursor-pointer" />
                                    @endunless
                                    <flux:button wire:click="confirmDelete('{{ $notification->id }}')" size="xs" variant="ghost" icon="trash" class="cursor-pointer" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-zinc-400">Tidak ada notifikasi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-5 dark:border-zinc-800 sm:p-6">
            <p class="text-xs text-gray-500 dark:text-zinc-400">Menampilkan {{ $this->notifications->count() }} dari {{ $this->notifications->total() }}</p>
            {{ $this->notifications->links() }}
        </div>
    </div>

    <flux:modal name="notification-delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Notifikasi</flux:heading>
                <flux:subheading>Yakin untuk menghapus data ini? Tindakan ini tidak dapat dibatalkan.</flux:subheading>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <flux:modal.close><flux:button variant="outline" class="w-full">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger" class="w-full">Ya, hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
