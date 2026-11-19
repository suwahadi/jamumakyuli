@extends('emails.layout')

@section('eyebrow', 'Peringatan Pembayaran')
@section('preheader', 'Perlu review pembayaran pesanan '.$order->order_number.', '.rupiah($order->grand_total).'.')
@section('heading', 'Perlu Review Pembayaran')

@section('content')
    <p style="margin:0 0 16px; font-size:14px; line-height:1.7; color:{{ $muted }};">
        Sistem mendeteksi kondisi pembayaran yang perlu ditinjau manual pada pesanan
        <strong style="color:{{ $ink }};">{{ $order->order_number }}</strong>.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; background-color:{{ $dangerBg }}; border:1px solid {{ $dangerBorder }}; border-radius:8px;">
        <tr>
            <td style="padding:14px 16px;">
                <span style="font-size:12px; color:{{ $dangerInk }};">Nomor Pesanan</span><br>
                <span style="font-size:18px; font-weight:800; letter-spacing:0.05em; color:{{ $ink }}; font-family:Arial,Helvetica,sans-serif; font-variant-numeric:tabular-nums;">{{ $order->order_number }}</span>
                <br><span style="font-size:12px; color:{{ $dangerInkSoft }};">{{ $reason }}</span>
            </td>
            <td align="right" style="padding:14px 16px; vertical-align:top;">
                <span style="display:inline-block; background-color:{{ $danger }}; color:#ffffff; font-size:11px; font-weight:700; padding:5px 12px; border-radius:999px;">REVIEW</span>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; border:1px solid {{ $line }}; border-radius:8px;">
        <tr>
            <td style="padding:12px 16px;">
                <span style="font-size:12px; color:{{ $muted }};">Total Pesanan</span><br>
                <span style="font-size:16px; font-weight:800; color:{{ $ink }}; font-family:Arial,Helvetica,sans-serif; font-variant-numeric:tabular-nums;">{{ rupiah($order->grand_total) }}</span>
            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 0;">
        <tr>
            <td style="background-color:{{ $ink }}; border-radius:8px;">
                <a href="{{ route('admin.orders.show', $order) }}" target="_blank"
                   style="display:inline-block; padding:13px 30px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none;">
                    Buka di Panel Admin
                </a>
            </td>
        </tr>
    </table>
@endsection
