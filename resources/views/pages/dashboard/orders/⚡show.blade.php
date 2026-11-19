<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail Pesanan')] #[Layout('layouts::app')] class extends Component {
    public Order $order;

    public function mount(Order $order): void
    {
        Gate::authorize('view', $order);

        $this->order = $order->load('items.product', 'items.variant');
    }
}; ?>

<div class="space-y-5">
    <div>

        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-heading text-[26px] text-zinc-900 sm:text-[30px] dark:text-white">Detail Pesanan</h1>
                <p class="mt-0.5 font-mono text-sm text-zinc-500 dark:text-zinc-400">{{ $order->order_number }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3 self-start sm:self-auto">
                <x-order-status-badge :status="$order->status" class="px-3 py-1 text-xs" />
                @if ($order->status !== OrderStatus::CANCELLED)
                    <x-btn variant="dark" :href="route('orders.invoice', $order)" target="_blank">Lihat Invoice</x-btn>
                @endif
            </div>
        </div>
    </div>

    @if ($order->status === OrderStatus::PENDING)
        <div class="flex flex-col gap-3 overflow-hidden rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-amber-500/30 dark:bg-amber-500/10">
            <div class="flex items-start gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400">
                    <flux:icon.clock class="size-5" />
                </span>
                <div>
                    <p class="text-sm font-bold text-amber-800 dark:text-amber-300">Menunggu Pembayaran</p>
                    <p class="mt-0.5 text-sm text-amber-700 dark:text-amber-200/80">Selesaikan pembayaran <span class="font-mono font-bold">{{ rupiah($order->grand_total) }}</span> untuk memproses pesanan.</p>
                </div>
            </div>
            <x-btn variant="primary" :href="route('checkout.success', $order->uuid)" class="shrink-0">
                Lanjutkan Pembayaran
            </x-btn>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_320px]">
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">Rincian Pesanan</h2>
                <p class="mt-0.5 font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ tanggal_id($order->created_at) }}</p>
            </div>
            <div class="divide-y divide-zinc-100 px-5 dark:divide-zinc-800">
                @foreach ($order->items as $item)
                    <div class="flex items-center justify-between gap-3 py-3.5 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold text-zinc-800 dark:text-zinc-100">{{ $item->product->name ?? 'Produk' }}</p>
                            @if ($item->namaVarian())
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Varian: {{ $item->namaVarian() }}</p>
                            @endif
                            @if ($item->custom_text)
                                <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">Catatan Custom: {{ $item->custom_text }}</p>
                            @endif
                            <p class="mt-0.5 font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ rupiah($item->price) }} &times; {{ $item->quantity }}</p>
                        </div>
                        <span class="shrink-0 font-mono font-bold text-zinc-900 dark:text-white">{{ rupiah($item->total) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="space-y-2 border-t border-zinc-200 bg-zinc-50/60 px-5 py-4 text-sm dark:border-zinc-800 dark:bg-zinc-800/30">
                <div class="flex justify-between"><span class="text-zinc-500 dark:text-zinc-400">Subtotal</span><span class="font-mono text-zinc-900 dark:text-white">{{ rupiah($order->subtotal) }}</span></div>
                <div class="flex justify-between"><span class="text-zinc-500 dark:text-zinc-400">Ongkos Kirim ({{ $order->shippingCourierName() }})</span><span class="font-mono text-zinc-900 dark:text-white">{{ rupiah($order->shipping_cost) }}</span></div>
                <div class="flex justify-between border-t border-zinc-200 pt-2.5 text-base font-extrabold dark:border-zinc-700">
                    <span class="text-zinc-900 dark:text-white">Total</span><span class="font-mono text-primary dark:text-white">{{ rupiah($order->grand_total) }}</span>
                </div>
            </div>
        </div>

        <div class="h-fit space-y-4 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">Pengiriman</h2>
            <div class="space-y-4 text-sm">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Penerima</p>
                    <p class="mt-1 font-semibold text-zinc-800 dark:text-zinc-100">{{ $order->customer_name }}</p>
                    <p class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $order->customer_phone }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Alamat</p>
                    @if ($order->shipping_destination_label)
                        <p class="mt-1 font-semibold text-zinc-800 dark:text-zinc-100">{{ $order->shipping_destination_label }}</p>
                    @endif
                    <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $order->shipping_address }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Jasa Kirim</p>
                    <p class="mt-1 font-semibold text-zinc-800 dark:text-zinc-100">{{ $order->shippingCourierName() }}</p>
                    @if ($detail = $order->shippingServiceDetail())
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $detail }}</p>
                    @endif
                    <x-resi-pengiriman :order="$order" class="mt-3" />
                    @if ($buktiUrl = $order->buktiKirimUrl())
                        <div x-data="productLightbox()" class="mt-3">
                            <x-btn variant="dark" :href="$buktiUrl" target="_blank" rel="noopener" x-on:click.prevent="open(0, [$el.href])" class="!px-4">
                                <flux:icon.photo class="size-4" />
                                Lihat Bukti Kirim
                            </x-btn>
                            <x-product-lightbox label="Foto bukti kirim" />
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div>
        <x-btn variant="outline" :href="route('dashboard.orders.index')" wire:navigate>
            Kembali ke Riwayat Pesanan
        </x-btn>
    </div>
</div>
