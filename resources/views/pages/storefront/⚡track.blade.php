<?php

use App\Models\Order;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::storefront')] class extends Component
{
    public string $order_number = '';

    public ?string $track_error = null;

    public function rendering(View $view): void
    {
        $view->title(__('Lacak Pesanan'));
    }

    public function track(): void
    {
        $number = mb_substr(strtoupper(trim($this->order_number)), 0, 100);

        $this->order_number = $number;
        $this->track_error = null;

        if ($number === '') {
            $this->track_error = __('Masukkan nomor pesanan terlebih dahulu.');

            return;
        }

        $order = Order::query()
            ->where('order_number', $number)
            ->first();

        if ($order === null) {
            $this->track_error = __('Pesanan tidak ditemukan. Periksa kembali nomor pesanan Anda.');

            return;
        }

        $this->redirectRoute('checkout.success', ['order' => $order->uuid], navigate: true);
    }
}; ?>

<div>
<x-page-header :title="__('Lacak Pesanan')" :items="[
    ['label' => __('Beranda'), 'url' => route('home')],
    ['label' => __('Lacak Pesanan')],
]" />

<x-container class="py-10 pb-24 lg:py-16">
    <div class="mx-auto max-w-lg">
        <div class="text-center">
            <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-body-2 dark:text-zinc-300">
                {{ __('Masukkan nomor pesanan Anda untuk melihat status pembayaran dan detail pengiriman.') }}
            </p>
        </div>

        <form wire:submit="track" class="mt-8 rounded-2xl border border-line bg-white p-6 shadow-soft dark:border-zinc-800 dark:bg-zinc-900 sm:p-8">
        <flux:input
            wire:model="order_number"
            label="{{ __('Nomor Pesanan') }}"
            placeholder=""
            icon="hashtag"
            autofocus
            class="font-mono uppercase tracking-wider [&_input]:uppercase" />

        @if ($track_error)
            <p class="mt-2 text-sm font-medium text-sale" role="alert">{{ $track_error }}</p>
        @endif

        <x-btn type="submit" variant="primary" class="mt-5 w-full" wire:loading.attr="disabled" wire:target="track">
            <span wire:loading.remove wire:target="track">{{ __('Lacak Pesanan') }}</span>
            <span wire:loading wire:target="track">{{ __('Memproses...') }}</span>
        </x-btn>
    </form>

        <div class="mt-6 text-center">
            <x-btn variant="outline" :href="route('products.index')" wire:navigate>{{ __('Lanjut Belanja') }}</x-btn>
        </div>
    </div>
</x-container>
</div>
