@props(['label', 'value', 'icon' => 'chart-bar'])

<div class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-5 transition duration-200 hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
    <span class="absolute inset-y-0 left-0 w-1 bg-primary opacity-0 transition group-hover:opacity-100"></span>

    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-xs font-semibold text-zinc-600 dark:text-zinc-400">{{ $label }}</p>
            <p class="mt-2 font-mono text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-white">{{ $value }}</p>
        </div>
        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary dark:bg-primary/25 dark:text-white">
            <flux:icon :icon="$icon" class="size-6" />
        </span>
    </div>
</div>
