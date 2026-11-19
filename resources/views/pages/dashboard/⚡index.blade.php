<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] #[Layout('layouts::app')] class extends Component {
    #[Computed]
    public function stats(): array
    {
        $user = auth()->user();

        return [
            'disiapkan' => Order::ownedBy($user)->where('status', OrderStatus::PAID)->count(),
            'total' => Order::ownedBy($user)->count(),
            'dikirim' => Order::ownedBy($user)->whereIn('status', [OrderStatus::SHIPPED, OrderStatus::DELIVERED])->count(),
        ];
    }

    #[Computed]
    public function recentOrders(): Collection
    {
        return Order::ownedBy(auth()->user())->latest()->take(10)->get();
    }
}; ?>

@php $firstName = explode(' ', trim(auth()->user()->name))[0] ?: auth()->user()->name; @endphp

<div class="space-y-6">
    <section class="rounded-2xl border border-zinc-200 bg-white p-6 sm:p-8 dark:border-zinc-800 dark:bg-zinc-900">
        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-primary">Dashboard</p>
        <h1 class="mt-1.5 text-heading text-[28px] text-zinc-900 sm:text-[34px] dark:text-white">Halo, {{ $firstName }}</h1>
        <p class="mt-1 max-w-md text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">Pantau status pesanan dan produk favorit Anda dari satu tempat.</p>

        <div class="mt-6 flex flex-wrap gap-3">
            <x-btn variant="primary" :href="route('products.index')" wire:navigate>
                Belanja Lagi
            </x-btn>
            <x-btn variant="outline" :href="route('dashboard.orders.index')" wire:navigate>
                Lihat Transaksi
            </x-btn>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-dashboard.stat-card label="Total Pesanan" :value="number_format($this->stats['total'], 0, ',', '.')" icon="shopping-bag" />
        <x-dashboard.stat-card label="Sedang Disiapkan" :value="number_format($this->stats['disiapkan'], 0, ',', '.')" icon="archive-box" />
        <x-dashboard.stat-card label="Sudah Dikirim" :value="number_format($this->stats['dikirim'], 0, ',', '.')" icon="truck" />
    </section>

    <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-900 dark:text-white">Pesanan Terakhir</h2>
                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">Transaksi terbaru Anda</p>
            </div>
            <a href="{{ route('dashboard.orders.index') }}" wire:navigate
               class="inline-flex shrink-0 items-center gap-1 text-xs font-bold text-primary transition-colors hover:text-primary-dark">
                Lihat Semua <flux:icon.arrow-right class="size-3.5" />
            </a>
        </div>

        @forelse ($this->recentOrders as $order)
            <a href="{{ route('dashboard.orders.show', $order->order_number) }}" wire:navigate
               class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-3.5 transition last:border-b-0 hover:bg-zinc-50 dark:border-zinc-800/80 dark:hover:bg-zinc-800/50">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary dark:bg-primary/25 dark:text-white">
                        <flux:icon.receipt-percent class="size-[18px]" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate font-mono text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ $order->order_number }}</p>
                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ tanggal_id($order->created_at) }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <span class="hidden font-mono text-sm font-bold text-zinc-900 sm:inline dark:text-white">{{ rupiah($order->grand_total) }}</span>
                    <x-order-status-badge :status="$order->status" />
                </div>
            </a>
        @empty
            <div class="flex flex-col items-center gap-3 px-5 py-14 text-center">
                <span class="grid size-14 place-items-center rounded-2xl bg-zinc-100 text-zinc-300 dark:bg-zinc-800 dark:text-zinc-600">
                    <flux:icon.shopping-bag class="size-7" />
                </span>
                <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">Belum ada pesanan</p>
                <p class="-mt-1 text-xs text-zinc-500 dark:text-zinc-400">Yuk mulai belanja produk pilihan Anda.</p>
                <x-btn variant="primary" :href="route('products.index')" wire:navigate class="mt-1">
                    Jelajahi Katalog
                </x-btn>
            </div>
        @endforelse
    </section>
</div>
