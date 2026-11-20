@extends('emails.layout')

@section('eyebrow', 'Pesanan Dikirim')
@section('preheader', 'Pesanan '.$order->order_number.' telah dikirim.')
@section('heading', 'Pesanan Anda Telah Dikirim')

@section('content')
    <x-email::text>
        Halo <x-email::strong>{{ $order->customer_name }}</x-email::strong>, pesanan
        <x-email::strong>{{ $order->order_number }}</x-email::strong> sudah kami kirim.
        Simpan nomor di bawah untuk melacak paket Anda.
    </x-email::text>

    <x-email::callout tone="info" label="Kurir" :value="$order->shippingCourierName()" badge="DIKIRIM" />

    @if ($order->tracking_number)
        <x-email::callout tone="info" label="No. Resi / Keterangan" :value="$order->tracking_number"
                          :link="$order->trackingUrl()"
                          :link-label="'Lacak di situs '.(\App\Services\ShippingService::COURIERS[$order->shipping_courier] ?? strtoupper((string) $order->shipping_courier))"
                          :note="$order->trackingUrl() ? 'Salin nomor resi di atas, lalu tempel di halaman lacak kurir.' : null" />
    @endif

    <x-email::button :href="route('checkout.success', $order)">Lihat Detail Pesanan</x-email::button>

    <x-email::order-summary :order="$order" />
    <x-email::shipping :order="$order" heading="Dikirim Ke" />
@endsection
