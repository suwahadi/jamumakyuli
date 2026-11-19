@php
    $jaminan = [
        ['i-clock', __('Sejak 2020'), __('Berawal dari jamu rumahan')],
        ['i-users', __('1000+ pelanggan'), __('Pasutri yang berikhtiar bersama kami')],
        ['i-shield-check', __('100% herbal alami'), __('Rempah kering pilihan, direbus sendiri')],
        ['i-truck', __('Kirim ke seluruh Indonesia'), __('Lewat JNE dan J&T')],
    ];
@endphp

<section {{ $attributes->class('border-b border-gray-100 bg-white dark:border-zinc-800 dark:bg-zinc-900') }}>
    <div class="container-x grid grid-cols-2 divide-x divide-gray-100 dark:divide-zinc-800 lg:grid-cols-4">
        @foreach ($jaminan as [$icon, $judul, $keterangan])
            <div class="flex items-center gap-3 px-4 py-5">
                <svg class="h-7 w-7 shrink-0 fill-none stroke-brand-green stroke-2 text-brand-green" aria-hidden="true"><use href="#{{ $icon }}"/></svg>
                <div>
                    <p class="font-display text-sm font-medium leading-tight dark:text-zinc-100">{{ $judul }}</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-zinc-400">{{ $keterangan }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>
