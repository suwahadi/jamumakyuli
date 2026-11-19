@extends('emails.layout')

@section('eyebrow', 'Pesanan Dikirim')
@section('preheader', 'Pesanan '.$order->order_number.' telah dikirim.')
@section('heading', 'Pesanan Anda Telah Dikirim')

@section('content')
    <p style="margin:0 0 16px; font-size:14px; line-height:1.7; color:{{ $muted }};">
        Halo <strong style="color:{{ $ink }};">{{ $order->customer_name }}</strong>, pesanan
        <strong style="color:{{ $ink }};">{{ $order->order_number }}</strong> sudah kami kirim.
        Simpan nomor di bawah untuk melacak paket Anda.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; background-color:{{ $infoBg }}; border:1px solid {{ $infoBorder }}; border-radius:8px;">
        <tr>
            <td style="padding:14px 16px;">
                <span style="font-size:12px; color:{{ $infoInk }};">Kurir</span><br>
                <span style="font-size:15px; font-weight:700; color:{{ $ink }};">{{ $order->shippingCourierName() }}</span>
                @if ($order->tracking_number)
                    <br><span style="font-size:12px; color:{{ $infoInkSoft }};">No. Resi / Keterangan</span><br>
                    <span style="font-size:18px; font-weight:800; letter-spacing:0.05em; color:{{ $ink }}; font-family:Arial,Helvetica,sans-serif; font-variant-numeric:tabular-nums;">{{ $order->tracking_number }}</span>
                    @if ($url = $order->trackingUrl())
                        <br><a href="{{ $url }}" target="_blank" style="display:inline-block; margin-top:8px; font-size:13px; font-weight:700; color:{{ $accent }};">Lacak di situs {{ \App\Services\ShippingService::COURIERS[$order->shipping_courier] ?? strtoupper((string) $order->shipping_courier) }}</a>
                        <br><span style="font-size:12px; color:{{ $infoInkSoft }};">Salin nomor resi di atas, lalu tempel di halaman lacak kurir.</span>
                    @endif
                @endif
            </td>
        </tr>
    </table>

    @include('emails.orders.partials.summary')

    <div style="height:16px;"></div>

    @include('emails.orders.partials.shipping', ['heading' => 'Dikirim Ke'])

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
