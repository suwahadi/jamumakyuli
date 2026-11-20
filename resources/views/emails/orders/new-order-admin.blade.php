@extends('emails.layout')

@section('eyebrow', 'Pesanan Baru Masuk')
@section('preheader', 'Pesanan baru '.$order->order_number.' dari '.$order->customer_name.', '.rupiah($order->grand_total).'.')
@section('heading', 'Pesanan Baru Masuk')

@section('content')
    <x-email::text>
        Pesanan baru <x-email::strong>{{ $order->order_number }}</x-email::strong> telah dibuat dan
        <x-email::strong>menunggu pembayaran</x-email::strong>. Siapkan ketersediaan stok untuk item berikut.
        Pemrosesan pengiriman dilakukan setelah pembayaran terkonfirmasi lunas.
    </x-email::text>

    <x-email::callout tone="warning" label="Total Pesanan" :value="rupiah($order->grand_total)" badge="MENUNGGU PEMBAYARAN" />

    <x-email::button :href="route('admin.orders.show', $order)" variant="admin">Buka di Panel Admin</x-email::button>

    <x-email::order-summary :order="$order" />
    <x-email::shipping :order="$order" heading="Pemesan & Tujuan Pengiriman" />
@endsection
