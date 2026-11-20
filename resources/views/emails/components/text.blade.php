@props(['size' => 'base'])
<p style="margin:0 0 16px; font-size:{{ $size === 'small' ? '13px' : '14px' }}; line-height:1.7; color:{{ $muted }};">
    {{ $slot }}
</p>
