<button type="button" x-data="{ isDark: document.documentElement.classList.contains('dark') }" role="switch"
        :aria-checked="isDark ? 'true' : 'false'"
        @click="isDark = ! isDark; $flux.appearance = isDark ? 'dark' : 'light'"
        aria-label="{{ __('Mode gelap') }}" title="{{ __('Mode terang atau gelap') }}"
        {{ $attributes->class(['relative h-7 w-[52px] shrink-0 items-center rounded-full p-0.5 transition-colors duration-300']) }}
        :class="isDark ? 'bg-gray-300' : 'bg-brand-green'">
    <span class="grid h-6 w-6 place-items-center rounded-full bg-white shadow-md transition-transform duration-300"
          :class="isDark ? 'translate-x-0' : 'translate-x-[24px]'">
        <svg x-show="! isDark" class="h-3.5 w-3.5 fill-none stroke-brand-green stroke-2" aria-hidden="true"><use href="#i-sun"/></svg>
        <svg x-cloak x-show="isDark" class="h-3.5 w-3.5 fill-none stroke-gray-600 stroke-2" aria-hidden="true"><use href="#i-moon"/></svg>
    </span>
</button>
