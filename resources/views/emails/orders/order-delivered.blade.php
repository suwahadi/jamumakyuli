@extends('emails.layout')

@php
    $namaSitus = setting('site_name', config('services.brevo.sender_name', 'Jamu Mak Yuli'));
    $whatsapp = trim((string) setting('site_whatsapp', ''));
    $waLink = wa_href('Halo '.$namaSitus.', saya ingin bertanya tentang pesanan '.$order->order_number.'.');
@endphp

@section('eyebrow', 'Pesanan Selesai')
@section('preheader', 'Pesanan '.$order->order_number.' sudah selesai. Terima kasih sudah berbelanja di '.$namaSitus.'.')
@section('heading', 'Pesanan Anda Telah Selesai')

@section('content')
    <p style="margin:0 0 16px; font-size:14px; line-height:1.7; color:{{ $muted }};">
        Halo <strong style="color:{{ $ink }};">{{ $order->customer_name }}</strong>, pesanan
        <strong style="color:{{ $ink }};">{{ $order->order_number }}</strong> sudah selesai.
        Terima kasih sudah berbelanja di <strong style="color:{{ $ink }};">{{ $namaSitus }}</strong>.
    </p>

    @if ($whatsapp !== '' && $waLink)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; background-color:{{ $successBg }}; border:1px solid {{ $successBorder }}; border-radius:8px;">
            <tr>
                <td style="padding:14px 16px;">
                    <span style="font-size:13px; line-height:1.6; color:{{ $successInk }};">Butuh bantuan terkait pesanan atau ada pertanyaan tentang produk? Hubungi kami lewat WhatsApp:</span><br>
                    <a href="{{ $waLink }}" target="_blank" style="display:inline-block; margin-top:6px; font-size:18px; font-weight:800; letter-spacing:0.03em; color:{{ $ink }}; text-decoration:none; font-variant-numeric:tabular-nums;">{{ $whatsapp }}</a>
                </td>
            </tr>
        </table>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 0;">
        <tr>
            <td style="background-color:{{ $accent }}; border-radius:8px;">
                <a href="{{ route('checkout.success', $order) }}" target="_blank"
                   style="display:inline-block; padding:13px 30px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none;">
                    Lihat Detail Pesanan
                </a>
            </td>
        </tr>
    </table>
@endsection
