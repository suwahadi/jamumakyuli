@props(['order'])

@if ($order->tracking_number)
    <div {{ $attributes }}>
        <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-ink/60 dark:text-zinc-400">{{ __('Nomor Resi') }}</p>
        <div class="mt-1 flex items-center gap-1">
            <p class="min-w-0 break-all font-mono text-sm font-semibold text-ink dark:text-zinc-100">{{ $order->tracking_number }}</p>
            <x-copy-button :text="$order->tracking_number" :label="__('Salin nomor resi')" class="!size-10" />
        </div>
        @if ($url = $order->trackingUrl())
            <a href="{{ $url }}" target="_blank" rel="noopener"
               class="inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-primary underline-offset-4 hover:underline">
                <flux:icon.arrow-top-right-on-square class="size-4" />
                {{ __('Lacak di situs :kurir', ['kurir' => \App\Services\ShippingService::COURIERS[$order->shipping_courier] ?? strtoupper((string) $order->shipping_courier)]) }}
            </a>
            <p class="text-xs leading-relaxed text-body-2 dark:text-zinc-400">{{ __('Salin nomor resi, lalu tempel di halaman lacak kurir.') }}</p>
        @endif
    </div>
@endif
