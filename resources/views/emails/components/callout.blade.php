@props(['tone' => 'neutral', 'label', 'value' => null, 'size' => 'lg', 'link' => null, 'linkLabel' => null, 'note' => null, 'badge' => null])
@php($warna = $tones[$tone])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px; background-color:{{ $warna['bg'] }}; border:1px solid {{ $warna['border'] }}; border-radius:8px;">
    <tr>
        <td style="padding:14px 16px; vertical-align:top;">
            <span style="font-size:12px; line-height:1.6; font-weight:600; color:{{ $warna['label'] }};">{{ $label }}</span>
            @if (filled($value))
                <br><span style="font-size:{{ $size === 'xl' ? '26px' : '18px' }}; line-height:1.4; font-weight:800; letter-spacing:0.02em; color:{{ $ink }}; font-variant-numeric:tabular-nums;">{{ $value }}</span>
            @endif
            @if ($link)
                <br><a href="{{ $link }}" target="_blank" style="display:inline-block; margin-top:6px; font-size:13px; font-weight:700; color:{{ $accentDark }};">{{ $linkLabel ?? $link }}</a>
            @endif
            @if (filled($note))
                <br><span style="font-size:12px; line-height:1.6; color:{{ $warna['note'] }};">{{ $note }}</span>
            @endif
        </td>
        @if ($badge)
            <td align="right" style="padding:14px 16px; vertical-align:top;">
                <span style="display:inline-block; background-color:{{ $warna['badgeBg'] }}; color:{{ $warna['badgeText'] }}; font-size:11px; font-weight:700; letter-spacing:0.02em; padding:5px 12px; border-radius:999px; white-space:nowrap;">{{ $badge }}</span>
            </td>
        @endif
    </tr>
</table>
