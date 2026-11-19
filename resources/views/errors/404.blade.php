@extends('errors.layout')

@section('code', '404')
@section('title', __('Halaman tidak ditemukan'))
@section('message', __('Tautan ini mungkin sudah lama atau produknya sudah tidak dijual. Coba cari jamu yang Anda butuhkan.'))

@section('extra')
    <form action="{{ route('products.index') }}" method="get" role="search" class="mt-6 flex w-full overflow-hidden rounded-md border-2 border-brand-dark focus-within:border-brand-green dark:border-zinc-600">
        <label for="cari-404" class="sr-only">{{ __('Cari jamu') }}</label>
        <input id="cari-404" type="search" name="q" placeholder="{{ __('Cari jamu atau ramuan...') }}"
               class="min-h-11 min-w-0 flex-1 bg-transparent px-4 text-sm text-ink placeholder:text-muted dark:text-zinc-100 dark:placeholder:text-zinc-400" />
        <button type="submit" class="grid min-h-11 w-12 shrink-0 place-items-center bg-brand-green text-white hover:bg-brand-green-dark" aria-label="{{ __('Cari') }}">
            <svg class="size-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
        </button>
    </form>
@endsection
