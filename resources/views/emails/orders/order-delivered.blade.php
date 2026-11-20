@extends('emails.layout')

@section('eyebrow', 'Pesanan Selesai')
@section('preheader', 'Pesanan '.$order->order_number.' sudah selesai. Terima kasih sudah berbelanja di '.$siteName.'.')
@section('heading', 'Pesanan Anda Telah Selesai')

@section('content')
    <x-email::text>
        Halo <x-email::strong>{{ $order->customer_name }}</x-email::strong>, pesanan
        <x-email::strong>{{ $order->order_number }}</x-email::strong> sudah selesai.
        Terima kasih sudah berbelanja di <x-email::strong>{{ $siteName }}</x-email::strong>.
    </x-email::text>

    <x-email::callout tone="success" label="Nomor Pesanan" :value="$order->order_number" badge="SELESAI" />

    @if (filled(setting('site_whatsapp')) && ($waLink = wa_href('Halo '.$siteName.', saya ingin bertanya tentang pesanan '.$order->order_number.'.')))
        <x-email::callout tone="brand" label="Butuh bantuan terkait pesanan atau ada pertanyaan tentang produk? Hubungi kami lewat WhatsApp:"
                          :value="trim((string) setting('site_whatsapp'))" :link="$waLink" link-label="Chat via WhatsApp" />
    @endif

    <x-email::button :href="route('checkout.success', $order)">Lihat Detail Pesanan</x-email::button>
@endsection
