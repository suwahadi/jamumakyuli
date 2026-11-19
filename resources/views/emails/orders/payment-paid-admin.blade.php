@extends('emails.layout')

@section('eyebrow', 'Pembayaran Lunas')
@section('preheader', 'Pesanan '.$order->order_number.' telah lunas.')
@section('heading', 'Pembayaran Diterima')

@section('content')
    <p style="margin:0 0 16px; font-size:14px; line-height:1.7; color:{{ $muted }};">
        Pesanan <strong style="color:{{ $ink }};">{{ $order->order_number }}</strong> dari
        <strong style="color:{{ $ink }};">{{ $order->customer_name }}</strong> telah lunas dan siap diproses.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; background-color:{{ $successBg }}; border:1px solid {{ $successBorder }}; border-radius:8px;">
        <tr>
            <td style="padding:14px 16px;">
                <span style="font-size:12px; color:{{ $successInk }};">Total Dibayar</span><br>
                <span style="font-size:18px; font-weight:800; color:{{ $ink }}; font-family:Arial,Helvetica,sans-serif; font-variant-numeric:tabular-nums;">{{ rupiah($order->grand_total) }}</span>
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

    @include('emails.orders.partials.shipping', ['heading' => 'Kirim Ke'])

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 0;">
        <tr>
            <td style="background-color:{{ $dark }}; border-radius:8px;">
                <a href="{{ route('admin.orders.show', $order) }}" target="_blank"
                   style="display:inline-block; padding:13px 30px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none;">
                    Buka di Panel Admin
                </a>
            </td>
        </tr>
    </table>
@endsection
