@extends('emails.layout')

@section('eyebrow', 'Keamanan Akun')
@section('preheader', 'Tautan atur ulang kata sandi Anda berlaku '.$expireMinutes.' menit.')
@section('heading', 'Atur Ulang Kata Sandi')

@section('content')
    <p style="margin:0 0 16px; font-size:14px; line-height:1.7; color:{{ $muted }};">
        @if ($nama)
            Halo <strong style="color:{{ $ink }};">{{ $nama }}</strong>, kami
        @else
            Kami
        @endif
        menerima permintaan untuk mengatur ulang kata sandi akun
        <strong style="color:{{ $ink }};">{{ $email }}</strong>. Tekan tombol di bawah untuk membuat kata sandi baru.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
        <tr>
            <td style="background-color:{{ $accent }}; border-radius:8px;">
                <a href="{{ $url }}" target="_blank"
                   style="display:inline-block; padding:13px 30px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none;">
                    Atur Ulang Kata Sandi
                </a>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; background-color:{{ $warningBg }}; border:1px solid {{ $warningBorder }}; border-radius:8px;">
        <tr>
            <td style="padding:14px 16px;">
                <span style="font-size:12px; color:{{ $warningInk }};">Berlaku Sampai</span><br>
                <span style="font-size:15px; font-weight:700; color:{{ $ink }};">{{ $expireMinutes }} menit setelah email ini dikirim</span>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px; font-size:13px; line-height:1.7; color:{{ $muted }};">
        Jika Anda tidak meminta pengaturan ulang kata sandi, abaikan email ini. Kata sandi Anda tidak akan berubah.
    </p>

    <p style="margin:0; font-size:13px; line-height:1.7; color:{{ $muted }};">
        Jika tombol di atas tidak berfungsi, salin tautan berikut ke peramban Anda:<br>
        <a href="{{ $url }}" style="color:{{ $accentDark }}; word-break:break-all;">{{ $url }}</a>
    </p>
@endsection
