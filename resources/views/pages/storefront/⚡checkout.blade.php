<?php

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\Payments\Midtrans\MidtransPaymentAttemptService;
use App\Services\ShippingService;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::storefront')] class extends Component {
    public function rendering(View $view): void
    {
        $view->title(__('Checkout'));
    }

    public string $customer_name = '';
    public string $customer_email = '';
    public string $customer_phone = '';
    public string $shipping_address = '';

    public ?int $provinceId = null;
    public ?int $cityId = null;
    public ?int $districtId = null;
    public ?int $subDistrictId = null;
    public ?int $destinationId = null;
    public ?string $destinationLabel = null;
    public ?string $regionError = null;

    public string $courier = '';

    public ?string $shipping_courier_name = null;
    public ?string $shipping_service = null;
    public ?string $shipping_service_label = null;
    public ?string $shipping_etd = null;
    public int $shipping_cost = 0;

    public string $idempotency_key = '';

    public function mount(CartService $cart): void
    {
        if ($cart->isEmpty()) {
            $this->redirectRoute('cart.index', navigate: true);

            return;
        }

        if ($user = auth()->user()) {
            $this->customer_name = $user->name;
            $this->customer_email = $user->email;
            $this->customer_phone = $user->phone
                ?: (string) Order::ownedBy($user)->latest()->value('customer_phone');
        }

        $this->idempotency_key = (string) Str::uuid();
    }

    #[Computed]
    public function provinces(): array
    {
        return $this->regions(fn (ShippingService $s): array => $s->provinces());
    }

    #[Computed]
    public function cities(): array
    {
        return $this->provinceId === null ? [] : $this->regions(fn (ShippingService $s): array => $s->cities($this->provinceId));
    }

    #[Computed]
    public function districts(): array
    {
        return $this->cityId === null ? [] : $this->regions(fn (ShippingService $s): array => $s->districts($this->cityId));
    }

    #[Computed]
    public function subDistricts(): array
    {
        return $this->districtId === null ? [] : $this->regions(fn (ShippingService $s): array => $s->subDistricts($this->districtId));
    }

    private function regions(callable $ambil): array
    {
        try {
            $this->regionError = null;

            return $ambil(app(ShippingService::class));
        } catch (BusinessRuleException $e) {
            $this->regionError = $e->getMessage();

            return [];
        }
    }

    public function updatedProvinceId(): void
    {
        $this->reset(['cityId', 'districtId', 'subDistrictId']);
        $this->tujuanBerubah();
    }

    public function updatedCityId(): void
    {
        $this->reset(['districtId', 'subDistrictId']);
        $this->tujuanBerubah();
    }

    public function updatedDistrictId(): void
    {
        $this->reset('subDistrictId');
        $this->tujuanBerubah();
    }

    public function updatedSubDistrictId(): void
    {
        $this->tujuanBerubah();
    }

    private function tujuanBerubah(): void
    {
        $this->reset(['courier', 'destinationId', 'destinationLabel', 'shipping_courier_name', 'shipping_service', 'shipping_service_label', 'shipping_etd', 'shipping_cost']);
        unset($this->cities, $this->districts, $this->subDistricts, $this->tariffs, $this->couriers, $this->shippingOptions);

        if ($this->subDistrictId === null) {
            return;
        }

        $kelurahan = collect($this->subDistricts)->firstWhere('id', $this->subDistrictId);

        if ($kelurahan === null) {
            $this->subDistrictId = null;

            return;
        }

        $this->destinationId = $this->subDistrictId;
        $this->destinationLabel = implode(', ', array_filter([
            $kelurahan['name'],
            collect($this->districts)->firstWhere('id', $this->districtId)['name'] ?? null,
            collect($this->cities)->firstWhere('id', $this->cityId)['name'] ?? null,
            collect($this->provinces)->firstWhere('id', $this->provinceId)['name'] ?? null,
            $kelurahan['zip'] ?? null,
        ]));
    }

    public function updatedCourier(): void
    {
        $this->resetErrorBag('courier');
        $this->reset(['shipping_courier_name', 'shipping_service', 'shipping_service_label', 'shipping_etd', 'shipping_cost']);
        unset($this->shippingOptions);
    }

    #[Computed]
    public function tariffs(): array
    {
        if ($this->destinationId === null) {
            return [];
        }

        try {
            return app(ShippingService::class)->tariffs($this->destinationId, $this->weight);
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());

            return [];
        }
    }

    #[Computed]
    public function couriers(): array
    {
        $aktif = array_intersect_key(
            ShippingService::COURIERS,
            array_flip(ShippingService::ENABLED_COURIERS),
        );

        if ($this->destinationId === null) {
            return $aktif;
        }

        return array_intersect_key($aktif, array_flip(array_column($this->tariffs, 'code')));
    }

    #[Computed]
    public function payBlocked(): bool
    {
        return $this->destinationId !== null && $this->couriers === [];
    }

    #[Computed]
    public function shippingOptions(): array
    {
        if ($this->courier === '') {
            return [];
        }

        return array_values(array_filter(
            $this->tariffs,
            fn (array $opsi): bool => $opsi['code'] === $this->courier,
        ));
    }

    public function selectShipping(int $index): void
    {
        $option = $this->shippingOptions[$index] ?? null;

        if ($option === null) {
            return;
        }

        $this->resetErrorBag('shipping_service');
        $this->shipping_courier_name = $option['name'] ?: (ShippingService::COURIERS[$this->courier] ?? strtoupper($this->courier));
        $this->shipping_service = $option['service'];
        $this->shipping_service_label = $option['description'];
        $this->shipping_etd = $option['etd'];
        $this->shipping_cost = (int) $option['cost'];
    }

    #[Computed]
    public function items()
    {
        return app(CartService::class)->items();
    }

    #[Computed]
    public function subtotal(): int
    {
        return app(CartService::class)->subtotal();
    }

    #[Computed]
    public function weight(): int
    {
        return app(CartService::class)->totalWeight();
    }

    public function grandTotal(): int
    {
        return $this->subtotal + $this->shipping_cost;
    }

    public function placeOrder(CartService $cart, OrderService $orders): void
    {
        try {
            $validated = $this->validate([
                'customer_name' => ['required', 'string', 'max:255'],
                'customer_email' => ['required', 'email', 'max:255'],
                'customer_phone' => ['required', 'string', 'max:50'],
                'shipping_address' => ['required', 'string'],
                'provinceId' => ['required', 'integer'],
                'cityId' => ['required', 'integer'],
                'districtId' => ['required', 'integer'],
                'subDistrictId' => ['required', 'integer'],
                'destinationId' => ['required', 'integer'],
                'courier' => ['required', 'string'],
                'shipping_cost' => ['required', 'integer', 'min:0'],
                'shipping_service' => ['required', 'string'],
            ], [
                'provinceId.required' => __('Silakan pilih provinsi.'),
                'cityId.required' => __('Silakan pilih kabupaten/kota.'),
                'districtId.required' => __('Silakan pilih kecamatan.'),
                'subDistrictId.required' => __('Silakan pilih kelurahan.'),
                'destinationId.required' => __('Silakan pilih kelurahan.'),
                'courier.required' => __('Silakan pilih kurir pengiriman.'),
                'shipping_service.required' => __('Silakan pilih layanan pengiriman.'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('checkout-invalid');

            throw $e;
        }

        $payload = [
            'customer' => [
                'name' => $validated['customer_name'],
                'email' => $validated['customer_email'],
                'phone' => $validated['customer_phone'],
            ],
            'shipping' => [
                'province_id' => $validated['provinceId'],
                'city_id' => $validated['cityId'],
                'destination_id' => $validated['destinationId'],
                'destination_label' => $this->destinationLabel,
                'address' => $validated['shipping_address'],
                'courier' => $validated['courier'],
                'courier_name' => $this->shipping_courier_name,
                'service' => $this->shipping_service,
                'service_label' => $this->shipping_service_label,
                'etd' => $this->shipping_etd,
                'cost' => $this->shipping_cost,
            ],
            'items' => $cart->toCheckoutItems(),
        ];

        try {
            $order = $orders->checkout($payload, $this->idempotency_key);
        } catch (BusinessRuleException $e) {
            $this->addError('cart', $e->getMessage());
            $this->dispatch('checkout-invalid');

            return;
        }

        if (($user = auth()->user()) && blank($user->phone)) {
            $user->update(['phone' => $validated['customer_phone']]);
        }

        $cart->clear();
        $this->dispatch('cart-updated');

        try {
            $attempt = app(MidtransPaymentAttemptService::class)->createOrReuseActiveAttempt($order);
        } catch (Throwable $e) {
            report($e);
            $this->redirectRoute('checkout.success', ['order' => $order->uuid], navigate: false);

            return;
        }

        if (blank($attempt->redirect_url)) {
            $this->redirectRoute('checkout.success', ['order' => $order->uuid], navigate: false);

            return;
        }

        $this->redirect($attempt->redirect_url);
    }
}; ?>

<x-container class="py-8 lg:py-12">
    <h1 class="text-heading text-[26px] text-ink dark:text-zinc-100 sm:text-[32px]">{{ __('Checkout') }}</h1>

    <form wire:submit="placeOrder" class="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]"
          x-data="{
              scrollToError() {
                  requestAnimationFrame(() => {
                      const el = this.$el.querySelector('[data-invalid], [data-checkout-error]');
                      if (! el) return;
                      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                      const field = el.matches('input, select, textarea') ? el : el.querySelector('input, select, textarea');
                      if (field) field.focus({ preventScroll: true });
                  });
              }
          }"
          x-on:checkout-invalid.window="scrollToError()">
        <div class="space-y-6">
            <section class="rounded-lg border border-line bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-ink dark:text-zinc-100">{{ __('Data Pelanggan') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="customer_name" label="{{ __('Nama Lengkap') }}" autocomplete="name" />
                    <flux:input wire:model="customer_phone" type="tel" inputmode="tel" label="{{ __('Nomor Telepon') }}" autocomplete="tel" />
                    <flux:input wire:model="customer_email" type="email" label="{{ __('Email') }}" autocomplete="email" class="sm:col-span-2" />
                </div>
            </section>

            <section class="rounded-lg border border-line bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-ink dark:text-zinc-100">{{ __('Alamat Pengiriman') }}</h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <flux:select wire:model.live="provinceId" label="{{ __('Provinsi') }}" class="cursor-pointer">
                            <option value="" class="placeholder">{{ __('Pilih provinsi…') }}</option>
                            @foreach ($this->provinces as $wilayah)
                                <flux:select.option value="{{ $wilayah['id'] }}">{{ $wilayah['name'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <flux:select wire:model.live="cityId" label="{{ __('Kabupaten/Kota') }}" class="cursor-pointer" :disabled="$provinceId === null"
                                     wire:loading.attr="disabled" wire:target="provinceId">
                            <option value="" class="placeholder">{{ __('Pilih kabupaten/kota…') }}</option>
                            @foreach ($this->cities as $wilayah)
                                <flux:select.option value="{{ $wilayah['id'] }}">{{ $wilayah['name'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <flux:select wire:model.live="districtId" label="{{ __('Kecamatan') }}" class="cursor-pointer" :disabled="$cityId === null"
                                     wire:loading.attr="disabled" wire:target="provinceId, cityId">
                            <option value="" class="placeholder">{{ __('Pilih kecamatan…') }}</option>
                            @foreach ($this->districts as $wilayah)
                                <flux:select.option value="{{ $wilayah['id'] }}">{{ $wilayah['name'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <flux:select wire:model.live="subDistrictId" label="{{ __('Kelurahan') }}" class="cursor-pointer" :disabled="$districtId === null"
                                     wire:loading.attr="disabled" wire:target="provinceId, cityId, districtId">
                            <option value="" class="placeholder">{{ __('Pilih kelurahan…') }}</option>
                            @foreach ($this->subDistricts as $wilayah)
                                <flux:select.option value="{{ $wilayah['id'] }}">{{ $wilayah['name'] }} ({{ $wilayah['zip'] }})</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>

                <p wire:loading wire:target="provinceId, cityId, districtId, subDistrictId" class="mt-2 flex items-center gap-2 text-xs text-body-2 dark:text-zinc-300">
                    <flux:icon.arrow-path class="size-3.5 animate-spin" /> {{ __('Memuat wilayah...') }}
                </p>

                @if ($regionError)
                    <p data-checkout-error class="mt-2 flex items-center gap-1.5 rounded-md border-l-4 border-sale bg-sale/10 px-4 py-3 text-sm font-medium text-sale">
                        <flux:icon.exclamation-triangle class="size-4 shrink-0" /> {{ $regionError }}
                    </p>
                @endif

                <div class="mt-4">
                    <flux:textarea wire:model="shipping_address" label="{{ __('Alamat Lengkap') }}" autocomplete="street-address" placeholder="{{ __('Nama jalan, nomor, RT/RW, patokan...') }}" rows="3" />
                </div>
            </section>

            <section class="rounded-lg border border-line bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-ink dark:text-zinc-100">{{ __('Metode Pengiriman') }}</h2>

                @if ($destinationId === null)
                    <p wire:key="kurir-petunjuk" wire:loading.remove wire:target="subDistrictId" class="mt-2 text-sm text-body-2 dark:text-zinc-400">{{ __('Silakan lengkapi dulu alamat pengiriman.') }}</p>
                    <p wire:key="kurir-memuat-kelurahan" wire:loading.flex wire:target="subDistrictId" class="mt-2 items-center gap-2 text-sm text-body-2 dark:text-zinc-400">
                        <flux:icon.arrow-path class="size-4 animate-spin" /> {{ __('Memperbarui pilihan kurir...') }}
                    </p>
                @else
                    <p wire:key="kurir-memuat-alamat" wire:loading.flex wire:target="provinceId, cityId, districtId, subDistrictId" class="mt-2 items-center gap-2 text-sm text-body-2 dark:text-zinc-400">
                        <flux:icon.arrow-path class="size-4 animate-spin" /> {{ __('Memperbarui pilihan kurir...') }}
                    </p>
                    <div wire:key="kurir-pilihan" wire:loading.remove wire:target="provinceId, cityId, districtId, subDistrictId">
                        @php
                            $logoKurir = ['jne' => 'jne.svg', 'jnt' => 'jnt.svg'];
                            $kartuPilihan = 'relative cursor-pointer rounded-lg border border-line bg-white transition-colors hover:border-gray-400 has-checked:border-brand-green has-checked:ring-1 has-checked:ring-brand-green has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-brand-green dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500 dark:has-checked:border-green-400 dark:has-checked:ring-green-400';
                            $tandaPilihan = 'grid size-[18px] shrink-0 place-items-center rounded-full border-2 border-gray-300 bg-white text-white transition-colors peer-checked:border-brand-green peer-checked:bg-brand-green peer-checked:[&_svg]:block dark:border-zinc-600 dark:bg-zinc-900 dark:peer-checked:border-green-400 dark:peer-checked:bg-green-400';
                        @endphp

                        @if ($this->payBlocked)
                            <p class="mt-4 rounded-md bg-sale/10 px-3 py-2 text-sm font-medium text-sale" role="alert">
                                {{ __('Ongkir belum tersedia untuk alamat ini. Hubungi kami via WhatsApp.') }}
                                @if ($waOngkir = wa_href())
                                    <a href="{{ $waOngkir }}" target="_blank" rel="noopener" class="underline">{{ __('Chat WhatsApp') }}</a>
                                @endif
                            </p>
                        @else
                        <fieldset class="mt-4">
                            <legend class="mb-2 text-sm font-medium text-ink dark:text-zinc-100">{{ __('Pilih Kurir') }}</legend>
                            @error('courier')
                                <p data-checkout-error role="alert" class="mb-2 flex items-center gap-1.5 text-sm font-medium text-sale">
                                    <flux:icon.exclamation-triangle class="size-4 shrink-0" /> {{ $message }}
                                </p>
                            @enderror
                            <div class="grid grid-cols-2 gap-2 sm:gap-3">
                                @foreach ($this->couriers as $code => $name)
                                    <label wire:key="kurir-{{ $code }}" class="{{ $kartuPilihan }} flex flex-col items-center justify-center gap-2 px-2 py-3 sm:h-[72px] sm:px-9 sm:py-0">
                                        <input type="radio" wire:model.live="courier" value="{{ $code }}" name="kurir" class="peer sr-only" />
                                        <span class="inline-flex max-w-full items-center justify-center rounded-md bg-white p-1.5 sm:px-2.5">
                                            @if (isset($logoKurir[$code]))
                                                <img src="{{ asset('assets/images/ekspedisi/'.$logoKurir[$code]) }}" alt="{{ $name }}" class="h-6 w-auto max-w-full select-none object-contain sm:h-7" />
                                            @else
                                                <span class="text-sm font-bold text-ink">{{ $name }}</span>
                                            @endif
                                        </span>
                                        <span class="{{ $tandaPilihan }} sm:absolute sm:right-2.5 sm:top-2.5" aria-hidden="true">
                                            <svg class="hidden size-2.5 fill-none stroke-current stroke-[3.5]" viewBox="0 0 24 24"><path d="m5 12 5 5L20 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        @endif

                        @if ($courier !== '' && ! $this->payBlocked)
                            <fieldset class="mt-5 space-y-2">
                                <legend class="mb-2 text-sm font-medium text-ink dark:text-zinc-100">{{ __('Pilih Layanan') }}</legend>
                                @error('shipping_service')
                                    <p data-checkout-error role="alert" class="flex items-center gap-1.5 text-sm font-medium text-sale">
                                        <flux:icon.exclamation-triangle class="size-4 shrink-0" /> {{ $message }}
                                    </p>
                                @enderror
                                <div wire:loading.flex wire:target="shippingOptions, courier, subDistrictId" class="items-center gap-2 text-sm text-body-2 dark:text-zinc-300">
                                    <flux:icon.arrow-path class="size-4 animate-spin" /> {{ __('Menghitung ongkir...') }}
                                </div>
                                <div wire:loading.remove wire:target="shippingOptions, courier, subDistrictId" class="grid gap-2 sm:grid-cols-2 sm:gap-3">
                                    @forelse ($this->shippingOptions as $i => $opt)
                                        @php $etd = rentang_etd($opt['etd']); @endphp
                                        <label wire:key="layanan-{{ $courier }}-{{ $i }}" class="{{ $kartuPilihan }} flex flex-col gap-1 py-3 pl-4 pr-10">
                                            <input type="radio" name="layanan" value="{{ $opt['service'] }}" @checked($shipping_service === $opt['service']) class="peer sr-only"
                                                   wire:click="selectShipping({{ $i }})" />
                                            <span class="flex items-baseline justify-between gap-3">
                                                <span class="text-sm font-bold text-ink dark:text-zinc-100">{{ $opt['service'] }}</span>
                                                <span class="notranslate text-price text-sm text-ink dark:text-zinc-100">{{ rupiah($opt['cost']) }}</span>
                                            </span>
                                            @if (strcasecmp((string) $opt['description'], (string) $opt['name']) !== 0)
                                                <span class="text-xs text-body-2 dark:text-zinc-400">{{ $opt['description'] }}</span>
                                            @endif
                                            @if ($etd !== '')
                                                <span class="text-xs text-body-2 dark:text-zinc-400">{{ __('Estimasi :etd hari', ['etd' => $etd]) }}</span>
                                            @endif
                                            <span class="{{ $tandaPilihan }} absolute right-2.5 top-3.5" aria-hidden="true">
                                                <svg class="hidden size-2.5 fill-none stroke-current stroke-[3.5]" viewBox="0 0 24 24"><path d="m5 12 5 5L20 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                            </span>
                                        </label>
                                    @empty
                                        <p class="text-sm text-body-2 dark:text-zinc-300 sm:col-span-2">{{ __('Tidak ada layanan tersedia untuk tujuan ini.') }}</p>
                                    @endforelse
                                </div>
                            </fieldset>
                        @endif
                    </div>
                @endif
            </section>
        </div>

        <div class="h-fit space-y-4 self-start rounded-lg border border-line bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 lg:sticky lg:top-20">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-ink dark:text-zinc-100">{{ __('Ringkasan Pesanan') }}</h2>

            <div class="divide-y divide-line dark:divide-zinc-800">
                @foreach ($this->items as $item)
                    <div class="py-2 text-sm">
                        <div class="flex justify-between gap-2">
                            <span class="text-body-2 dark:text-zinc-300">{{ $item['product']->name }} <span class="text-muted dark:text-zinc-400">×{{ $item['quantity'] }}</span></span>
                            <span class="notranslate shrink-0 font-semibold text-ink dark:text-zinc-100">{{ rupiah($item['line_total']) }}</span>
                        </div>
                        @if ($item['variant'])
                            <p class="mt-0.5 text-xs text-muted dark:text-zinc-400">{{ __('Varian:') }} {{ $item['variant']->name }}</p>
                        @endif
                        @if (! empty($item['custom_text']))
                            <p class="mt-0.5 text-xs font-medium text-primary">{{ __('Catatan Custom:') }} {{ $item['custom_text'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="space-y-2 border-t border-line pt-4 text-sm dark:border-zinc-800">
                @error('cart')
                    <p class="rounded-md bg-sale/10 px-3 py-2 text-sm font-medium text-sale" role="alert">{{ $message }}</p>
                @enderror
                <div class="flex justify-between"><span class="text-body-2 dark:text-zinc-300">{{ __('Subtotal') }}</span><span class="notranslate font-semibold text-ink dark:text-zinc-100">{{ rupiah($this->subtotal) }}</span></div>
                <div class="flex justify-between">
                    <span class="text-body-2 dark:text-zinc-300">{{ __('Ongkos Kirim') }}</span>
                    @if ($shipping_service)
                        <span wire:key="ongkir-nilai" wire:loading.remove wire:target="provinceId, cityId, districtId, subDistrictId, courier" class="notranslate font-semibold text-ink dark:text-zinc-100" data-ringkasan-ongkir>{{ rupiah($shipping_cost) }}</span>
                        <span wire:key="ongkir-nilai-memuat" wire:loading wire:target="provinceId, cityId, districtId, subDistrictId, courier" class="text-body-2 dark:text-zinc-400">{{ __('Pilih kurir') }}</span>
                    @else
                        <span wire:key="ongkir-ajakan" wire:loading.remove wire:target="selectShipping" class="text-body-2 dark:text-zinc-400" data-ringkasan-ongkir>{{ $courier === '' ? __('Pilih kurir') : __('Pilih layanan') }}</span>
                        <span wire:key="ongkir-ajakan-memuat" wire:loading wire:target="selectShipping" class="text-body-2 dark:text-zinc-400">{{ __('Menghitung ongkir...') }}</span>
                    @endif
                </div>
                <div class="flex justify-between border-t border-line pt-2 text-base font-bold dark:border-zinc-800">
                    <span class="text-ink dark:text-zinc-100">{{ __('Total') }}</span>
                    <span class="notranslate text-price text-primary">{{ rupiah($this->grandTotal()) }}</span>
                </div>
            </div>

            <div class="hidden lg:block">
                <x-btn type="submit" variant="primary" class="w-full" :disabled="$this->payBlocked" wire:loading.attr="disabled" wire:target="placeOrder">
                    <span wire:loading.remove wire:target="placeOrder">{{ __('Buat Pesanan') }}</span>
                    <span wire:loading wire:target="placeOrder">{{ __('Memproses...') }}</span>
                </x-btn>
            </div>
        </div>

        <div class="bar-aksi fixed inset-x-0 bottom-0 z-40 flex items-center gap-3 border-t border-line bg-white px-4 pt-2.5 shadow-[0_-8px_28px_-12px_rgba(0,0,0,.3)] dark:border-zinc-800 dark:bg-zinc-900 lg:hidden">
            <div class="shrink-0">
                <p class="mb-1 text-[11px] leading-none text-gray-500 dark:text-zinc-400">{{ __('Total') }}</p>
                <p class="notranslate font-display text-lg font-bold leading-none text-brand-dark dark:text-zinc-100">{{ rupiah($this->grandTotal()) }}</p>
            </div>
            <x-btn type="submit" variant="primary" class="h-11 flex-1" :disabled="$this->payBlocked" wire:loading.attr="disabled" wire:target="placeOrder">
                <span wire:loading.remove wire:target="placeOrder">{{ __('Buat Pesanan') }}</span>
                <span wire:loading wire:target="placeOrder">{{ __('Memproses...') }}</span>
            </x-btn>
        </div>
    </form>
</x-container>
