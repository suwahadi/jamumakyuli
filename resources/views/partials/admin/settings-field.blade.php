@php $type = $field['type'] ?? 'input'; @endphp
<div @class(['sm:col-span-2' => $type === 'textarea'])>
    <label for="setting-{{ $key }}" class="lbl">{{ $field['label'] }}@if ($required ?? false) <span class="req">*</span>@endif</label>
    @if ($type === 'textarea')
        <textarea id="setting-{{ $key }}" wire:model="values.{{ $key }}" rows="3" class="field"></textarea>
    @else
        @if ($field['readonly'] ?? false)
            <input type="text" id="setting-{{ $key }}" wire:model="values.{{ $key }}" readonly class="field cursor-not-allowed bg-gray-50 text-gray-500 dark:bg-zinc-800 dark:text-zinc-400">
        @else
            <input type="text" id="setting-{{ $key }}" wire:model="values.{{ $key }}" class="field">
        @endif
    @endif
    @if (! empty($field['hint']))
        <p class="mt-1.5 text-xs text-gray-500 dark:text-zinc-400">{{ $field['hint'] }}</p>
    @endif
    <flux:error name="values.{{ $key }}" />
</div>
