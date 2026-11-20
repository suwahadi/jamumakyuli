@props(['href', 'variant' => 'customer'])
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:4px 0 12px;">
    <tr>
        <td style="background-color:{{ $variant === 'admin' ? $dark : $accent }}; border-radius:8px;">
            <a href="{{ $href }}" target="_blank"
               style="display:inline-block; padding:13px 30px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none;">
                {{ $slot }}
            </a>
        </td>
    </tr>
</table>
<p style="margin:0 0 24px; font-size:12px; line-height:1.6; color:{{ $muted }};">
    Jika tombol di atas tidak berfungsi, salin tautan berikut ke peramban Anda:<br>
    <a href="{{ $href }}" style="color:{{ $accentDark }}; word-break:break-all;">{{ $href }}</a>
</p>
