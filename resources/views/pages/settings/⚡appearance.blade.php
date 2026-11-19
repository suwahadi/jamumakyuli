<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Pengaturan Tampilan')] class extends Component {
}; ?>

<div>
    <x-pages::settings.layout>
        <x-settings.card title="Tema" description="Pilih tampilan terang, gelap, atau ikuti pengaturan perangkat">
            <div x-data role="radiogroup" aria-label="Tema tampilan" class="grid grid-cols-3 gap-2 sm:max-w-md">
                @foreach ([['light', 'sun', 'Terang'], ['dark', 'moon', 'Gelap'], ['system', 'computer-desktop', 'Sistem']] as [$value, $icon, $label])
                    <label class="flex min-h-11 cursor-pointer flex-col items-center justify-center gap-1.5 rounded-lg border border-zinc-200 px-2 py-3 text-sm font-medium text-zinc-600 transition-colors hover:border-zinc-300 hover:text-zinc-900 has-[:checked]:border-brand-green has-[:checked]:bg-brand-green/5 has-[:checked]:text-brand-dark has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-green/40 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100 dark:has-[:checked]:border-green-400 dark:has-[:checked]:bg-green-400/10 dark:has-[:checked]:text-zinc-100">
                        <input type="radio" name="appearance" value="{{ $value }}" x-model="$flux.appearance" class="sr-only">
                        <flux:icon :icon="$icon" class="size-5" />
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </x-settings.card>
    </x-pages::settings.layout>
</div>
