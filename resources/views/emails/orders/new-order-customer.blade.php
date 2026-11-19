@extends('emails.layout')

@section('eyebrow', 'Pesanan Anda')
@section('preheader', 'Pesanan '.$order->order_number.' telah kami terima. Selesaikan pembayaran '.rupiah($order->grand_total).'.')
@section('heading', 'Terima kasih, '.$order->customer_name.'!')

@section('content')
    <p style="margin:0 0 16px; font-size:14px; line-height:1.7; color:{{ $muted }};">
        Pesanan Anda telah kami terima dan saat ini <strong style="color:{{ $ink }};">menunggu pembayaran</strong>.
        Selesaikan pembayaran agar pesanan Anda segera kami proses.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px; background-color:{{ $warningBg }}; border:1px solid {{ $warningBorder }}; border-radius:8px;">
        <tr>
            <td style="padding:14px 16px;">
                <span style="font-size:12px; color:{{ $warningInk }};">Nomor Pesanan</span><br>
                <span style="font-size:18px; font-weight:800; letter-spacing:0.05em; color:{{ $ink }}; font-family:Arial,Helvetica,sans-serif; font-variant-numeric:tabular-nums;">{{ $order->order_number }}</span>
            </td>
            <td align="right" style="padding:14px 16px;">
                <span style="display:inline-block; background-color:{{ $warning }}; color:{{ $ink }}; font-size:11px; font-weight:700; padding:5px 12px; border-radius:999px;">Menunggu Pembayaran</span>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px; background-color:{{ $creamDeep }}; border:1px solid {{ $accent }}; border-radius:8px;">
        <tr>
            <td style="padding:16px;">
                <span style="font-size:12px; font-weight:600; color:{{ $accentDark }};">Total yang harus dibayar</span><br>
                <span style="font-size:26px; font-weight:800; letter-spacing:-0.01em; color:{{ $ink }}; font-family:Arial,Helvetica,sans-serif; font-variant-numeric:tabular-nums;">{{ rupiah($order->grand_total) }}</span>
            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
        <tr>
            <td style="background-color:{{ $accent }}; border-radius:8px;">
                <a href="{{ route('checkout.success', $order) }}" target="_blank"
                   style="display:inline-block; padding:13px 30px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none;">
                    Bayar Sekarang
                </a>
            </td>
        </tr>
    </table>

    @include('emails.orders.partials.summary')

    <div style="height:16px;"></div>

    @include('emails.orders.partials.shipping', ['heading' => 'Dikirim Ke'])

    <p style="margin:20px 0 0; font-size:13px; line-height:1.7; color:{{ $muted }};">
        Jika tombol di atas tidak berfungsi, salin tautan berikut ke peramban Anda:<br>
        <a href="{{ route('checkout.success', $order) }}" style="color:{{ $accentDark }}; word-break:break-all;">{{ route('checkout.success', $order) }}</a>
    </p>
@endsection
