<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard Admin')] #[Layout('layouts::admin')] class extends Component {
    #[Computed]
    public function omsetBulanIni(): int
    {
        return (int) Order::query()
            ->whereIn('status', OrderStatus::berpenghasilan())
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('grand_total');
    }

    #[Computed]
    public function jumlahPengguna(): int
    {
        return User::query()->count();
    }

    #[Computed]
    public function jumlahPesananBulanIni(): int
    {
        return Order::query()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    #[Computed]
    public function jumlahProdukAktif(): int
    {
        return Product::query()->where('is_active', true)->count();
    }

    #[Computed]
    public function omsetHarian(): array
    {
        $mulai = now()->subDays(29)->startOfDay();

        $terkumpul = Order::query()
            ->whereIn('status', OrderStatus::berpenghasilan())
            ->where('created_at', '>=', $mulai)
            ->get(['created_at', 'grand_total'])
            ->groupBy(fn ($order) => $order->created_at->toDateString())
            ->map(fn ($grup) => (int) $grup->sum('grand_total'));

        return collect(range(0, 29))
            ->map(function (int $i) use ($mulai, $terkumpul): array {
                $tanggal = $mulai->copy()->addDays($i)->toDateString();

                return ['tanggal' => $tanggal, 'nilai' => $terkumpul[$tanggal] ?? 0];
            })
            ->all();
    }

    #[Computed]
    public function produkTerlaris()
    {
        return Product::query()
            ->select('products.id', 'products.name', 'products.sku')
            ->selectRaw('SUM(order_items.quantity) as jumlah_terjual')
            ->join('order_items', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', array_map(fn (OrderStatus $s): string => $s->value, OrderStatus::berpenghasilan()))
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('jumlah_terjual')
            ->take(5)
            ->get();
    }

    #[Computed]
    public function pesananTerbaru()
    {
        return Order::query()->latest()->take(5)->get();
    }

    #[Computed]
    public function stokMenipis()
    {
        return Product::query()
            ->active()
            ->stokMenipis()
            ->with('variants')
            ->orderByRaw('COALESCE((SELECT MIN(stock) FROM variants WHERE variants.product_id = products.id), products.stock)')
            ->take(5)
            ->get(['id', 'name', 'sku', 'stock']);
    }
}; ?>

<div data-segarkan-saat-kembali>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="sec-tick font-display text-2xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:text-[28px]">Dashboard</h1>
            <p class="mt-1.5 text-sm text-gray-500 dark:text-zinc-400">Selamat datang kembali, {{ auth()->user()->name }}.</p>
        </div>
    </div>

    <section class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <div class="col-span-2 rounded-xl border border-gray-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900 sm:col-span-1 sm:p-5">
            <span class="grid size-9 place-items-center rounded-lg bg-brand-green/10 text-brand-green sm:size-11">
                <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-wallet"/></svg>
            </span>
            <p class="mt-3 font-display text-xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:mt-4 sm:text-2xl">{{ rupiah($this->omsetBulanIni) }}</p>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-zinc-400">Penjualan</p>
            <p class="mt-2 border-t border-gray-100 pt-2 text-xs text-gray-500 dark:border-zinc-800 dark:text-zinc-400">Bulan ini, lunas dan dikirim</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900 sm:p-5">
            <span class="grid size-9 place-items-center rounded-lg bg-brand-green/10 text-brand-green sm:size-11">
                <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-users-round"/></svg>
            </span>
            <p class="mt-3 font-display text-xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:mt-4 sm:text-2xl">{{ number_format($this->jumlahPengguna, 0, ',', '.') }}</p>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-zinc-400">Total Pengguna</p>
            <p class="mt-2 border-t border-gray-100 pt-2 text-xs text-gray-500 dark:border-zinc-800 dark:text-zinc-400">Terdaftar sepanjang waktu</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900 sm:p-5">
            <span class="grid size-9 place-items-center rounded-lg bg-brand-green/10 text-brand-green sm:size-11">
                <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-shopping-cart"/></svg>
            </span>
            <p class="mt-3 font-display text-xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:mt-4 sm:text-2xl">{{ number_format($this->jumlahPesananBulanIni, 0, ',', '.') }}</p>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-zinc-400">Pesanan</p>
            <p class="mt-2 border-t border-gray-100 pt-2 text-xs text-gray-500 dark:border-zinc-800 dark:text-zinc-400">Bulan ini</p>
        </div>

        <div class="col-span-2 rounded-xl border border-gray-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900 sm:col-span-1 sm:p-5">
            <span class="grid size-9 place-items-center rounded-lg bg-brand-green/10 text-brand-green sm:size-11">
                <svg class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-package"/></svg>
            </span>
            <p class="mt-3 font-display text-xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:mt-4 sm:text-2xl">{{ number_format($this->jumlahProdukAktif, 0, ',', '.') }}</p>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-zinc-400">Produk Aktif</p>
            <p class="mt-2 border-t border-gray-100 pt-2 text-xs text-gray-500 dark:border-zinc-800 dark:text-zinc-400">Sedang dijual</p>
        </div>
    </section>

    <section class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 sm:p-6 lg:col-span-2">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100 md:text-lg">Statistik Penjualan</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">Omset harian. 30 hari terakhir</p>
                </div>
                <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-zinc-400">
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-brand-green"></span> Penjualan</span>
                </div>
            </div>

            @php
                $harian = $this->omsetHarian;
                $puncak = max(1, max(array_column($harian, 'nilai')));
            @endphp

            <div class="flex h-[240px] items-end justify-between gap-[3px] sm:gap-1">
                @foreach ($harian as $i => $hari)
                    <div class="group flex h-full flex-1 flex-col items-center justify-end gap-2">
                        <div class="relative flex w-full flex-1 items-end justify-center">
                            <div class="bar relative w-full rounded-t-[3px] bg-brand-green/70 group-hover:bg-brand-green"
                                 style="height: {{ round($hari['nilai'] / $puncak * 100, 2) }}%">
                                <span class="absolute -top-6 left-1/2 z-10 -translate-x-1/2 whitespace-nowrap rounded bg-brand-dark px-1.5 py-0.5 font-display text-[10px] font-bold text-white opacity-0 transition group-hover:opacity-100">{{ rupiah($hari['nilai']) }}</span>
                            </div>
                        </div>
                        <span class="h-3.5 text-[10px] font-medium tabular-nums text-gray-500 dark:text-zinc-400">{{ $i % 5 === 0 || $i === 29 ? $i + 1 : '' }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100 md:text-lg">Produk Terlaris</h2>
            </div>
            @if ($this->produkTerlaris->isEmpty())
                <p class="py-10 text-center text-sm text-gray-500 dark:text-zinc-400">Belum ada penjualan dari pesanan yang sudah dibayar.</p>
            @else
                @php $puncakTerjual = max(1, (int) $this->produkTerlaris->max('jumlah_terjual')); @endphp
                <ol class="space-y-1">
                    @foreach ($this->produkTerlaris as $i => $produk)
                        <li class="flex items-center gap-3 py-2.5 {{ $i ? 'border-t border-gray-100 dark:border-zinc-800' : '' }}">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-md font-display text-sm font-bold {{ $i === 0 ? 'bg-brand-green text-white' : 'bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-zinc-400' }}">{{ $i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold leading-tight text-brand-dark dark:text-zinc-100">{{ $produk->name }}</p>
                                <div class="mt-1 flex items-center gap-2">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-zinc-800">
                                        <div class="h-full rounded-full bg-brand-turmeric" style="width: {{ round($produk->jumlah_terjual / $puncakTerjual * 100, 2) }}%"></div>
                                    </div>
                                    <span class="shrink-0 font-mono text-[11px] text-gray-500 dark:text-zinc-400">{{ (int) $produk->jumlah_terjual }}</span>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-2">
            <div class="flex items-center justify-between p-5 pb-4 sm:p-6 sm:pb-4">
                <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100 md:text-lg">Pesanan Terbaru</h2>
                <a href="{{ route('admin.orders.index') }}" wire:navigate class="flex items-center gap-1 text-xs font-display font-bold text-brand-green hover:underline">
                    Lihat Semua
                    <svg class="h-3.5 w-3.5 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-arrow"/></svg>
                </a>
            </div>
            @if ($this->pesananTerbaru->isEmpty())
                <p class="px-5 py-10 text-center text-sm text-gray-500 dark:text-zinc-400 sm:px-6">Belum ada pesanan.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-sm">
                        <thead>
                            <tr class="border-y border-gray-100 bg-gray-50/60 text-left font-display text-xs font-bold uppercase tracking-wide text-gray-400 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                                <th class="px-5 py-3 sm:px-6">Order</th>
                                <th class="px-3 py-3">Pelanggan</th>
                                <th class="px-3 py-3">Total</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-3 py-3 text-right sm:px-6">Waktu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            @foreach ($this->pesananTerbaru as $order)
                                <tr class="transition hover:bg-gray-50/70 dark:hover:bg-zinc-800/50">
                                    <td class="whitespace-nowrap px-5 py-3.5 sm:px-6"><span class="font-mono text-[13px] font-bold text-brand-dark dark:text-zinc-100">{{ $order->order_number }}</span></td>
                                    <td class="px-3 py-3.5 text-gray-700 dark:text-zinc-300">{{ $order->customer_name }}</td>
                                    <td class="px-3 py-3.5 font-display font-bold text-brand-green">{{ rupiah($order->grand_total) }}</td>
                                    <td class="px-3 py-3.5"><x-order-status-badge :status="$order->status" /></td>
                                    <td class="px-3 py-3.5 text-right text-xs text-gray-500 dark:text-zinc-400 sm:px-6">{{ $order->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100 md:text-lg">Stok Menipis</h2>
            </div>
            @if ($this->stokMenipis->isEmpty())
                <p class="py-10 text-center text-sm text-gray-500 dark:text-zinc-400">Semua stok aman.</p>
            @else
                <ul class="space-y-3">
                    @foreach ($this->stokMenipis as $produk)
                        @php $sisa = $produk->stokTerendah(); @endphp
                        <li class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-gray-100 text-gray-400 dark:bg-zinc-800 dark:text-zinc-400">
                                <svg class="h-4 w-4 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-box"/></svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold leading-tight text-brand-dark dark:text-zinc-100">{{ $produk->name }}</p>
                                <p class="font-mono text-[11px] text-gray-500 dark:text-zinc-400">{{ $produk->sku }}</p>
                            </div>
                            <span class="shrink-0 font-display text-sm font-bold {{ $sisa <= 2 ? 'text-brand-green' : 'text-brand-turmeric-ink' }}">{{ $sisa }} <span class="text-[10px] font-medium text-gray-500 dark:text-zinc-400">tersisa</span></span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <a href="{{ route('admin.stock-opname.index') }}" wire:navigate class="mt-5 grid h-10 w-full place-items-center rounded-md border-2 border-brand-green font-display text-sm font-bold text-brand-green transition hover:bg-brand-green hover:text-white">Kelola Stok</a>
        </div>
    </section>
</div>
