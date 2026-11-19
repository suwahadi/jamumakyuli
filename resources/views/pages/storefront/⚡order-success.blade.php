<?php

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\Payments\Midtrans\MidtransPaymentAttemptService;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::storefront')] class extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order->load('items.product', 'items.variant', 'activePaymentAttempt');
        $this->syncPayment();
    }

    public function rendering(View $view): void
    {
        $view->title($this->statusTitle());
    }

    public function statusTitle(): string
    {
        return match ($this->order->status) {
            OrderStatus::PAID => __('Pembayaran Berhasil'),
            OrderStatus::SHIPPED => __('Pesanan Sedang Dikirim'),
            OrderStatus::DELIVERED => __('Pesanan Diterima'),
            OrderStatus::CANCELLED => __('Pesanan Dibatalkan'),
            OrderStatus::PENDING => __('Selesaikan Pembayaran'),
        };
    }

    public function syncPayment(): void
    {
        rescue(fn () => app(MidtransPaymentAttemptService::class)->syncActiveAttempt($this->order), report: false);

        $this->order->refresh()->load('items.product', 'items.variant', 'activePaymentAttempt');
    }

    public function retryPayment(): void
    {
        try {
            $attempt = app(MidtransPaymentAttemptService::class)->createOrReuseActiveAttempt($this->order);
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->order->refresh()->load('items.product', 'items.variant', 'activePaymentAttempt');

        if (blank($attempt->redirect_url)) {
            Flux::toast(variant: 'danger', text: __('Gagal memulai pembayaran. Silakan coba lagi.'));

            return;
        }

        $this->redirect($attempt->redirect_url);
    }
}; ?>

<div>
@php
    $isPaid = $order->status === \App\Enums\OrderStatus::PAID;
    $isPending = $order->status === \App\Enums\OrderStatus::PENDING;
@endphp

<x-page-header :title="$this->statusTitle()" :items="[
    ['label' => __('Beranda'), 'url' => route('home')],
    ['label' => __('Pesanan')],
]" />

