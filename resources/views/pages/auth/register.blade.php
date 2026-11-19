<x-layouts::auth :title="__('Daftar')">
    <div class="flex flex-col gap-5">
        <x-auth-header :title="__('Buat akun baru')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" novalidate action="{{ route('register.store') }}" class="flex flex-col gap-5" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf

            <flux:input
                name="name"
                :label="__('Nama lengkap')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Nama lengkap')"
            />

            <flux:input
                name="email"
                :label="__('Alamat email')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <flux:input
                name="phone"
                :label="__('Nomor HP')"
                :value="old('phone')"
                type="tel"
                required
                autocomplete="tel"
                placeholder="08xxxxxxxxxx"
            />

            <flux:input
                name="password"
                :label="__('Kata sandi')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Kata sandi')"
                viewable
            />

            <flux:input
                name="password_confirmation"
                :label="__('Konfirmasi kata sandi')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Konfirmasi kata sandi')"
                viewable
            />

            <x-btn variant="primary" type="submit" class="w-full" data-test="register-button" x-bind:disabled="submitting">
                <span x-show="!submitting">{{ __('Daftar') }}</span>
                <span x-show="submitting" x-cloak>{{ __('Memproses...') }}</span>
            </x-btn>
        </form>

        <div class="space-x-1 text-center text-sm text-body-2 rtl:space-x-reverse dark:text-zinc-400">
            <span>{{ __('Sudah punya akun?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Masuk') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
