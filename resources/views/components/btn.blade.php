@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
])

@php
    $base = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-md px-5 py-2 text-[13px] font-bold leading-none tracking-wide transition-colors cursor-pointer disabled:cursor-not-allowed disabled:opacity-60';

    $variants = [
        'primary' => 'bg-brand-green text-white hover:bg-brand-green-dark',
        'outline' => 'border-2 border-brand-green text-brand-green hover:bg-brand-green hover:text-white',
        'dark' => 'border-2 border-brand-dark text-brand-dark hover:bg-brand-dark hover:text-white dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700 dark:hover:text-white',
        'ghost' => 'text-body-2 hover:bg-black/5 hover:text-ink dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
