<?php

use App\Models\Setting;
use App\Services\SettingService;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengaturan')] #[Layout('layouts::admin')] class extends Component
{
    public array $values = [];

    public array $fields = [
        'site_name' => ['label' => 'Nama Situs'],
        'site_tagline' => ['label' => 'Tagline'],
        'site_email' => ['label' => 'Email Customer Service'],
        'site_phone' => ['label' => 'Nomor Telepon'],
        'site_whatsapp' => ['label' => 'Nomor WhatsApp'],
        'site_address' => ['label' => 'Alamat'],
        'site_hours' => ['label' => 'Jam Operasional'],
        'site_maps_url' => ['label' => 'Link Google Maps'],
        'origin_district_id' => ['label' => 'ID Kelurahan Asal Pengiriman (RajaOngkir)'],
    ];

    public array $paymentFields = [
        'expiry_order' => ['label' => 'Batas Waktu Pembayaran (menit)'],
    ];

    public function mount(): void
    {
        foreach ([...array_keys($this->fields), ...array_keys($this->paymentFields)] as $key) {
            $this->values[$key] = (string) Setting::query()->where('key', $key)->value('value');
        }
    }

    public function save(): void
    {
        $this->validate(
            [
                'values.site_name' => ['required', 'string', 'max:255'],
                'values.site_email' => ['nullable', 'email'],
                'values.site_maps_url' => ['nullable', 'url'],
                'values.origin_district_id' => ['nullable', 'integer', 'min:1'],
                'values.expiry_order' => ['required', 'integer', 'min:15'],
            ],
            attributes: [
                'values.site_name' => 'nama situs',
                'values.site_email' => 'email',
                'values.site_maps_url' => 'link Google Maps',
                'values.origin_district_id' => 'ID kelurahan asal',
                'values.expiry_order' => 'batas waktu pembayaran',
            ],
        );

        foreach ($this->values as $key => $value) {
            if ($this->fields[$key]['readonly'] ?? false) {
                continue;
            }

            SettingService::set($key, $value);
        }

        Flux::toast(variant: 'success', text: 'Pengaturan disimpan & cache diperbarui.');
    }
}; ?>

@php
    $infoKeys = ['site_name', 'site_tagline', 'site_email', 'site_phone', 'site_whatsapp', 'site_address', 'site_hours', 'site_maps_url'];
@endphp

<form wire:submit="save" class="mx-auto max-w-[1000px]">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:text-[28px]">Pengaturan Situs</h1>
    </div>

    <div class="space-y-5 pb-24">
        <section class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
                <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Informasi Toko</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">Identitas toko yang tampil ke pelanggan.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 sm:p-6">
                @foreach ($infoKeys as $key)
                    @include('partials.admin.settings-field', ['key' => $key, 'field' => $fields[$key], 'required' => $key === 'site_name'])
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
                <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Pengiriman</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">Titik asal pengiriman untuk menghitung ongkos kirim.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 sm:p-6">
                @foreach (['origin_district_id'] as $key)
                    @include('partials.admin.settings-field', ['key' => $key, 'field' => $fields[$key]])
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
                <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Pembayaran</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">Batas waktu pembayaran pesanan.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 p-5 sm:p-6">
                @foreach ($paymentFields as $key => $field)
                    @include('partials.admin.settings-field', ['key' => $key, 'field' => $field])
                @endforeach
            </div>
        </section>
    </div>

    <div class="sticky bottom-0 -mx-4 flex items-center justify-end gap-3 border-t border-gray-200 bg-white/90 px-4 py-3.5 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/90 sm:-mx-6 sm:px-6">
        <flux:button type="submit" variant="primary" class="cursor-pointer" :loading="false"><span wire:loading.remove wire:target="save">Simpan Perubahan</span><span wire:loading wire:target="save">Memproses...</span></flux:button>
    </div>
</form>
