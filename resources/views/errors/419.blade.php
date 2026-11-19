@extends('errors.layout')

@section('code', '419')
@section('title', __('Sesi sudah berakhir'))
@section('message', __('Halaman terlalu lama dibiarkan terbuka. Muat ulang halaman, lalu ulangi langkah terakhir Anda.'))

@section('actions')
    <x-btn variant="primary" onclick="window.location.reload()">{{ __('Muat Ulang Halaman') }}</x-btn>
    <x-btn variant="dark" :href="url('/')">{{ __('Ke Beranda') }}</x-btn>
@endsection
