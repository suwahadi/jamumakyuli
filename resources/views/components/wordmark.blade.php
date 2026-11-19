@props([
    'size' => 'text-xl',
    'accent' => 'text-brand-green',
])

<span {{ $attributes->class(['font-display font-bold leading-none tracking-tight whitespace-nowrap', $size]) }}>Jamu <span class="{{ $accent }}">Mak Yuli</span></span>
