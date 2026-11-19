@php
    $tabs = [
        ['label' => 'Profil', 'route' => 'profile.edit'],
        ['label' => 'Keamanan', 'route' => 'security.edit'],
        ['label' => 'Tampilan', 'route' => 'appearance.edit'],
    ];
@endphp

<div class="space-y-6">
    <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="px-6 pb-5 pt-6 sm:px-8 sm:pt-8">
            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-primary">Akun</p>
            <h1 class="mt-1.5 text-heading text-[28px] text-zinc-900 sm:text-[34px] dark:text-white">Pengaturan Akun</h1>
            <p class="mt-1 max-w-md text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">Kelola profil dan pengaturan akun Anda</p>
        </div>

        <nav aria-label="Pengaturan akun" class="flex gap-1 overflow-x-auto overflow-y-hidden border-t border-zinc-100 px-3 sm:px-5 dark:border-zinc-800">
            @foreach ($tabs as $tab)
                @php $active = request()->routeIs($tab['route']); @endphp
                <a href="{{ route($tab['route']) }}" wire:navigate aria-current="{{ $active ? 'page' : 'false' }}"
                   @class([
                       'inline-flex min-h-12 shrink-0 items-center border-b-2 px-3 text-sm transition-colors',
                       'border-brand-green font-semibold text-brand-dark dark:text-zinc-100' => $active,
                       'border-transparent font-medium text-zinc-500 hover:border-zinc-300 hover:text-zinc-900 dark:text-zinc-400 dark:hover:border-zinc-600 dark:hover:text-zinc-100' => ! $active,
                   ])>{{ $tab['label'] }}</a>
            @endforeach
        </nav>
    </section>

    {{ $slot }}
</div>
