@extends('emails.layout')

@section('eyebrow', 'Peringatan Pembayaran')
@section('preheader', 'Perlu review pembayaran pesanan '.$order->order_number.', '.rupiah($order->grand_total).'.')
@section('heading', 'Perlu Review Pembayaran')

@section('content')
    <x-email::text>
        Sistem mendeteksi kondisi pembayaran yang perlu ditinjau manual pada pesanan
        <x-email::strong>{{ $order->order_number }}</x-email::strong>.
    </x-email::text>

    <x-email::callout tone="danger" label="Nomor Pesanan" :value="$order->order_number" :note="$reason" badge="REVIEW" />
    <x-email::callout tone="neutral" label="Total Pesanan" :value="rupiah($order->grand_total)" />

    <x-email::button :href="route('admin.orders.show', $order)" variant="admin">Buka di Panel Admin</x-email::button>
@endsection
