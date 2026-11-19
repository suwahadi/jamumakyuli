<?php

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\OrderService;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Detail Pesanan')] #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    private const ATURAN_FOTO = ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'];

    private const PESAN_FOTO = [
        'bukti_kirim.image' => 'Berkas harus berupa foto.',
        'bukti_kirim.mimes' => 'Gunakan foto JPG, PNG, atau WebP.',
        'bukti_kirim.max' => 'Ukuran foto maksimal 10 MB.',
    ];

    public Order $order;

    public string $tracking_number = '';

    public $bukti_kirim = null;

    public function mount(Order $order): void
    {
        $this->order = $order->load('items.product', 'items.variant', 'activities');
        $this->tracking_number = (string) $order->tracking_number;
    }

    public function markPaid(OrderService $service): void
    {
        $service->markAsPaid($this->order, auth()->user()?->name);
        $this->order->refresh();
        Flux::modal('confirm-paid')->close();
        Flux::toast(variant: 'success', text: 'Pesanan ditandai lunas. Notifikasi pembayaran dikirim.');
    }

    public function markShipped(OrderService $service): void
    {
        $validated = $this->validate([
            'tracking_number' => ['required', 'string', 'max:100'],
            'bukti_kirim' => ['nullable', ...self::ATURAN_FOTO],
        ], [
            'tracking_number.required' => 'Nomor resi atau keterangan kirim wajib diisi.',
            ...self::PESAN_FOTO,
        ]);

        try {
            $service->markAsShipped($this->order, auth()->user()?->name, $validated['tracking_number']);

            if ($this->bukti_kirim) {
                $service->simpanBuktiKirim($this->order, $this->bukti_kirim);
                $this->bukti_kirim = null;
            }

            $this->order->refresh();
            Flux::toast(variant: 'success', text: 'Pesanan ditandai telah dikirim. Notifikasi pengiriman dikirim.');
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());
        }

        Flux::modal('confirm-shipped')->close();
    }

    public function updatedBuktiKirim(): void
    {
        if (! in_array($this->order->status, [OrderStatus::SHIPPED, OrderStatus::DELIVERED], true)) {
            return;
        }

        $this->validate(['bukti_kirim' => ['required', ...self::ATURAN_FOTO]], self::PESAN_FOTO);

        app(OrderService::class)->simpanBuktiKirim($this->order, $this->bukti_kirim);
        $this->bukti_kirim = null;
        $this->order->refresh();
        Flux::toast(variant: 'success', text: 'Foto bukti kirim disimpan.');
    }

    public function saveTracking(OrderService $service): void
    {
        $validated = $this->validate([
            'tracking_number' => ['required', 'string', 'max:100'],
        ], [
            'tracking_number.required' => 'Nomor resi atau keterangan kirim wajib diisi.',
        ]);

        $sebelumnya = (string) $this->order->tracking_number;
        $service->updateTracking($this->order, $validated['tracking_number'], auth()->user()?->name);
        $this->order->refresh();
        $this->tracking_number = (string) $this->order->tracking_number;

        Flux::toast(variant: 'success', text: $this->order->tracking_number === $sebelumnya
            ? 'Nomor resi tidak berubah.'
            : 'Nomor resi diperbarui. Pembeli dikirimi email berisi resi baru.');
        $this->dispatch('resi-tersimpan');
    }

    public function batalUbahResi(): void
    {
        $this->tracking_number = (string) $this->order->tracking_number;
        $this->resetErrorBag('tracking_number');
    }

    public function markDelivered(OrderService $service): void
    {
        try {
            $service->markAsDelivered($this->order, auth()->user()?->name);
            $this->order->refresh();
            Flux::toast(variant: 'success', text: 'Pesanan ditandai diterima. Pelanggan dikirimi email ucapan terima kasih.');
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());
        }

        Flux::modal('confirm-delivered')->close();
    }

    public function cancelOrder(OrderService $service): void
    {
        try {
            $service->cancel($this->order, auth()->user()?->name);
            $this->order->refresh();
            Flux::toast(variant: 'success', text: 'Pesanan dibatalkan. Stok dikembalikan.');
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());
        }

        Flux::modal('confirm-cancel')->close();
    }
}; ?>

