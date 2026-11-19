@props(['title', 'description' => null, 'danger' => false])

<section {{ $attributes->class([
    'rounded-2xl border bg-white dark:bg-zinc-900',
    'border-rose-200 dark:border-rose-500/30' => $danger,
    'border-zinc-200 dark:border-zinc-800' => ! $danger,
]) }}>
    <div @class([
        'border-b px-5 py-4 sm:px-6',
        'border-rose-100 dark:border-rose-500/20' => $danger,
        'border-zinc-100 dark:border-zinc-800' => ! $danger,
    ])>
        <h2 @class([
            'font-display text-base font-semibold tracking-tight',
            'text-rose-700 dark:text-rose-400' => $danger,
            'text-brand-dark dark:text-zinc-100' => ! $danger,
        ])>{{ $title }}</h2>
        @if ($description)
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
        @endif
    </div>

    <div class="p-5 sm:p-6">
        {{ $slot }}
    </div>
</section>
