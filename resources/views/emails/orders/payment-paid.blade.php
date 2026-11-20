@extends('emails.layout')

@section('eyebrow', 'Pembayaran Lunas')
@section('preheader', 'Pembayaran pesanan '.$order->order_number.' telah lunas. Pesanan sedang disiapkan.')
@section('heading', 'Pembayaran Berhasil Diterima')

@section('content')
    <x-email::text>
        Terima kasih, <x-email::strong>{{ $order->customer_name }}</x-email::strong>. Pembayaran untuk pesanan
        <x-email::strong>{{ $order->order_number }}</x-email::strong> telah kami terima secara penuh.
        Produk Anda sedang dipersiapkan untuk pengiriman.
    </x-email::text>

    <x-email::callout tone="success" label="Nomor Pesanan" :value="$order->order_number" badge="LUNAS"
                      :note="$order->paid_at ? 'Lunas pada '.tanggal_id($order->paid_at) : null" />

    <x-email::button :href="route('checkout.success', $order)">Lihat Detail Pesanan</x-email::button>

    <x-email::order-summary :order="$order" />
    <x-email::shipping :order="$order" heading="Dikirim Ke" />
@endsection