<div>
    @php $order->loadMissing('items.product', 'items.variant', 'activities'); @endphp

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <flux:button :href="route('admin.orders.index')" wire:navigate size="sm" variant="ghost" icon="arrow-left" aria-label="Kembali ke daftar pesanan" />
        <div>
            <div class="flex items-center gap-3">
                <h1 class="font-mono text-xl font-extrabold text-brand-dark dark:text-zinc-100">{{ $order->order_number }}</h1>
                <x-order-status-badge :status="$order->status" />
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">Tanggal dibuat: {{ tanggal_id($order->created_at) }}</p>
        </div>
        @if (in_array($order->status, App\Enums\OrderStatus::berpenghasilan(), true))
            <flux:button :href="route('admin.orders.label', $order)" target="_blank" variant="outline" icon="printer" aria-label="Cetak Label (tab baru)" class="ml-auto max-sm:!h-11">Cetak Label</flux:button>
        @endif
        @if ($order->status !== App\Enums\OrderStatus::CANCELLED)
            <flux:button :href="route('orders.invoice', $order)" target="_blank" variant="outline" icon="document-text" aria-label="Lihat Invoice (tab baru)" :class="in_array($order->status, App\Enums\OrderStatus::berpenghasilan(), true) ? 'max-sm:!h-11' : 'ml-auto max-sm:!h-11'">Lihat Invoice</flux:button>
        @endif
    </div>

    <div class="mb-6 flex flex-col gap-2.5 lg:hidden">
        @include('partials.admin.order-actions')
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="space-y-6">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800"><h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Item Pesanan</h2></div>
                <div class="divide-y divide-gray-100 px-5 dark:divide-zinc-800">
                    @foreach ($order->items as $item)
                        <div class="flex items-center justify-between gap-3 py-3.5 text-sm">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800 dark:text-zinc-100">{{ $item->product->name ?? 'Produk dihapus' }}</p>
                                @if ($item->namaVarian())<p class="text-xs text-gray-500 dark:text-zinc-400">Varian: {{ $item->namaVarian() }}</p>@endif
                                @if ($item->custom_text)<p class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">Catatan Custom: {{ $item->custom_text }}</p>@endif
                                <p class="mt-0.5 font-mono text-xs text-gray-500 dark:text-zinc-400">{{ rupiah($item->price) }} × {{ $item->quantity }}</p>
                            </div>
                            <span class="shrink-0 font-mono font-bold text-brand-dark dark:text-zinc-100">{{ rupiah($item->total) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800"><h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Riwayat Pesanan</h2></div>
                <ol class="px-5 py-4">
                    @forelse ($order->activities->reverse() as $activity)
                        @php
                            $dot = \App\Support\Tone::chip($activity->type->color(), strong: true);
                        @endphp
                        <li class="relative flex gap-3 pb-5 last:pb-0" wire:key="act-{{ $activity->id }}">
                            @unless ($loop->last)
                                <span class="absolute left-3.5 top-8 -bottom-1 w-px bg-gray-200 dark:bg-zinc-700"></span>
                            @endunless
                            <span class="relative z-10 flex size-7 shrink-0 items-center justify-center rounded-full {{ $dot }}">
                                <flux:icon :icon="$activity->type->icon()" class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1 pt-0.5">
                                <p class="text-sm font-semibold text-gray-800 dark:text-zinc-100">{{ $activity->type->label() }}</p>
                                <p class="text-sm text-gray-600 dark:text-zinc-300">{{ $activity->description }}</p>
                                <p class="mt-0.5 font-mono text-xs text-gray-500 dark:text-zinc-400">{{ $activity->actor ?? 'Sistem' }} • {{ tanggal_id($activity->created_at) }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500 dark:text-zinc-400">Belum ada riwayat aktivitas.</li>
                    @endforelse
                </ol>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800"><h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Pengiriman</h2></div>
                <dl class="grid gap-x-8 gap-y-4 px-5 py-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-zinc-400">Ekspedisi &amp; Layanan</dt>
                        <dd class="mt-1 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-100 px-2.5 py-1 text-[12px] text-amber-600 dark:bg-amber-500/15 dark:text-amber-300">
                                <flux:icon.truck class="size-4" /> {{ $order->shippingCourierName() }}
                            </span>
                            <span class="font-mono text-xs text-gray-500 dark:text-zinc-400">{{ rupiah($order->shipping_cost) }}</span>
                        </dd>
                        @if ($detail = $order->shippingServiceDetail())
                            <dd class="mt-1 text-xs text-gray-500 dark:text-zinc-400">{{ $detail }}</dd>
                        @endif
                    </div>
                    @if (in_array($order->status, [App\Enums\OrderStatus::SHIPPED, App\Enums\OrderStatus::DELIVERED], true))
                        <div x-data="{ ubah: false }" x-on:resi-tersimpan.window="ubah = false">
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-zinc-400"><label for="resi-inline">Nomor Resi</label></dt>
                            <dd class="mt-1">
                                <div x-show="! ubah" class="flex min-h-11 items-center gap-1 rounded-md border border-gray-200 bg-gray-50 pl-3 pr-1 dark:border-zinc-700 dark:bg-zinc-800/60">
                                    <span class="min-w-0 flex-1 break-all font-mono font-bold text-brand-dark dark:text-zinc-100">{{ $order->tracking_number ?: '-' }}</span>
                                    @if ($order->tracking_number)
                                        <x-copy-button :text="$order->tracking_number" label="Salin nomor resi" class="!size-9 [&>svg]:!size-[18px]" />
                                    @endif
                                    <button type="button" aria-label="Ubah nomor resi" title="Ubah nomor resi" x-on:click="ubah = true; $nextTick(() => $refs.resi.focus())"
                                            class="grid size-9 shrink-0 cursor-pointer place-items-center rounded-md transition text-gray-500 hover:bg-gray-100 hover:text-brand-dark dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                                        <svg class="size-[18px] fill-none stroke-current stroke-2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M12 20h9" />
                                            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                        </svg>
                                    </button>
                                </div>
                                <div x-show="ubah" x-cloak x-on:keydown.escape.prevent="ubah = false; $wire.batalUbahResi()">
                                    <div class="flex min-h-11 items-center gap-1 rounded-md border border-zinc-500 bg-white pl-3 pr-1 focus-within:ring-2 focus-within:ring-zinc-900/15 dark:bg-zinc-900 dark:focus-within:ring-white/20">
                                        <input id="resi-inline" x-ref="resi" type="text" wire:model="tracking_number" maxlength="100"
                                               wire:keydown.enter.prevent="saveTracking"
                                               class="min-w-0 flex-1 bg-transparent py-2 font-mono font-bold text-brand-dark outline-none dark:text-zinc-100" />
                                        <button type="button" aria-label="Simpan nomor resi" title="Simpan nomor resi" wire:click="saveTracking"
                                                class="grid size-9 shrink-0 cursor-pointer place-items-center rounded-md transition text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-500/10">
                                            <svg class="size-[18px] fill-none stroke-current stroke-2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="m5 13 4 4L19 7" />
                                            </svg>
                                        </button>
                                        <button type="button" aria-label="Batal ubah nomor resi" title="Batal" x-on:click="ubah = false" wire:click="batalUbahResi"
                                                class="grid size-9 shrink-0 cursor-pointer place-items-center rounded-md transition text-gray-500 hover:bg-gray-100 hover:text-brand-dark dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                                            <svg class="size-[18px] fill-none stroke-current stroke-2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M18 6 6 18" />
                                                <path d="m6 6 12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                    @error('tracking_number')
                                        <p class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                            </dd>
                        </div>
                    @elseif ($order->tracking_number)
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-zinc-400">Nomor Resi</dt>
                            <dd class="mt-0.5 flex items-center gap-2">
                                <span class="min-w-0 break-all font-mono font-bold text-brand-dark dark:text-zinc-100">{{ $order->tracking_number }}</span>
                                <x-copy-button :text="$order->tracking_number" label="Salin nomor resi" />
                            </dd>
                        </div>
                    @endif
                    @if (in_array($order->status, [App\Enums\OrderStatus::SHIPPED, App\Enums\OrderStatus::DELIVERED], true))
                        @php($buktiUrl = $order->buktiKirimUrl())
                        <div x-data="productLightbox()">
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-zinc-400">Bukti Kirim</dt>
                            <dd class="mt-1 flex items-center gap-3">
                                @if ($buktiUrl)
                                    <a href="{{ $buktiUrl }}" target="_blank" rel="noopener" x-on:click.prevent="open(0, [$el.href])" aria-label="Perbesar foto bukti kirim"
                                       class="block size-16 shrink-0 overflow-hidden rounded-md border border-gray-200 bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800">
                                        <img src="{{ $buktiUrl }}" alt="Foto bukti kirim" class="size-full object-cover">
                                    </a>
                                @endif
                                <div class="min-w-0">
                                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-md border border-gray-200 px-3 text-sm font-semibold text-brand-dark transition hover:bg-gray-50 focus-within:ring-2 focus-within:ring-zinc-900/15 dark:border-zinc-700 dark:text-zinc-100 dark:hover:bg-zinc-800 dark:focus-within:ring-white/20">
                                        <input type="file" wire:model="bukti_kirim" accept="image/jpeg,image/png,image/webp" class="sr-only">
                                        <flux:icon.photo class="size-4" />
                                        {{ $buktiUrl ? 'Ganti foto' : 'Unggah foto' }}
                                    </label>
                                    <p wire:loading wire:target="bukti_kirim" class="mt-1 text-xs text-gray-500 dark:text-zinc-400">Mengunggah foto…</p>
                                    @error('bukti_kirim')
                                        <p class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                            </dd>
                            <x-product-lightbox label="Foto bukti kirim" />
                        </div>
                    @endif
                    @if ($order->shipping_destination_label)
                        <div><dt class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-zinc-400">Wilayah</dt><dd class="mt-0.5 text-gray-800 dark:text-zinc-100">{{ $order->shipping_destination_label }}</dd></div>
                    @endif
                    <div><dt class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-zinc-400">Alamat</dt><dd class="mt-0.5 text-gray-800 dark:text-zinc-100">{{ $order->shipping_address }}</dd></div>
                </dl>
                @if ($order->status === App\Enums\OrderStatus::PAID)
                    <p class="border-t border-gray-100 px-5 py-4 text-xs leading-relaxed text-gray-500 dark:border-zinc-800 dark:text-zinc-400">
                        Setelah paket diserahkan ke ekspedisi, tekan Tandai Dikirim dan masukkan nomor resinya. Pembeli otomatis menerima email berisi resi.
                    </p>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Pelanggan</h2>
                <dl class="mt-3 space-y-3 text-sm">
                    <div><dt class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-zinc-400">Nama</dt><dd class="mt-0.5 text-gray-800 dark:text-zinc-100">{{ $order->customer_name }}</dd></div>
                    <div><dt class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-zinc-400">Email</dt><dd class="mt-0.5 text-gray-800 dark:text-zinc-100">{{ $order->customer_email }}</dd></div>
                    <div><dt class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-zinc-400">Telepon</dt><dd class="mt-0.5 font-mono text-gray-800 dark:text-zinc-100">{{ $order->customer_phone }}</dd></div>
                </dl>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 lg:sticky lg:top-[88px]">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800"><h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Ringkasan Pembayaran</h2></div>
                <div class="space-y-1.5 px-5 py-4 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500 dark:text-zinc-400">Subtotal</span><span class="font-mono text-brand-dark dark:text-zinc-100">{{ rupiah($order->subtotal) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500 dark:text-zinc-400">Ongkos Kirim ({{ $order->shippingCourierName() }})</span><span class="font-mono text-brand-dark dark:text-zinc-100">{{ rupiah($order->shipping_cost) }}</span></div>
                    <div class="flex justify-between border-t border-gray-200 pt-2.5 text-base font-extrabold dark:border-zinc-800"><span class="text-brand-dark dark:text-zinc-100">Total</span><span class="font-mono text-brand-green">{{ rupiah($order->grand_total) }}</span></div>
                </div>

                <div class="hidden flex-col gap-2.5 border-t border-gray-100 px-5 py-4 dark:border-zinc-800 lg:flex">
                    @include('partials.admin.order-actions')
                </div>
            </div>
        </div>
    </div>

    <flux:modal name="confirm-paid" class="md:w-[26rem]">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <flux:icon.check-circle class="size-5" />
                </span>
                <div>
                    <flux:heading size="lg">Tandai pesanan lunas?</flux:heading>
                    <flux:text class="mt-1">Status pesanan akan menjadi <span class="font-semibold">Diproses</span> dan notifikasi pembayaran dikirim ke pelanggan.</flux:text>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="outline" class="cursor-pointer">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="markPaid" variant="primary" class="cursor-pointer">Ya, Tandai Lunas</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="confirm-shipped" class="md:w-[26rem]">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400">
                    <flux:icon.truck class="size-5" />
                </span>
                <div>
                    <flux:heading size="lg">Tandai pesanan dikirim?</flux:heading>
                    <flux:text class="mt-1">Pembeli akan menerima email bahwa pesanannya telah <span class="font-semibold">dikirim</span>, beserta nomor resinya.</flux:text>
                </div>
            </div>
            <flux:input wire:model="tracking_number"
                        label="Nomor Resi / Keterangan Kirim"
                        placeholder="mis. JP1234567890 atau Diantar 12 Agu"
                        maxlength="100" />
            <div>
                <flux:input type="file" wire:model="bukti_kirim" accept="image/jpeg,image/png,image/webp"
                            label="Foto bukti kirim (opsional)" />
                <p wire:loading wire:target="bukti_kirim" class="mt-1 text-xs text-gray-500 dark:text-zinc-400">Mengunggah foto…</p>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="outline" class="cursor-pointer">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="markShipped" wire:loading.attr="disabled" wire:target="bukti_kirim,markShipped" variant="primary" class="cursor-pointer">Ya, Tandai Dikirim</flux:button>
            </div>
        </div>
    </flux:modal>

    @if ($order->status === App\Enums\OrderStatus::SHIPPED)
        <flux:modal name="confirm-delivered" class="md:w-[26rem]">
            <div class="space-y-5">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-teal-50 text-teal-600 dark:bg-teal-500/10 dark:text-teal-400">
                        <flux:icon.check-badge class="size-5" />
                    </span>
                    <div>
                        <flux:heading size="lg">Tandai pesanan diterima?</flux:heading>
                        <flux:text class="mt-1">Status pesanan menjadi <strong>Pesanan Diterima</strong>. Tindakan ini tidak dapat diurungkan.</flux:text>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="outline" class="cursor-pointer">Tidak</flux:button></flux:modal.close>
                    <flux:button wire:click="markDelivered" variant="primary" class="cursor-pointer">Ya, Tandai Diterima</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    <flux:modal name="confirm-cancel" class="md:w-[26rem]">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                    <flux:icon.exclamation-triangle class="size-5" />
                </span>
                <div>
                    <flux:heading size="lg">Batalkan pesanan ini?</flux:heading>
                    <flux:text class="mt-1">Stok produk akan dikembalikan. Tindakan ini tidak dapat diurungkan.</flux:text>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="outline" class="cursor-pointer">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="cancelOrder" variant="danger" class="cursor-pointer">Ya, Batalkan Pesanan</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
