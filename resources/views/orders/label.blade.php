@php
    $namaToko = setting('site_name', 'Jamu Mak Yuli');
    $teleponToko = trim((string) (setting('site_phone') ?: setting('site_whatsapp', '')));
    $alamatToko = trim((string) setting('site_address', ''));
    $lunas = ($order->paid_at ?? $order->created_at)->locale('id')->isoFormat('D MMM YYYY HH.mm');
    $gram = $order->beratTotalGram();
    $berat = $gram >= 1000
        ? rtrim(rtrim(number_format($gram / 1000, 2, ',', '.'), '0'), ',').' kg'
        : $gram.' g';
    $jumlahBarang = (int) $order->items->sum('quantity');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <title>Label {{ $order->order_number }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}" />
    @vite(['resources/css/app.css', 'resources/js/unduh-pdf.js'])
    <style>
        @page { size: 100mm 150mm; margin: 0; }
        @media print {
            html, body { background: #fff !important; }
        }
    </style>
</head>
<body class="bg-zinc-100 font-sans text-ink antialiased print:bg-white">
    <div class="mx-auto flex w-[100mm] max-w-full items-center gap-2 px-1 pt-6 pb-4 print:hidden">
        <a href="{{ route('admin.orders.show', $order) }}" class="mr-auto inline-flex min-h-11 min-w-11 items-center gap-1.5 text-sm font-semibold text-zinc-700 underline-offset-4 hover:underline max-sm:justify-center">
            <flux:icon.arrow-left class="size-4 shrink-0" /><span class="max-sm:sr-only">Kembali ke pesanan</span>
        </a>
        <div class="grid grid-cols-2 gap-2">
            <button type="button" data-unduh-pdf="label-{{ $order->order_number }}.pdf" data-ukuran-mm="100x150" data-margin-mm="4" class="inline-flex min-h-11 cursor-pointer items-center justify-center rounded-md bg-brand-green px-4 text-sm font-bold text-white transition hover:bg-brand-green-dark disabled:cursor-wait disabled:opacity-60">Unduh</button>
            <button type="button" onclick="window.print()" class="inline-flex min-h-11 cursor-pointer items-center justify-center rounded-md border border-zinc-400 bg-white px-4 text-sm font-bold text-ink transition hover:bg-zinc-50">Cetak</button>
        </div>
    </div>
    <p data-status-unduh role="status" class="mx-auto w-[100mm] max-w-full px-1 pb-3 text-right text-sm font-medium text-zinc-700 empty:pb-0 print:hidden"></p>

    <main class="mx-auto box-border min-h-[150mm] w-[100mm] max-w-full border border-black bg-white p-[4mm] text-[9pt] leading-snug print:min-h-0 print:border-0">
        <div class="flex items-start justify-between gap-2 border-b-2 border-black pb-[2mm]">
            <p class="text-[13pt] font-bold leading-tight">{{ $namaToko }}</p>
            <div class="text-right">
                <p class="text-[7pt] uppercase tracking-wide">No. Pesanan</p>
                <p class="font-mono text-[11pt] font-bold leading-tight">{{ $order->order_number }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-x-2 border-b border-black py-[1.5mm] text-[8pt]">
            <p>Tanggal lunas<br><span class="font-bold">{{ $lunas }}</span></p>
            <p class="text-right">Kurir<br><span class="font-bold">{{ $order->shippingCourierName() }}</span></p>
        </div>

        <section class="border-b border-black py-[2mm]">
            <h2 class="text-[7pt] font-bold uppercase tracking-wide">Pengirim</h2>
            <p class="font-bold">{{ $namaToko }}@if ($teleponToko) <span class="font-normal">· {{ $teleponToko }}</span>@endif</p>
            @if ($alamatToko)
                <p>{{ $alamatToko }}</p>
            @endif
        </section>

        <section class="border-b-2 border-black py-[2mm]">
            <h2 class="text-[7pt] font-bold uppercase tracking-wide">Penerima</h2>
            <p class="text-[11pt] font-bold leading-tight">{{ $order->customer_name }}</p>
            <p class="font-mono font-bold">{{ $order->customer_phone }}</p>
            <p class="mt-[1mm]">{{ $order->shipping_address }}</p>
            @if ($order->shipping_destination_label)
                <p class="font-bold">{{ $order->shipping_destination_label }}</p>
            @endif
        </section>

        <div class="grid grid-cols-2 gap-x-2 border-b border-black py-[1.5mm] text-[8pt]">
            <p>Berat total<br><span class="text-[10pt] font-bold">{{ $berat }}</span></p>
            <p class="text-right">Jumlah barang<br><span class="text-[10pt] font-bold">{{ $jumlahBarang }}</span></p>
        </div>

        @if ($order->tracking_number)
            <p class="border-b border-black py-[1.5mm] text-[8pt]">No. Resi <span class="font-mono text-[10pt] font-bold">{{ $order->tracking_number }}</span></p>
        @endif

        <table class="mt-[2mm] w-full border-collapse text-[8pt]">
            <thead>
                <tr class="border-b border-black text-left">
                    <th class="py-[1mm] pr-[1.5mm] font-bold">Produk</th>
                    <th class="py-[1mm] pr-[1.5mm] font-bold">SKU / Varian</th>
                    <th class="py-[1mm] text-right font-bold">Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr class="break-inside-avoid border-b border-black/40 align-top">
                        <td class="py-[1mm] pr-[1.5mm]">
                            {{ $item->product?->name ?? 'Produk dihapus' }}
                            @if ($item->custom_text)
                                <span class="block font-bold">Catatan: {{ $item->custom_text }}</span>
                            @endif
                        </td>
                        <td class="py-[1mm] pr-[1.5mm] font-mono break-all">
                            @if ($item->sku === null && $item->variant_id === null && $item->product?->hasVariants())
                                <span class="font-sans font-bold">Varian tidak diketahui, cek detail pesanan</span>
                            @else
                                {{ $item->skuBarang() ?? '-' }}
                                @if ($item->namaVarian())
                                    <span class="block font-sans">{{ $item->namaVarian() }}</span>
                                @endif
                            @endif
                        </td>
                        <td class="py-[1mm] text-right text-[10pt] font-bold">{{ $item->quantity }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </main>
</body>
</html>
