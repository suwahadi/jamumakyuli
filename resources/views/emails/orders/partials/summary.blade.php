<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid {{ $line }}; border-radius:8px; border-collapse:separate; overflow:hidden;">
    <tr>
        <td style="background-color:{{ $creamDeep }}; padding:11px 16px; border-bottom:1px solid {{ $line }};">
            <span style="font-size:11px; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; color:{{ $muted }};">Rincian Pesanan</span>
            <span style="float:right; font-size:11px; color:{{ $muted }}; font-family:Arial,Helvetica,sans-serif; font-variant-numeric:tabular-nums;">{{ tanggal_id($order->created_at) }}</span>
        </td>
    </tr>

    @foreach ($order->items as $item)
        <tr>
            <td style="padding:12px 16px; border-bottom:1px solid {{ $line }};">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="font-size:14px; color:{{ $ink }};">
                            <span style="font-weight:600;">{{ $item->product->name ?? 'Produk' }}</span>
                            <br>
                            @if ($item->namaVarian())
                                <span style="font-size:12px; color:{{ $muted }};">Varian: {{ $item->namaVarian() }}</span><br>
                            @endif
                            @if ($item->custom_text)
                                <span style="font-size:12px; font-weight:600; color:{{ $accentDark }};">Catatan Custom: {{ $item->custom_text }}</span><br>
                            @endif
                            <span style="font-size:12px; color:{{ $muted }}; font-family:Arial,Helvetica,sans-serif; font-variant-numeric:tabular-nums;">{{ rupiah($item->price) }} &times; {{ $item->quantity }}</span>
                        </td>
                        <td align="right" style="font-size:14px; font-weight:700; color:{{ $ink }}; font-family:Arial,Helvetica,sans-serif; font-variant-numeric:tabular-nums; white-space:nowrap; vertical-align:top;">
                            {{ rupiah($item->total) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    @endforeach

    <tr>
        <td style="padding:14px 16px; background-color:{{ $creamDeep }};">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px; color:{{ $muted }};">
                <tr>
                    <td style="padding:2px 0;">Subtotal</td>
                    <td align="right" style="padding:2px 0; font-family:Arial,Helvetica,sans-serif; font-variant-numeric:tabular-nums; color:{{ $ink }};">{{ rupiah($order->subtotal) }}</td>
                </tr>
                <tr>
                    <td style="padding:2px 0;">Ongkos Kirim ({{ $order->shippingCourierName() }})</td>
                    <td align="right" style="padding:2px 0; font-family:Arial,Helvetica,sans-serif; font-variant-numeric:tabular-nums; color:{{ $ink }};">{{ rupiah($order->shipping_cost) }}</td>
                </tr>
                <tr>
                    <td style="padding:9px 0 0; border-top:1px solid {{ $line }}; font-size:16px; font-weight:800; color:{{ $ink }};">Total</td>
                    <td align="right" style="padding:9px 0 0; border-top:1px solid {{ $line }}; font-size:16px; font-weight:800; font-family:Arial,Helvetica,sans-serif; font-variant-numeric:tabular-nums; color:{{ $accent }};">{{ rupiah($order->grand_total) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
