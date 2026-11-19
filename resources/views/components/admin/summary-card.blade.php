@props(['label', 'value', 'icon', 'tone'])

<div {{ $attributes->class('flex flex-col items-start gap-3 rounded-xl border border-gray-200 bg-white p-3.5 dark:border-zinc-800 dark:bg-zinc-900 sm:flex-row sm:items-center sm:gap-4 sm:p-5') }}>
    <span class="grid size-9 shrink-0 place-items-center rounded-lg sm:size-11 {{ $tone }}">
        <svg class="h-[18px] w-[18px] fill-none stroke-current stroke-2 sm:h-5 sm:w-5" aria-hidden="true"><use href="#i-{{ $icon }}"/></svg>
    </span>
    <div class="min-w-0">
        <p class="font-display text-xl font-bold leading-none tracking-tight text-brand-dark dark:text-zinc-100 sm:text-2xl">{{ $value }}</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">{{ $label }}</p>
    </div>
</div>
