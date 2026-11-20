@php
    $namaSitus = setting('site_name', 'Jamu Mak Yuli');
    $whatsapp = trim((string) setting('site_whatsapp', ''));
    $email = trim((string) setting('site_email', ''));
    $situs = \Illuminate\Support\Str::after(rtrim(url('/'), '/'), '://');
    $lunas = in_array($order->status, \App\Enums\OrderStatus::berpenghasilan(), true);
    $tanggal = fn ($waktu) => $waktu?->locale(app()->getLocale())->isoFormat('D MMMM YYYY');
    $kembali = auth()->user()?->isAdmin()
        ? route('admin.orders.show', $order)
        : route('checkout.success', $order);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <title>Invoice {{ $order->order_number }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}" />
    @vite(['resources/css/app.css', 'resources/js/unduh-pdf.js'])
    <style>
        @page { size: A4; margin: 0; }
        .pita-lunas { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @media print {
            html, body { background: #fff !important; }
        }
    </style>
</head>
<body class="bg-zinc-100 text-ink antialiased print:bg-white">
    <div class="mx-auto flex max-w-[826px] items-center gap-2 px-4 pt-6 pb-4 print:hidden">
        <a href="{{ $kembali }}" class="mr-auto inline-flex min-h-11 min-w-11 items-center gap-1.5 text-sm font-semibold text-zinc-700 underline-offset-4 hover:underline max-sm:justify-center">
            <flux:icon.arrow-left class="size-4 shrink-0" /><span class="max-sm:sr-only">{{ __('Kembali ke pesanan') }}</span>
        </a>
        <div class="grid grid-cols-2 gap-2">
            <button type="button" data-unduh-pdf="invoice-{{ $order->order_number }}.pdf" data-ukuran-mm="210x297" data-margin-mm="12"
                    data-teks-proses="{{ __('Menyiapkan PDF…') }}" data-teks-gagal="{{ __('PDF gagal dibuat di browser ini. Tekan Cetak, lalu pilih Simpan sebagai PDF.') }}"
                    class="inline-flex min-h-11 cursor-pointer items-center justify-center rounded-md bg-brand-green px-4 text-sm font-bold text-white transition hover:bg-brand-green-dark disabled:cursor-wait disabled:opacity-60">{{ __('Unduh') }}</button>
            <button type="button" onclick="window.print()" class="inline-flex min-h-11 cursor-pointer items-center justify-center rounded-md border border-zinc-400 bg-white px-4 text-sm font-bold text-ink transition hover:bg-zinc-50">{{ __('Cetak') }}</button>
        </div>
    </div>
    <p data-status-unduh role="status" class="mx-auto max-w-[826px] px-4 pb-3 text-right text-sm font-medium text-zinc-700 empty:pb-0 print:hidden"></p>

    <main class="relative mx-auto mb-10 max-w-[794px] overflow-hidden bg-white px-5 py-8 text-[13px] leading-relaxed shadow-sm sm:px-[52px] sm:py-12 print:m-0 print:max-w-none print:shadow-none">
        @if ($lunas)
            <div class="pita-lunas pointer-events-none absolute -right-[58px] top-[34px] w-[230px] rotate-45 bg-green-700 py-[7px] text-center" aria-hidden="true">
                <span class="text-base font-extrabold tracking-[0.18em] text-white">{{ __('LUNAS') }}</span>
            </div>
        @endif

        <header class="flex flex-col gap-6 border-b-2 border-ink pb-6 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <x-wordmark size="text-2xl" class="text-ink" />
                <p class="mt-3 text-xs text-zinc-600">
                    @if ($whatsapp !== '')
                        WhatsApp {{ $whatsapp }}<br>
                    @endif
                    @if ($email !== '')
                        {{ $email }}<br>
                    @endif
                    {{ $situs }}
                </p>
            </div>

            <div class="sm:mr-[72px] sm:text-right">
                <h1 class="text-[30px] font-extrabold leading-none tracking-tight">{{ __('Invoice') }}</h1>
                @if ($lunas)
                    <p class="sr-only">{{ __('Status') }}: {{ __('LUNAS') }}</p>
                @endif
                <dl class="mt-3 grid grid-cols-[auto_auto] justify-start gap-x-4 gap-y-0.5 text-xs sm:justify-end">
                    <dt class="text-zinc-600">{{ __('Nomor') }}</dt>
                    <dd class="notranslate font-bold sm:text-right">{{ $order->order_number }}</dd>
                    <dt class="text-zinc-600">{{ __('Tanggal pesanan') }}</dt>
                    <dd class="sm:text-right">{{ $tanggal($order->created_at) }}</dd>
                    @if ($lunas && $order->paid_at)
                        <dt class="text-zinc-600">{{ __('Tanggal lunas') }}</dt>
                        <dd class="sm:text-right">{{ $tanggal($order->paid_at) }}</dd>
                    @endif
                </dl>
            </div>
        </header>

        <section class="grid gap-6 py-6 sm:grid-cols-2 sm:gap-8">
            <div>
                <h2 class="mb-1.5 text-xs font-bold text-zinc-600">{{ __('Ditagihkan kepada') }}</h2>
                <p>
                    <strong>{{ $order->customer_name }}</strong><br>
                    {{ $order->customer_phone }}<br>
                    {{ $order->customer_email }}
                </p>
            </div>
            <div>
                <h2 class="mb-1.5 text-xs font-bold text-zinc-600">{{ __('Dikirim ke') }}</h2>
                <p>
                    {{ $order->shipping_address }}
                    @if ($order->shipping_destination_label)
                        <br>{{ $order->shipping_destination_label }}
                    @endif
                </p>
                <p class="mt-2 text-zinc-600">
                    {{ $order->shippingCourierName() }}@if ($order->tracking_number), {{ __('resi') }} <strong class="notranslate text-ink">{{ $order->tracking_number }}</strong>@endif
                </p>
            </div>
        </section>

        <table class="w-full border-collapse">
            <thead>
                <tr class="border-b border-ink text-left text-xs font-bold text-zinc-600">
                    <th class="py-2 font-bold">{{ __('Produk') }}</th>
                    <th class="py-2 pl-4 text-right font-bold">{{ __('Jumlah') }}</th>
                    <th class="hidden py-2 pl-4 text-right font-bold sm:table-cell">{{ __('Harga satuan') }}</th>
                    <th class="py-2 pl-4 text-right font-bold">{{ __('Subtotal') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    @php $sku = $item->skuBarang(); @endphp
                    <tr class="border-b border-zinc-300 align-top">
                        <td class="py-3">
                            {{ $item->product?->name ?? __('Produk') }}
                            @if ($item->namaVarian())
                                <span class="block text-xs text-zinc-600">{{ __('Varian') }}: {{ $item->namaVarian() }}</span>
                            @endif
                            @if ($item->custom_text)
                                <span class="block text-xs text-zinc-600">{{ __('Catatan') }}: {{ $item->custom_text }}</span>
                            @endif
                            @if ($sku)
                                <span class="notranslate block text-xs text-zinc-600">SKU {{ $sku }}</span>
                            @endif
                            <span class="notranslate block text-xs text-zinc-600 sm:hidden">{{ rupiah($item->price) }} &times; {{ $item->quantity }}</span>
                        </td>
                        <td class="py-3 pl-4 text-right tabular-nums">{{ $item->quantity }}</td>
                        <td class="notranslate hidden whitespace-nowrap py-3 pl-4 text-right tabular-nums sm:table-cell">{{ rupiah($item->price) }}</td>
                        <td class="notranslate whitespace-nowrap py-3 pl-4 text-right tabular-nums">{{ rupiah($item->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-5 flex flex-col-reverse gap-6 sm:flex-row sm:justify-between sm:gap-8">
            <div>
                <h2 class="mb-1.5 text-xs font-bold text-zinc-600">{{ __('Pembayaran') }}</h2>
                <p>{{ $order->paymentMethodLabel() }}</p>
            </div>
            <table class="w-full border-collapse sm:w-auto sm:min-w-[300px]">
                <tr>
                    <td class="py-1 text-zinc-600">{{ __('Subtotal') }}</td>
                    <td class="notranslate whitespace-nowrap py-1 pl-6 text-right tabular-nums">{{ rupiah($order->subtotal) }}</td>
                </tr>
                <tr>
                    <td class="py-1 text-zinc-600">{{ __('Ongkos kirim') }} ({{ $order->shippingCourierName() }})</td>
                    <td class="notranslate whitespace-nowrap py-1 pl-6 text-right tabular-nums">{{ rupiah($order->shipping_cost) }}</td>
                </tr>
                <tr class="border-t-2 border-ink text-base font-extrabold">
                    <td class="pt-2.5">{{ $lunas ? __('Total dibayar') : __('Total tagihan') }}</td>
                    <td class="notranslate whitespace-nowrap pl-6 pt-2.5 text-right tabular-nums text-brand-green">{{ rupiah($order->grand_total) }}</td>
                </tr>
            </table>
        </div>

        <footer class="mt-10 border-t border-zinc-300 pt-3.5 text-xs text-zinc-600">
            {{ __('Terima kasih sudah berbelanja di :site', ['site' => $namaSitus]) }}
        </footer>
    </main>
</body>
</html>
