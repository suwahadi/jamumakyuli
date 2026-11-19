@props(['label' => null])

<template x-teleport="body">
    <div x-show="isOpen" x-cloak x-transition.opacity.duration.200ms
         class="fixed inset-0 z-[120] flex flex-col bg-ink/95 backdrop-blur-sm"
         role="dialog" aria-modal="true" aria-label="{{ $label ?? __('Galeri gambar produk') }}"
         @keydown.window.escape="isOpen && close()"
         @keydown.window.arrow-right="isOpen && next()"
         @keydown.window.arrow-left="isOpen && prev()"
         @keydown.window="isOpen && onKey($event)">

        <div class="flex items-center gap-2 p-4 text-white">
            <span x-show="images.length > 1" class="rounded-full bg-white/10 px-3 py-1 text-sm font-medium">
                <span x-text="index + 1"></span> / <span x-text="images.length"></span>
            </span>
            <div class="ml-auto flex items-center rounded-full bg-white/10">
                <button type="button" @click="zoomOut()" aria-label="{{ __('Perkecil') }}" :disabled="zoom.s <= 1"
                        class="grid size-11 place-items-center rounded-full transition hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent">
                    <flux:icon.minus class="size-5" />
                </button>
                <span class="w-12 text-center text-sm font-medium tabular-nums" aria-live="polite" x-text="Math.round(zoom.s * 100) + '%'"></span>
                <button type="button" @click="zoomIn()" aria-label="{{ __('Perbesar') }}" :disabled="zoom.s >= maxZoom() - 0.01"
                        class="grid size-11 place-items-center rounded-full transition hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent">
                    <flux:icon.plus class="size-5" />
                </button>
            </div>
            <button type="button" x-ref="lightboxClose" @click="close()" aria-label="{{ __('Tutup') }}"
                    class="grid size-11 place-items-center rounded-full bg-white/10 transition hover:bg-white/20">
                <flux:icon.x-mark class="size-6" />
            </button>
        </div>

        <div class="relative flex flex-1 touch-none items-center justify-center overflow-hidden px-4 pb-4"
             @touchstart.passive="onTouchStart($event)" @touchmove.passive="onTouchMove($event)" @touchend.passive="onTouchEnd($event)">
            <div class="absolute inset-0" @click="close()"></div>

            <button type="button" @click="prev()" aria-label="{{ __('Sebelumnya') }}" x-show="images.length > 1"
                    class="absolute left-3 top-1/2 z-10 grid size-12 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20 sm:left-6">
                <flux:icon.chevron-left class="size-7" />
            </button>

            <img x-ref="lightboxImg" :src="images[index]" alt="" draggable="false"
                 :style="imgStyle()"
                 :class="{ 'transition-transform duration-200': animating, 'cursor-grab': zoom.s > 1 && gesture?.type !== 'drag', 'cursor-grabbing': gesture?.type === 'drag' }"
                 @pointerdown="onPointerDown($event)" @pointermove="onPointerMove($event)" @pointerup="onPointerUp()" @pointercancel="onPointerUp()" @lostpointercapture="onPointerUp()"
                 class="relative max-h-full max-w-full select-none rounded-lg object-contain shadow-2xl" />

            <button type="button" @click="next()" aria-label="{{ __('Berikutnya') }}" x-show="images.length > 1"
                    class="absolute right-3 top-1/2 z-10 grid size-12 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20 sm:right-6">
                <flux:icon.chevron-right class="size-7" />
            </button>
        </div>

        <div class="flex items-center justify-center gap-2 p-5" x-show="images.length > 1">
            <template x-for="(src, i) in images" :key="i">
                <button type="button" @click="go(i)" :aria-label="@js(__('Ke gambar')) + ' ' + (i + 1)"
                        class="grid h-11 place-items-center px-1">
                    <span class="block h-2.5 rounded-full transition-all duration-300"
                          :class="index === i ? 'w-7 bg-primary' : 'w-2.5 bg-white/50 hover:bg-white/80'"></span>
                </button>
            </template>
        </div>
    </div>
</template>
