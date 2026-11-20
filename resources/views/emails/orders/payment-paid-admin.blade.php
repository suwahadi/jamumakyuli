@extends('emails.layout')

@section('eyebrow', 'Pembayaran Lunas')
@section('preheader', 'Pesanan '.$order->order_number.' telah lunas.')
@section('heading', 'Pembayaran Diterima')

@section('content')
    <x-email::text>
        Pesanan <x-email::strong>{{ $order->order_number }}</x-email::strong> dari
        <x-email::strong>{{ $order->customer_name }}</x-email::strong> telah lunas dan siap diproses.
    </x-email::text>

    <x-email::callout tone="success" label="Total Dibayar" :value="rupiah($order->grand_total)" badge="LUNAS"
                      :note="$order->paid_at ? 'Lunas pada '.tanggal_id($order->paid_at) : null" />

    <x-email::button :href="route('admin.orders.show', $order)" variant="admin">Buka di Panel Admin</x-email::button>

    <x-email::order-summary :order="$order" />
    <x-email::shipping :order="$order" heading="Kirim Ke" />
@endsection
