@extends('emails.layout')

@section('eyebrow', 'Pesanan Anda')
@section('preheader', 'Pesanan '.$order->order_number.' telah kami terima. Selesaikan pembayaran '.rupiah($order->grand_total).'.')
@section('heading', 'Terima kasih, '.$order->customer_name.'!')

@section('content')
    <x-email::text>
        Pesanan Anda telah kami terima dan saat ini <x-email::strong>menunggu pembayaran</x-email::strong>.
        Selesaikan pembayaran agar pesanan Anda segera kami proses.
    </x-email::text>

    <x-email::callout tone="warning" label="Nomor Pesanan" :value="$order->order_number" badge="MENUNGGU PEMBAYARAN" />
    <x-email::callout tone="brand" label="Total yang harus dibayar" :value="rupiah($order->grand_total)" size="xl" />

    <x-email::button :href="route('checkout.success', $order)">Bayar Sekarang</x-email::button>

    <x-email::order-summary :order="$order" />
    <x-email::shipping :order="$order" heading="Dikirim Ke" />
@endsection
