<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="storefront min-h-screen bg-surface text-ink antialiased dark:bg-zinc-950 dark:text-zinc-200">
        @include('partials.storefront.icons')

        <main class="flex min-h-svh flex-col items-center justify-center px-4 py-8 sm:p-10">
            <div class="w-full max-w-[400px]">
                <div class="auth-card overflow-hidden rounded-xl border border-line bg-white shadow-lift dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-none dark:[--color-accent-content:#f87171]">
                    <a href="{{ route('home') }}" wire:navigate
                       class="group relative isolate flex h-32 items-end overflow-hidden border-b-[3px] border-brand-green bg-brand-dark px-6 pb-5 sm:h-36"
                       aria-label="{{ setting('site_name', 'Jamu Mak Yuli') }}, beranda">
                        <img src="{{ asset('assets/images/auth-rempah.webp') }}" alt="" width="960" height="640"
                             class="absolute inset-0 -z-10 h-full w-full object-cover object-[50%_45%] transition-transform duration-700 ease-out group-hover:scale-105 motion-reduce:transition-none">
                        <span class="absolute inset-0 -z-10 bg-gradient-to-t from-black via-black/70 to-black/25" aria-hidden="true"></span>
                        <x-wordmark size="text-3xl sm:text-4xl" accent="text-green-300" class="select-none text-white drop-shadow-[0_2px_6px_rgba(0,0,0,0.6)]" />
                    </a>
                    <div class="p-6 sm:p-7">
                        {{ $slot }}
                    </div>
                </div>

                <a href="{{ route('home') }}" wire:navigate
                   class="mt-3 flex min-h-11 items-center justify-center gap-1.5 text-sm font-medium text-body-2 transition-colors hover:text-primary dark:text-zinc-400 dark:hover:text-green-400">
                    <flux:icon.arrow-left class="size-4" /> {{ __('Kembali ke beranda') }}
                </a>
            </div>
        </main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
