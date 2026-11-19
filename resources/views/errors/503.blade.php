@extends('errors.layout')

@section('code', '503')
@section('title', __('Toko sedang dalam pemeliharaan'))
@section('message', __('Kami sedang memperbarui toko. Silakan kembali beberapa menit lagi.'))

@section('actions')
    <x-btn variant="primary" onclick="window.location.reload()">{{ __('Muat Ulang Halaman') }}</x-btn>
@endsection
