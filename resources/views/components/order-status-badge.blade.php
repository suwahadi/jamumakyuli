@props(['status'])

@php
    $kelas = \App\Support\Tone::chip($status->color());
    $titik = \App\Support\Tone::dot($status->color());
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold $kelas"]) }}><span class="h-1.5 w-1.5 rounded-full {{ $titik }}"></span>{{ $status->label() }}</span>
