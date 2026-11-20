@extends('emails.layout')

@section('eyebrow', 'Keamanan Akun')
@section('preheader', 'Tautan atur ulang kata sandi Anda berlaku '.$expireMinutes.' menit.')
@section('heading', 'Atur Ulang Kata Sandi')

@section('content')
    <x-email::text>
        @if ($nama)
            Halo <x-email::strong>{{ $nama }}</x-email::strong>, kami
        @else
            Kami
        @endif
        menerima permintaan untuk mengatur ulang kata sandi akun <x-email::strong>{{ $email }}</x-email::strong>.
        Tekan tombol di bawah untuk membuat kata sandi baru.
    </x-email::text>

    <x-email::callout tone="warning" label="Berlaku Sampai" :value="$expireMinutes.' menit setelah email ini dikirim'" />

    <x-email::button :href="$url">Atur Ulang Kata Sandi</x-email::button>

    <x-email::text size="small">
        Jika Anda tidak meminta pengaturan ulang kata sandi, abaikan email ini. Kata sandi Anda tidak akan berubah.
    </x-email::text>
@endsection
