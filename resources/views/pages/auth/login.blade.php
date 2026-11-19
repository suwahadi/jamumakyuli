<x-layouts::auth :title="__('Masuk')">
    <div class="flex flex-col gap-5">
        <x-auth-header :title="__('Masuk ke akun Anda')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" novalidate action="{{ route('login.store') }}" class="flex flex-col gap-5" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf

            <flux:input
                name="email"
                :label="__('Alamat email')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Kata sandi')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Kata sandi')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute end-0 top-0 text-sm" :href="route('password.request')" wire:navigate>
                        {{ __('Lupa kata sandi?') }}
                    </flux:link>
                @endif
            </div>

            <flux:checkbox name="remember" :label="__('Ingat saya')" :checked="old('remember')" />

            <x-btn variant="primary" type="submit" class="w-full" data-test="login-button" x-bind:disabled="submitting">
                <span x-show="!submitting">{{ __('Masuk') }}</span>
                <span x-show="submitting" x-cloak>{{ __('Memproses...') }}</span>
            </x-btn>
        </form>

        @if (Route::has('register'))
            <div class="space-x-1 text-center text-sm text-body-2 rtl:space-x-reverse dark:text-zinc-400">
                <span>{{ __('Belum punya akun?') }}</span>
                <flux:link :href="route('register')" wire:navigate>{{ __('Daftar') }}</flux:link>
            </div>
        @endif
    </div>
</x-layouts::auth>
