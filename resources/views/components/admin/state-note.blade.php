@props(['icon', 'title', 'tone' => 'slate'])

@php
    $chip = match ($tone) {
        'sky' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400',
        'teal' => 'bg-teal-100 text-teal-600 dark:bg-teal-500/15 dark:text-teal-400',
        'rose' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400',
        default => 'bg-gray-200 text-gray-600 dark:bg-zinc-700 dark:text-zinc-300',
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-lg bg-gray-50 px-4 py-3.5 dark:bg-zinc-800/60']) }}>
    <span class="grid size-7 shrink-0 place-items-center rounded-full {{ $chip }}">
        <flux:icon :icon="$icon" class="size-4" />
    </span>
    <div class="min-w-0">
        <p class="text-sm font-semibold leading-tight text-brand-dark dark:text-zinc-100">{{ $title }}</p>
        @if (trim($slot) !== '')
            <p class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-zinc-400">{{ $slot }}</p>
        @endif
    </div>
</div>
