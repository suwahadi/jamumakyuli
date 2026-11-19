<x-layouts::auth :title="__('Konfirmasi kata sandi')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Konfirmasi kata sandi')"
            :description="__('Ini area terlindungi. Konfirmasi kata sandi Anda sebelum melanjutkan.')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />


        <form method="POST" novalidate action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf

            <flux:input
                name="password"
                :label="__('Kata sandi')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Kata sandi')"
                viewable
            />

            <x-btn variant="primary" type="submit" class="w-full" data-test="confirm-password-button" x-bind:disabled="submitting">
                <span x-show="!submitting">{{ __('Konfirmasi') }}</span>
                <span x-show="submitting" x-cloak>{{ __('Memproses...') }}</span>
            </x-btn>
        </form>
    </div>
</x-layouts::auth>
