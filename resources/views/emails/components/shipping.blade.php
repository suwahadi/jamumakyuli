@props(['order', 'heading' => 'Alamat Pengiriman'])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px; border:1px solid {{ $line }}; border-radius:8px;">
    <tr>
        <td style="padding:14px 16px;">
            <p style="margin:0 0 10px; font-size:11px; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; color:{{ $muted }};">{{ $heading }}</p>

            <p style="margin:0; font-size:14px; line-height:1.6; color:{{ $ink }};">
                <span style="font-weight:600;">{{ $order->customer_name }}</span><br>
                @if ($order->customer_phone){{ $order->customer_phone }}<br>@endif
                <span style="color:{{ $muted }};">{{ $order->customer_email }}</span>
            </p>

            <p style="margin:10px 0 0; font-size:14px; line-height:1.6; color:{{ $ink }};">
                {{ $order->shipping_address }}<br>
                <span style="color:{{ $muted }};">{{ $order->shipping_destination_label }}</span><br>
                <span style="font-size:12px; color:{{ $muted }};">Kurir: <strong style="color:{{ $ink }};">{{ $order->shippingCourierName() }}</strong></span>
            </p>
        </td>
    </tr>
</table>
