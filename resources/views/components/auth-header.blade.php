@props([
    'title',
    'description' => null,
])

<div class="flex w-full flex-col">
    <h1 class="text-heading text-xl text-ink dark:text-zinc-100">{{ $title }}</h1>
    @if ($description)
        <p class="mt-1 text-sm text-body-2 dark:text-zinc-400">{{ $description }}</p>
    @endif
</div>
