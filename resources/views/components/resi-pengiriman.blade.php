@props(['order'])

@if ($order->tracking_number)
    <div {{ $attributes }}>
        <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-ink/60 dark:text-zinc-400">{{ __('Nomor Resi') }}</p>
        <div class="mt-1 flex items-center gap-1">
            <p class="min-w-0 break-all font-mono text-sm font-semibold text-ink dark:text-zinc-100">{{ $order->tracking_number }}</p>
            <x-copy-button :text="$order->tracking_number" :label="__('Salin nomor resi')" class="!size-10" />
        </div>
    </div>
@endif
