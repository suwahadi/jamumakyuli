@extends('emails.layout')

@section('eyebrow', 'Pembayaran Lunas')
@section('preheader', 'Pembayaran pesanan '.$order->order_number.' telah lunas. Pesanan sedang disiapkan.')
@section('heading', 'Pembayaran Berhasil Diterima')

@section('content')
    <p style="margin:0 0 16px; font-size:14px; line-height:1.7; color:{{ $muted }};">
        Terima kasih, <strong style="color:{{ $ink }};">{{ $order->customer_name }}</strong>. Pembayaran untuk pesanan
        <strong style="color:{{ $ink }};">{{ $order->order_number }}</strong> telah kami terima secara penuh.
        Produk Anda sedang dipersiapkan untuk pengiriman.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; background-color:{{ $successBg }}; border:1px solid {{ $successBorder }}; border-radius:8px;">
        <tr>
            <td style="padding:14px 16px;">
                <span style="font-size:12px; color:{{ $successInk }};">Nomor Pesanan</span><br>
                <span style="font-size:18px; font-weight:800; letter-spacing:0.05em; color:{{ $ink }}; font-family:Arial,Helvetica,sans-serif; font-variant-numeric:tabular-nums;">{{ $order->order_number }}</span>
                @if ($order->paid_at)
                    <br><span style="font-size:12px; color:{{ $successInkSoft }};">Lunas pada {{ tanggal_id($order->paid_at) }}</span>
                @endif
            </td>
            <td align="right" style="padding:14px 16px;">
                <span style="display:inline-block; background-color:{{ $success }}; color:#ffffff; font-size:11px; font-weight:700; padding:5px 12px; border-radius:999px;">LUNAS</span>
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
