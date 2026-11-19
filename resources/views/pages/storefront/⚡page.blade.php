<?php

use App\Models\Page;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::storefront')] class extends Component {
    public Page $page;

    public function mount(Page $page): void
    {
        abort_unless($page->is_active, 404);

        $this->page = $page;
    }

    #[Computed]
    public function faqItems(): array
    {
        if ($this->page->slug !== 'faq') {
            return [];
        }

        if (preg_match_all('#<p>\s*<strong>(.*?)</strong>\s*<br\s*/?\s*>(.*?)</p>#is', (string) $this->page->content, $matches, PREG_SET_ORDER) === 0) {
            return [];
        }

        return array_map(fn ($m) => ['question' => trim($m[1]), 'answer' => trim($m[2])], $matches);
    }

    public function rendering(View $view): void
    {
        $view->title($this->page->title);

        view()->share([
            'seoDescription' => (string) Str::of($this->page->content)->stripTags()->squish()->limit(160),
        ]);
    }
}; ?>

<div>
<x-page-header :title="$page->title" :items="[
    ['label' => __('Beranda'), 'url' => route('home')],
    ['label' => $page->title],
]" />

<x-container class="py-8 pb-24 lg:py-12 lg:pb-16">
    @if (count($this->faqItems))
        <div class="mt-8 space-y-3">
            @foreach ($this->faqItems as $index => $item)
                <details class="faq group rounded-lg border border-line bg-white dark:border-zinc-800 dark:bg-zinc-900" @if ($index === 0) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-[15px] font-bold text-[#1F2937] dark:text-zinc-100 sm:text-[16px]">
                        <span>{!! $item['question'] !!}</span>
                        <svg class="faq-chev h-5 w-5 shrink-0 fill-none stroke-primary stroke-2" aria-hidden="true"><use href="#i-chev"/></svg>
                    </summary>
                    <div class="px-5 pb-5 text-[15px] leading-relaxed text-body-2 dark:text-zinc-300">{!! $item['answer'] !!}</div>
                </details>
            @endforeach
        </div>
    @else
        <div class="mt-8 rounded-lg border border-line bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900 sm:p-8 lg:p-10">
            <article class="rich-text max-w-none">{!! $page->content !!}</article>

            @php
                $shareUrl = route('pages.show', $page->slug);
                $waShare = 'https://wa.me/?text='.rawurlencode($page->title.' - '.$shareUrl);
                $tgShare = 'https://t.me/share/url?url='.rawurlencode($shareUrl).'&text='.rawurlencode($page->title);
                $fbShare = 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($shareUrl);
                $xShare = 'https://twitter.com/intent/tweet?text='.rawurlencode($page->title).'&url='.rawurlencode($shareUrl);
            @endphp
            <div class="mt-8 flex items-center gap-2.5 border-t border-line pt-6 dark:border-zinc-800" x-data="{ copied: false, t: null }">
                <span class="flex items-center gap-1.5 text-xs font-medium text-body-2 dark:text-zinc-400">
                    <svg class="size-4 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-share"/></svg>
                    {{ __('Bagikan:') }}
                </span>

                <a href="{{ $waShare }}" target="_blank" rel="noopener" aria-label="{{ __('Bagikan ke WhatsApp') }}" title="WhatsApp"
                   class="grid size-9 place-items-center rounded-full border border-line text-body-2 transition hover:border-primary/40 hover:bg-primary/10 hover:text-primary dark:border-zinc-700 dark:text-zinc-400">
                    <flux:icon.whatsapp class="size-[18px]" />
                </a>

                <a href="{{ $tgShare }}" target="_blank" rel="noopener" aria-label="{{ __('Bagikan ke Telegram') }}" title="Telegram"
                   class="grid size-9 place-items-center rounded-full border border-line text-body-2 transition hover:border-primary/40 hover:bg-primary/10 hover:text-primary dark:border-zinc-700 dark:text-zinc-400">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="size-[18px]"><path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.21l15.97-6.16c.73-.27 1.37.17 1.13 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71l-4.07-3-1.96 1.9c-.22.22-.4.4-.83.4z"/></svg>
                </a>

                <a href="{{ $fbShare }}" target="_blank" rel="noopener" aria-label="{{ __('Bagikan ke Facebook') }}" title="Facebook"
                   class="grid size-9 place-items-center rounded-full border border-line text-body-2 transition hover:border-primary/40 hover:bg-primary/10 hover:text-primary dark:border-zinc-700 dark:text-zinc-400">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="size-[18px]"><path d="M22 12a10 10 0 1 0-11.5 9.87v-6.98H7.9V12h2.6V9.8c0-2.57 1.53-4 3.87-4 1.12 0 2.3.2 2.3.2v2.5h-1.3c-1.28 0-1.68.8-1.68 1.62V12h2.85l-.46 2.89h-2.39v6.98A10 10 0 0 0 22 12z"/></svg>
                </a>

                <a href="{{ $xShare }}" target="_blank" rel="noopener" aria-label="{{ __('Bagikan ke X') }}" title="X"
                   class="grid size-9 place-items-center rounded-full border border-line text-body-2 transition hover:border-primary/40 hover:bg-primary/10 hover:text-primary dark:border-zinc-700 dark:text-zinc-400">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="size-[18px]"><path d="M18.9 3H21l-6.6 7.5L22 21h-6.1l-4.8-6.3L5.4 21H3.3l7.1-8.1L2 3h6.2l4.3 5.8L18.9 3zm-1.1 16h1.7L7.3 5H5.5l12.3 14z"/></svg>
                </a>

                <button type="button" aria-label="{{ __('Salin tautan') }}" :title="copied ? @js(__('Tersalin')) : @js(__('Salin tautan'))"
                        @click="navigator.clipboard?.writeText(@js($shareUrl)); copied = true; clearTimeout(t); t = setTimeout(() => copied = false, 2000)"
                        class="grid size-9 place-items-center rounded-full border transition"
                        :class="copied ? 'border-primary/40 bg-primary/10 text-primary' : 'border-line text-body-2 hover:border-body-2/60 hover:text-ink dark:border-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-100'">
                    <span x-show="!copied"><flux:icon.link class="size-[18px]" /></span>
                    <span x-show="copied" x-cloak><flux:icon.check class="size-[18px]" /></span>
                </button>
            </div>
        </div>
    @endif
</x-container>
</div>
