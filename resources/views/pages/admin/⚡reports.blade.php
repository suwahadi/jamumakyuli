<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Laporan Penjualan')] #[Layout('layouts::admin')] class extends Component {
    #[Computed]
    public function perBulan(): array
    {
        $mulai = now()->subMonths(11)->startOfMonth();

        $pesanan = Order::query()
            ->whereIn('status', OrderStatus::berpenghasilan())
            ->where('created_at', '>=', $mulai)
            ->get(['created_at', 'grand_total']);

        $terkumpul = $pesanan->groupBy(fn ($o) => $o->created_at->format('Y-m'));

        return collect(range(0, 11))
            ->map(function (int $i) use ($mulai, $terkumpul): array {
                $bulan = $mulai->copy()->addMonths($i);
                $grup = $terkumpul[$bulan->format('Y-m')] ?? collect();
                $omset = (int) $grup->sum('grand_total');
                $jumlah = $grup->count();

                return [
                    'label' => $bulan->locale('id')->isoFormat('MMMM YYYY'),
                    'omset' => $omset,
                    'jumlah' => $jumlah,
                    'rata' => $jumlah > 0 ? intdiv($omset, $jumlah) : 0,
                ];
            })
            ->all();
    }

    #[Computed]
    public function omsetSetahun(): int
    {
        return array_sum(array_column($this->perBulan, 'omset'));
    }

    #[Computed]
    public function jumlahPesananSetahun(): int
    {
        return array_sum(array_column($this->perBulan, 'jumlah'));
    }

    #[Computed]
    public function rataRataPesanan(): int
    {
        $jumlah = $this->jumlahPesananSetahun;

        return $jumlah > 0 ? intdiv($this->omsetSetahun, $jumlah) : 0;
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:text-[28px]">Laporan Penjualan</h1>
        <p class="mt-1.5 text-sm text-gray-500 dark:text-zinc-400">Omset, jumlah pesanan, dan rata-rata nilai pesanan. Dua belas bulan terakhir, hanya pesanan lunas dan dikirim.</p>
    </div>

    <section class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
        <x-admin.summary-card class="col-span-2 sm:col-span-1" label="Omset 12 Bulan" :value="rupiah($this->omsetSetahun)" icon="wallet" tone="bg-brand-green/10 text-brand-green" />
        <x-admin.summary-card label="Jumlah Pesanan" :value="number_format($this->jumlahPesananSetahun, 0, ',', '.')" icon="shopping-bag" tone="bg-brand-turmeric/15 text-brand-turmeric-ink" />
        <x-admin.summary-card label="Rata-rata Nilai Pesanan" :value="rupiah($this->rataRataPesanan)" icon="chart-column" tone="bg-sky-50 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400" />
    </section>

    <section class="mb-6 rounded-xl border border-gray-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
        <div class="mb-6">
            <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Omset Bulanan</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">Dua belas bulan terakhir</p>
        </div>

        @php
            $perBulan = $this->perBulan;
            $puncak = max(1, max(array_column($perBulan, 'omset')));
        @endphp

        <div class="flex h-[240px] items-end justify-between gap-1 sm:gap-3">
            @foreach ($perBulan as $bulan)
                <div class="group flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2">
                    <div class="relative flex w-full flex-1 items-end justify-center">
                        <div class="bar relative w-full rounded-t-[3px] bg-brand-green/70 group-hover:bg-brand-green"
                             style="height: {{ round($bulan['omset'] / $puncak * 100, 2) }}%">
                            <span class="absolute -top-6 left-1/2 z-10 -translate-x-1/2 whitespace-nowrap rounded bg-brand-dark px-1.5 py-0.5 font-display text-[10px] font-bold text-white opacity-0 transition group-hover:opacity-100">{{ rupiah($bulan['omset']) }}</span>
                        </div>
                    </div>
                    <span class="h-7 text-center text-xs font-medium leading-tight text-gray-500 dark:text-zinc-400"><span class="sm:hidden">{{ mb_substr(explode(' ', $bulan['label'])[0], 0, 3) }}</span><span class="hidden sm:inline">{{ explode(' ', $bulan['label'])[0] }}</span></span>
                </div>
            @endforeach
        </div>
    </section>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
            <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Rincian per Bulan</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[560px] text-sm">
                <thead>
                    <tr class="border-y border-gray-100 bg-gray-50/60 text-left font-display text-xs font-bold uppercase tracking-wide text-gray-400 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                        <th class="px-5 py-3 sm:px-6">Bulan</th>
                        <th class="px-3 py-3">Omset</th>
                        <th class="px-3 py-3">Jumlah Pesanan</th>
                        <th class="px-3 py-3">Rata-rata</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                    @foreach ($perBulan as $bulan)
                        <tr class="transition hover:bg-gray-50/70 dark:hover:bg-zinc-800/50">
                            <td class="px-5 py-3.5 font-semibold text-brand-dark dark:text-zinc-100 sm:px-6">{{ $bulan['label'] }}</td>
                            <td class="px-3 py-3.5 font-display font-bold text-brand-green">{{ rupiah($bulan['omset']) }}</td>
                            <td class="px-3 py-3.5 text-gray-700 dark:text-zinc-300">{{ number_format($bulan['jumlah'], 0, ',', '.') }}</td>
                            <td class="px-3 py-3.5 text-gray-700 dark:text-zinc-300">{{ rupiah($bulan['rata']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
