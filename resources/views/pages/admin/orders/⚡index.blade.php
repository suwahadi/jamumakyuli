<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pesanan')] #[Layout('layouts::admin')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $cari = '';

    #[Url(as: 'status', history: true)]
    public string $filterStatus = '';

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function baris()
    {
        return Order::query()
            ->with(['paymentAttempts' => fn ($q) => $q->latest('id')])
            ->when($this->cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('order_number', 'like', "%{$this->cari}%")
                ->orWhere('customer_name', 'like', "%{$this->cari}%")
                ->orWhere('customer_email', 'like', "%{$this->cari}%")))
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function totalPesanan(): int
    {
        return Order::query()->count();
    }

    #[Computed]
    public function menungguPembayaran(): int
    {
        return Order::query()->where('status', OrderStatus::PENDING)->count();
    }

    #[Computed]
    public function pesananLunas(): int
    {
        return Order::query()->whereIn('status', OrderStatus::berpenghasilan())->count();
    }

    #[Computed]
    public function pesananDibatalkan(): int
    {
        return Order::query()->where('status', OrderStatus::CANCELLED)->count();
    }

    public function statusOptions(): array
    {
        return OrderStatus::cases();
    }

    public function metodeBayar(Order $order): string
    {
        $attempt = $order->paymentAttempts->first();
        $metode = $attempt?->payment_method ?? ($attempt?->latest_notification_payload['payment_type'] ?? null);

        return match (true) {
            filled($metode) => strtoupper(str_replace('_', ' ', $metode)),
            $attempt !== null => 'Midtrans',
            default => '-',
        };
    }

    public function statusPembayaran(OrderStatus $status): array
    {
        return match ($status) {
            OrderStatus::PENDING => ['Menunggu', 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400', 'bg-amber-500'],
            OrderStatus::PAID, OrderStatus::SHIPPED, OrderStatus::DELIVERED => ['Lunas', 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400', 'bg-emerald-500'],
            OrderStatus::CANCELLED => ['Gagal', 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400', 'bg-rose-500'],
        };
    }
}; ?>

<div data-segarkan-saat-kembali>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <h1 class="font-display text-2xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:text-[28px]">Pesanan</h1>
    </div>

    <section class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-admin.summary-card label="Total Pesanan" :value="$this->totalPesanan" icon="shopping-bag" tone="bg-brand-green/10 text-brand-green" />
        <x-admin.summary-card label="Menunggu Pembayaran" :value="$this->menungguPembayaran" icon="clock" tone="bg-brand-turmeric/15 text-brand-turmeric-ink" />
        <x-admin.summary-card label="Lunas" :value="$this->pesananLunas" icon="check-circle-2" tone="bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400" />
        <x-admin.summary-card label="Dibatalkan" :value="$this->pesananDibatalkan" icon="x-circle" tone="bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-zinc-400" />
    </section>

    <div class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3 p-5 sm:p-6">
            <label class="min-w-0 flex-1">
                <span class="sr-only">Cari</span>
                <input type="search" wire:model.live.debounce.400ms="cari" placeholder="Cari nomor, nama, atau email..." class="field">
            </label>
            <select wire:model.live="filterStatus" aria-label="Saring pesanan menurut status" class="field w-full sm:w-auto">
                <option value="">Semua Status</option>
                @foreach ($this->statusOptions() as $opt)
                    <option value="{{ $opt->value }}">{{ $opt->label() }}</option>
                @endforeach
            </select>
        </div>

        <ul class="divide-y divide-gray-100 border-t border-gray-100 dark:divide-zinc-800 dark:border-zinc-800 sm:hidden">
            @forelse ($this->baris as $order)
                @php [$labelBayar, $clsBayar, $dotBayar] = $this->statusPembayaran($order->status); @endphp
                <li wire:key="order-hp-{{ $order->id }}">
                    <a href="{{ route('admin.orders.show', $order->order_number) }}" wire:navigate class="block px-5 py-4 transition hover:bg-gray-50/70 dark:hover:bg-zinc-800/50">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-mono text-[13px] font-bold text-brand-dark dark:text-zinc-100">{{ $order->order_number }}</span>
                            <x-order-status-badge :status="$order->status" />
                        </div>
                        <p class="mt-1.5 truncate text-sm font-semibold text-gray-800 dark:text-zinc-100">{{ $order->customer_name }}</p>
                        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                            <span class="font-display text-sm font-bold text-brand-green">{{ rupiah($order->grand_total) }}</span>
                            <span class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-semibold {{ $clsBayar }}"><span class="h-1.5 w-1.5 rounded-full {{ $dotBayar }}"></span>{{ $labelBayar }}</span>
                            <span class="text-xs text-gray-500 dark:text-zinc-400">{{ tanggal_id($order->created_at) }}</span>
                        </div>
                    </a>
                </li>
            @empty
                <li class="px-5 py-12 text-center text-sm text-gray-500 dark:text-zinc-400">Tidak ada data yang cocok.</li>
            @endforelse
        </ul>

        <div class="hidden overflow-x-auto sm:block">
            <table class="w-full min-w-[1040px] text-sm">
                <thead>
                    <tr class="border-y border-gray-100 bg-gray-50/60 text-left font-display text-xs font-bold uppercase tracking-wide text-gray-400 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                        <th class="px-5 py-3.5 sm:px-6">No. Order</th>
                        <th class="px-3 py-3.5">Pelanggan</th>
                        <th class="px-3 py-3.5">Tanggal</th>
                        <th class="px-3 py-3.5">Metode</th>
                        <th class="px-3 py-3.5">Total</th>
                        <th class="px-3 py-3.5">Pembayaran</th>
                        <th class="px-3 py-3.5">Status</th>
                        <th class="px-3 py-3.5 text-right sm:px-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                    @forelse ($this->baris as $order)
                        <tr class="transition hover:bg-gray-50/70 dark:hover:bg-zinc-800/50" wire:key="order-{{ $order->id }}">
                            <td class="px-5 py-3.5 sm:px-6"><span class="font-mono text-[13px] font-bold text-brand-dark dark:text-zinc-100">{{ $order->order_number }}</span></td>
                            <td class="px-3 py-3.5">
                                <p class="text-[13px] font-semibold leading-tight text-gray-800 dark:text-zinc-100">{{ $order->customer_name }}</p>
                                <p class="mt-0.5 text-[11px] leading-tight text-gray-500 dark:text-zinc-400">{{ $order->customer_email }}</p>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5 text-gray-600 dark:text-zinc-400">{{ tanggal_id($order->created_at) }}</td>
                            <td class="whitespace-nowrap px-3 py-3.5"><span class="inline-flex rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $this->metodeBayar($order) }}</span></td>
                            <td class="whitespace-nowrap px-3 py-3.5 font-display font-bold text-brand-green">{{ rupiah($order->grand_total) }}</td>
                            <td class="px-3 py-3.5">
                                @php [$labelBayar, $clsBayar, $dotBayar] = $this->statusPembayaran($order->status); @endphp
                                <span class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold {{ $clsBayar }}"><span class="h-1.5 w-1.5 rounded-full {{ $dotBayar }}"></span>{{ $labelBayar }}</span>
                            </td>
                            <td class="px-3 py-3.5"><x-order-status-badge :status="$order->status" /></td>
                            <td class="px-3 py-3.5 sm:px-6">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.orders.show', $order->order_number) }}" wire:navigate class="inline-flex h-9 items-center gap-1.5 rounded-md border border-gray-300 px-3 text-xs font-display font-bold text-gray-700 transition hover:border-gray-400 hover:bg-gray-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800">
                                        <flux:icon.eye class="size-3.5" /> Detail
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-zinc-400">Tidak ada data yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-5 dark:border-zinc-800 sm:p-6">
            <p class="text-xs text-gray-500 dark:text-zinc-400">Menampilkan {{ $this->baris->count() }} dari {{ $this->baris->total() }}</p>
            {{ $this->baris->links() }}
        </div>
    </div>
</div>
