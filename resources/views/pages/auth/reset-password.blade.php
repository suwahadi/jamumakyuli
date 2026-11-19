<x-layouts::auth :title="__('Atur ulang kata sandi')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Atur ulang kata sandi')" :description="__('Masukkan kata sandi baru Anda di bawah')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" novalidate action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <flux:input
                name="email"
                value="{{ request('email') }}"
                :label="__('Alamat email')"
                type="email"
                required
                autocomplete="email"
            />

            <flux:input
                name="password"
                :label="__('Kata sandi')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Kata sandi')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <flux:input
                name="password_confirmation"
                :label="__('Konfirmasi kata sandi')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Konfirmasi kata sandi')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <div class="flex items-center justify-end">
                <x-btn type="submit" variant="primary" class="w-full" data-test="reset-password-button">
                    {{ __('Atur ulang kata sandi') }}
                </x-btn>
            </div>
        </form>
    </div>
</x-layouts::auth>
