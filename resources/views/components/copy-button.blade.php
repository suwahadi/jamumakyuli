@props(['text', 'label' => 'Salin'])

<button type="button"
        x-data="{ disalin: false, teks: @js($text) }"
        x-on:click="
            const tandai = () => { disalin = true; setTimeout(() => disalin = false, 2000) };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(teks).then(tandai);
            } else {
                const kotak = document.createElement('textarea');
                kotak.value = teks;
                kotak.setAttribute('readonly', '');
                kotak.style.cssText = 'position:fixed;top:0;left:0;opacity:0';
                document.body.appendChild(kotak);
                kotak.select();
                document.execCommand('copy');
                kotak.remove();
                tandai();
            }
        "
        x-bind:title="disalin ? 'Tersalin' : '{{ $label }}'"
        x-bind:class="disalin ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 hover:text-brand-dark dark:text-zinc-400 dark:hover:text-zinc-100'"
        aria-label="{{ $label }}"
        {{ $attributes->merge(['class' => 'grid size-6 shrink-0 cursor-pointer place-items-center rounded-md transition hover:bg-gray-100 dark:hover:bg-zinc-800']) }}>
    <svg x-show="!disalin" class="size-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="9" y="9" width="11" height="11" rx="2" />
        <path d="M5 15V5a2 2 0 0 1 2-2h10" />
    </svg>
    <svg x-show="disalin" x-cloak class="size-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="m5 13 4 4L19 7" />
    </svg>
</button>
