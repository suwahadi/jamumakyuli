<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <title>@yield('title') | Jamu Mak Yuli</title>
    <script>
        try {
            const pilihan = localStorage.getItem('flux.appearance');
            if (pilihan === 'dark' || ((! pilihan || pilihan === 'system') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {}
    </script>
    @vite(['resources/css/app.css'])
</head>
<body class="storefront bg-white text-ink antialiased dark:bg-zinc-950 dark:text-zinc-200">
    <main class="mx-auto flex min-h-dvh max-w-xl flex-col items-center justify-center px-5 py-12 text-center">
        <a href="{{ url('/') }}" aria-label="{{ __('Jamu Mak Yuli, beranda') }}">
            <x-wordmark size="text-3xl" class="text-brand-dark dark:text-zinc-100" />
        </a>

        <p class="mt-12 font-display text-7xl font-black leading-none tracking-tight text-brand-green" aria-hidden="true">@yield('code')</p>
        <h1 class="mt-4 text-2xl font-bold text-ink dark:text-zinc-100 sm:text-3xl">@yield('title')</h1>
        <p class="mt-3 text-[15px] leading-relaxed text-body-2 dark:text-zinc-300">@yield('message')</p>

        @yield('extra')

        <div class="mt-8 grid w-full grid-cols-[repeat(auto-fit,minmax(0,1fr))] gap-3">
            @section('actions')
                <x-btn variant="primary" :href="url('/')">{{ __('Ke Beranda') }}</x-btn>
                <x-btn variant="dark" :href="route('products.index')">{{ __('Lihat Katalog') }}</x-btn>
            @show
        </div>
    </main>
</body>
</html>
