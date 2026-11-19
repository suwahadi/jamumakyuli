<?php

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::storefront')] class extends Component {
    public function rendering(View $view): void
    {
        $view->title(__('Kontak'));
    }
}; ?>

@php
    $phone = setting('site_phone', '087879865789');
    $whatsapp = setting('site_whatsapp', '087879865789');
    $email = setting('site_email');
    $address = setting('site_address');
    $mapsUrl = setting('site_maps_url');
    $hours = setting('site_hours');
    $waProduk = wa_href() ?? '#';
@endphp

<div>
<x-page-header :title="__('Kontak')" :items="[
    ['label' => __('Beranda'), 'url' => route('home')],
    ['label' => __('Kontak')],
]" />

<x-container class="py-8 pb-24 lg:py-12 lg:pb-16">
    <div class="grid gap-8 rounded-lg border border-line p-6 dark:border-zinc-800 sm:p-10 md:grid-cols-2 lg:gap-12">
        <div>
            <p class="mb-3 text-[12px] font-bold uppercase tracking-[.15em] text-primary">{{ __('Kontak dan Lokasi') }}</p>
            <h2 class="text-heading text-[26px] text-[#1F2937] dark:text-zinc-100 sm:text-[30px]">{{ __('Kunjungi atau Hubungi Kami') }}</h2>

            <ul class="mt-6 space-y-4 text-[15px] text-body-2 dark:text-zinc-300">
                <li class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 fill-current text-primary" aria-hidden="true"><use href="#i-wa"/></svg>
                    <a href="{{ $waProduk }}" target="_blank" rel="noopener" class="hover:underline underline-offset-4">{{ $whatsapp }}</a>
                </li>
                <li class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 fill-none stroke-primary stroke-2" aria-hidden="true"><use href="#i-phone"/></svg>
                    <a href="{{ tel_href($phone) }}" class="hover:underline underline-offset-4">{{ $phone }}</a>
                </li>
                @if ($email)
                    <li class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 fill-none stroke-primary stroke-2" aria-hidden="true"><use href="#i-mail"/></svg>
                        <a href="mailto:{{ $email }}" class="min-w-0 [overflow-wrap:anywhere] hover:underline underline-offset-4">{{ $email }}</a>
                    </li>
                @endif
                @if ($hours)
                    <li class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 fill-none stroke-primary stroke-2" aria-hidden="true"><use href="#i-clock"/></svg>
                        <span>{{ $hours }}</span>
                    </li>
                @endif
                @if ($address)
                    <li class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 fill-none stroke-primary stroke-2" aria-hidden="true"><use href="#i-pin"/></svg>
                        <span>
                            {{ $address }}
                            @if ($mapsUrl)
                                <a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="mt-1 block font-semibold text-primary hover:underline underline-offset-4">{{ __('Buka di Google Maps') }}</a>
                            @endif
                        </span>
                    </li>
                @endif
            </ul>
        </div>

        <div class="flex flex-col justify-center rounded-lg bg-brand-pink-soft p-6 dark:bg-brand-green/10 sm:p-8">
            <h3 class="text-heading text-[22px] text-[#1F2937] dark:text-zinc-100 sm:text-[24px]">{{ __('Respon Cepat via WhatsApp') }}</h3>
            <p class="mt-2 text-[15px] text-body-2 dark:text-zinc-300">{{ __('Tanya stok, cara merebus, atau ramuan yang cocok untuk Anda. Kami siap bantu.') }}</p>
            <x-btn variant="primary" :href="$waProduk" target="_blank" rel="noopener" class="mt-5 self-start">
                <svg class="h-4 w-4 fill-current" aria-hidden="true"><use href="#i-wa"/></svg>
                {{ __('Chat Sekarang') }}
            </x-btn>
        </div>
    </div>
</x-container>
</div>