<x-container class="py-8 pb-24 lg:py-12 lg:pb-12">

    <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:items-center sm:gap-5 sm:text-left">
        <div class="flex-1">
            <p class="text-sm text-ink/55 dark:text-zinc-400">{{ __('Terima kasih, :name. Pesanan Anda telah kami terima.', ['name' => $order->customer_name]) }}</p>
        </div>

        <div class="flex shrink-0 items-center gap-3" x-data="{ copied: false, t: null }">
            <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-ink/60 dark:text-zinc-400">{{ __('Nomor Pesanan') }}</span>
            <button type="button"
                    @click="
                        navigator.clipboard?.writeText(@js($order->order_number));
                        copied = true; clearTimeout(t); t = setTimeout(() => copied = false, 2000);
                    "
                    class="group inline-flex min-h-11 cursor-pointer items-center gap-2.5 rounded-lg border border-primary/20 bg-brand-pink-soft py-1.5 pl-4 pr-2.5 transition hover:border-primary/40 dark:bg-brand-green/10"
                    :title="copied ? @js(__('Tersalin')) : @js(__('Klik untuk menyalin'))">
                <span class="font-mono text-sm font-bold tracking-wider text-primary">{{ $order->order_number }}</span>
                <span class="h-3.5 w-px bg-primary/20"></span>
                <span class="inline-flex items-center gap-1 text-xs font-medium transition-colors"
                      :class="copied ? 'text-primary' : 'text-primary/70 group-hover:text-primary'">
                    <span x-show="!copied"><flux:icon.clipboard-document class="size-3.5" /></span>
                    <span x-show="copied" x-cloak><flux:icon.check class="size-3.5" /></span>
                    <span x-text="copied ? @js(__('Tersalin')) : @js(__('Salin'))"></span>
                </span>
            </button>
        </div>
    </div>

    @if ($order->status !== \App\Enums\OrderStatus::CANCELLED && ($waKonfirmasi = wa_href(\App\Support\WhatsAppMessage::orderConfirmation($order))))
        <div class="mt-6">
            <a href="{{ $waKonfirmasi }}" target="_blank" rel="noopener"
               class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-md bg-[#25D366] px-6 font-display text-sm font-bold tracking-wide text-white transition hover:brightness-95 sm:w-auto">
                <svg class="h-[18px] w-[18px] fill-current" aria-hidden="true"><use href="#i-wa"/></svg>
                {{ __('Konfirmasi via WhatsApp') }}
            </a>
        </div>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_350px] lg:items-start">
        <div class="space-y-6">
            @if ($isPaid)
                <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-5 dark:border-emerald-500/25 dark:bg-emerald-500/10 sm:p-6">
                    <div class="flex items-center gap-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white dark:bg-emerald-500">
                            <flux:icon.check class="size-6" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-emerald-700 dark:text-emerald-400">{{ $order->status->label() }}</p>
                            @if ($order->paid_at)
                                <p class="mt-1 text-base font-semibold text-ink dark:text-zinc-100">{{ __('Pembayaran diterima :waktu', ['waktu' => tanggal_id($order->paid_at)]) }}</p>
                            @else
                                <p class="mt-1 text-base font-semibold text-ink dark:text-zinc-100">{{ __('Pembayaran Anda telah kami terima.') }}</p>
                            @endif
                            <p class="mt-1 text-sm leading-relaxed text-ink/65 dark:text-zinc-400">{{ __('Pesanan sedang kami siapkan untuk pengiriman.') }}</p>
                        </div>
                    </div>
                </div>
            @elseif (in_array($order->status, [\App\Enums\OrderStatus::SHIPPED, \App\Enums\OrderStatus::DELIVERED], true))
                @php $sampai = $order->status === \App\Enums\OrderStatus::DELIVERED; @endphp
                <div class="rounded-lg border border-sky-200 bg-sky-50/70 p-5 dark:border-sky-500/25 dark:bg-sky-500/10 sm:p-6">
                    <div class="flex items-start gap-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-sky-600 text-white dark:bg-sky-500">
                            @if ($sampai)
                                <flux:icon.check-badge class="size-6" />
                            @else
                                <flux:icon.truck class="size-6" />
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-sky-800 dark:text-sky-300">{{ $order->status->label() }}</p>
                            <p class="mt-1 text-base font-semibold text-ink dark:text-zinc-100">{{ $sampai ? __('Pesanan sudah sampai. Terima kasih telah berbelanja.') : __('Pesanan Anda dalam perjalanan.') }}</p>
                            <x-resi-pengiriman :order="$order" class="mt-3" />
                        </div>
                    </div>
                </div>
            @elseif ($order->status === \App\Enums\OrderStatus::CANCELLED)
                <div class="rounded-lg border border-line bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
                    <div class="flex items-center gap-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-zinc-500 text-white dark:bg-zinc-600">
                            <flux:icon.x-mark class="size-6" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-ink/60 dark:text-zinc-400">{{ $order->status->label() }}</p>
                            <p class="mt-1 text-base font-semibold text-ink dark:text-zinc-100">{{ __('Pesanan ini dibatalkan.') }}</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="space-y-6" wire:poll.30s="syncPayment">
                    <div class="rounded-lg border border-amber-200 bg-amber-50/70 p-5 dark:border-amber-500/25 dark:bg-amber-500/10 sm:p-6">
                        <div class="flex items-center gap-4">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-amber-500 text-white">
                                <flux:icon.clock class="size-6" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-amber-700 dark:text-amber-400">{{ $order->status->label() }}</p>
                                <p class="mt-1.5 text-[11px] font-bold uppercase tracking-[0.14em] text-ink/60 dark:text-zinc-400">{{ __('Total Pembayaran') }}</p>
                                <p class="notranslate text-price text-3xl text-ink dark:text-zinc-100 sm:text-4xl">{{ rupiah($order->grand_total) }}</p>
                            </div>
                        </div>
                    </div>

                    @php $attempt = $order->activePaymentAttempt; @endphp
                    @if ($isPending && $attempt?->status?->isOpen() && $attempt->redirect_url)
                        <div class="rounded-lg border border-line bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-ink dark:text-zinc-100">{{ __('Selesaikan Pembayaran') }}</h2>
                            <p class="mt-1 text-sm text-body-2 dark:text-zinc-300">{{ __('Anda akan diarahkan ke halaman pembayaran Midtrans yang aman.') }}</p>
                            <div class="mt-4 grid grid-cols-2 gap-3">
                                <x-btn variant="primary" :href="$attempt->redirect_url" class="w-full">{{ __('Bayar Sekarang') }}</x-btn>
                                <x-btn variant="outline" wire:click="syncPayment" wire:loading.attr="disabled" wire:target="syncPayment" class="w-full">
                                    <span wire:loading.remove wire:target="syncPayment">{{ __('Cek Status') }}</span>
                                    <span wire:loading wire:target="syncPayment">{{ __('Memproses...') }}</span>
                                </x-btn>
                            </div>
                        </div>
                    @elseif ($isPending)
                        <div class="rounded-lg border border-line bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-ink dark:text-zinc-100">{{ __('Pembayaran Belum Selesai') }}</h2>
                            <p class="mt-1 text-sm text-body-2 dark:text-zinc-300">{{ __('Halaman pembayaran belum terbuka atau sesinya sudah berakhir. Tekan Bayar Ulang untuk melanjutkan.') }}</p>
                            <div class="mt-4 grid grid-cols-2 gap-3">
                                <x-btn variant="primary" wire:click="retryPayment" wire:loading.attr="disabled" wire:target="retryPayment" class="w-full">
                                    <span wire:loading.remove wire:target="retryPayment">{{ __('Bayar Ulang') }}</span>
                                    <span wire:loading wire:target="retryPayment">{{ __('Memproses...') }}</span>
                                </x-btn>
                                <x-btn variant="outline" wire:click="syncPayment" wire:loading.attr="disabled" wire:target="syncPayment" class="w-full">
                                    <span wire:loading.remove wire:target="syncPayment">{{ __('Cek Status') }}</span>
                                    <span wire:loading wire:target="syncPayment">{{ __('Memproses...') }}</span>
                                </x-btn>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="rounded-lg border border-line bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-line px-5 py-3.5 dark:border-zinc-800 sm:px-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-ink dark:text-zinc-100">{{ __('Informasi Pengiriman') }}</h2>
                    <p class="mt-0.5 text-xs text-ink/45 dark:text-zinc-400">{{ __('Detail penerima dan jasa kirim pesanan ini.') }}</p>
                </div>

                <div class="grid gap-x-6 gap-y-5 px-5 py-5 sm:grid-cols-2 sm:px-6">
                    <div class="sm:col-span-2">
                        <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-ink/60 dark:text-zinc-400">{{ __('Penerima') }}</p>
                        <p class="mt-1.5 text-sm font-medium text-ink dark:text-zinc-100">{{ $order->customer_name }}</p>
                    </div>

                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-ink/60 dark:text-zinc-400">{{ __('Email') }}</p>
                        <p class="mt-1.5 break-words text-sm text-ink/75 dark:text-zinc-300">{{ $order->customer_email }}</p>
                    </div>

                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-ink/60 dark:text-zinc-400">{{ __('Nomor Telepon') }}</p>
                        <p class="mt-1.5 text-sm text-ink/75 dark:text-zinc-300">{{ $order->customer_phone }}</p>
                    </div>

                    <div class="sm:col-span-2 border-t border-dashed border-line pt-5 dark:border-zinc-800">
                        <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-ink/60 dark:text-zinc-400">{{ __('Alamat Pengiriman') }}</p>
                        @if ($order->shipping_destination_label)
                            <p class="mt-1.5 text-sm font-medium text-ink dark:text-zinc-100">{{ $order->shipping_destination_label }}</p>
                        @endif
                        <p class="mt-1 text-sm leading-relaxed text-ink/70 dark:text-zinc-300">{{ $order->shipping_address }}</p>
                    </div>
                </div>
            </div>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-20">
            <div class="overflow-hidden rounded-lg border border-line bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-line px-5 py-3 dark:border-zinc-800">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-ink dark:text-zinc-100">{{ __('Rincian Pesanan') }}</h2>
                    <p class="mt-0.5 text-xs text-ink/45 dark:text-zinc-400">{{ tanggal_id($order->created_at) }}</p>
                </div>
                <div class="divide-y divide-line px-5 dark:divide-zinc-800">
                    @foreach ($order->items as $item)
                        <div class="flex items-start justify-between gap-3 py-3 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium text-ink dark:text-zinc-100">{{ $item->product->name ?? 'Produk' }}</p>
                                @if ($item->namaVarian())
                                    <p class="text-xs text-ink/55 dark:text-zinc-400">{{ __('Varian:') }} {{ $item->namaVarian() }}</p>
                                @endif
                                @if ($item->custom_text)
                                    <p class="text-xs font-medium text-brand-green">{{ __('Catatan Custom:') }} {{ $item->custom_text }}</p>
                                @endif
                                <p class="text-xs text-ink/45 dark:text-zinc-400"><span class="notranslate">{{ rupiah($item->price) }}</span> × {{ $item->quantity }}</p>
                            </div>
                            <span class="notranslate shrink-0 font-semibold text-ink dark:text-zinc-100">{{ rupiah($item->total) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="space-y-2.5 border-t border-line bg-white px-5 py-4 text-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex justify-between">
                        <span class="text-ink/60 dark:text-zinc-400">{{ __('Subtotal') }}</span>
                        <span class="notranslate font-medium text-ink dark:text-zinc-100">{{ rupiah($order->subtotal) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink/60 dark:text-zinc-400">{{ __('Ongkos Kirim') }} <span class="text-ink/60 dark:text-zinc-400">({{ $order->shippingCourierName() }})</span></span>
                        <span class="notranslate font-medium text-ink dark:text-zinc-100">{{ rupiah($order->shipping_cost) }}</span>
                    </div>
                    <div class="mt-1 flex items-center justify-between border-t border-line pt-3 dark:border-zinc-800">
                        <span class="text-base font-bold text-ink dark:text-zinc-100">{{ __('Total') }}</span>
                        <span class="notranslate text-price text-xl text-brand-green">{{ rupiah($order->grand_total) }}</span>
                    </div>
                </div>
            </div>

            @if ($order->status !== \App\Enums\OrderStatus::CANCELLED)
                <x-btn variant="dark" :href="route('orders.invoice', $order)" target="_blank" class="w-full">
                    {{ __('Lihat Invoice') }}
                </x-btn>
            @endif

            <x-btn variant="outline" :href="route('products.index')" wire:navigate class="w-full">
                {{ __('Lanjut Belanja') }}
            </x-btn>
        </aside>
    </div>
</x-container>
</div>
