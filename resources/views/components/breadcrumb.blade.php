@props(['items' => []])

<nav aria-label="Breadcrumb" {{ $attributes->class('flex items-center text-[14px] text-body-2') }}>
    <ol class="flex flex-wrap items-center gap-2">
        @foreach ($items as $item)
            <li>
                @if (! empty($item['url']) && ! $loop->last)
                    <a href="{{ $item['url'] }}" wire:navigate class="inline-flex min-h-6 items-center hover:text-primary hover:underline underline-offset-4">{{ $item['label'] }}</a>
                @else
                    <span @class(['font-bold' => $loop->last]) @if ($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
                @endif
            </li>
            @unless ($loop->last)
                <li aria-hidden="true">/</li>
            @endunless
        @endforeach
    </ol>
</nav>
