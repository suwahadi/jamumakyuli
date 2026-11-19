<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;

class Format
{
    public static function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    public static function tanggal(mixed $date): string
    {
        return Carbon::parse($date)->locale('id')->isoFormat('dddd, D MMM YYYY HH:mm');
    }
}
