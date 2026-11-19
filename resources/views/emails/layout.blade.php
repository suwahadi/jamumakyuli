@php
    $siteName = setting('site_name', config('services.brevo.sender_name', 'Jamu Mak Yuli'));
@endphp
<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $siteName }}</title>
</head>
<body style="margin:0; padding:0; background-color:{{ $cream }}; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:{{ $cream }};">
        @yield('preheader')
        &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:{{ $cream }};">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; font-family:Arial,Helvetica,sans-serif;">

                    <tr>
                        <td style="background-color:{{ $dark }}; border-radius:12px 12px 0 0; padding:22px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <span style="font-size:19px; font-weight:800; letter-spacing:-0.01em; color:#ffffff;">{{ $siteName }}</span>
                                    </td>
                                    <td align="right" style="vertical-align:middle;">
                                        <span style="display:inline-block; background-color:#ffffff; color:{{ $dark }}; font-size:11px; font-weight:800; letter-spacing:0.04em; text-transform:uppercase; padding:5px 12px; border-radius:999px;">@yield('eyebrow', 'Notifikasi Pesanan')</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#ffffff; padding:30px 28px;">
                            <h1 style="margin:0 0 16px; font-size:21px; line-height:1.3; font-weight:800; letter-spacing:-0.01em; color:{{ $ink }};">@yield('heading')</h1>
                            @yield('content')
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:{{ $creamDeep }}; border-top:1px solid {{ $line }}; border-radius:0 0 12px 12px; padding:18px 28px; text-align:center;">
                            <p style="margin:0; font-size:11px; line-height:1.6; color:{{ $muted }};">
                                Email ini dikirim otomatis oleh sistem. Mohon tidak membalas email ini.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
