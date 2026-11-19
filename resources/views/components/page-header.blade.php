@props([
    'title' => null,
    'items' => [],
    'padding' => 'py-7 sm:py-9',
])

<section {{ $attributes->class('relative overflow-hidden bg-brand-green') }}>
    <div class="grid-tex absolute inset-0 opacity-60"></div>
    <div class="container-x relative {{ $padding }}">
        @if ($items)
            <x-breadcrumb :items="$items" class="text-[13px] text-white/65 [&_a:hover]:text-white [&_span]:text-white/90" />
        @endif
        @if ($title)
            <h1 class="mt-2 font-display text-[22px] font-bold tracking-tight text-white sm:text-[26px]">{{ $title }}</h1>
        @endif
    </div>
</section>
