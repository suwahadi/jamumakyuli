<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Format;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function test_rupiah(): void
    {
        $this->assertSame('Rp 125.000', Format::rupiah(125000));
        $this->assertSame('Rp 0', Format::rupiah(0));
        $this->assertSame('Rp 1.250.000', Format::rupiah(1250000));
    }

    public function test_tanggal(): void
    {
        $this->assertSame('Kamis, 8 Okt 2026 16:22', Format::tanggal('2026-10-08 16:22:59'));
        $this->assertSame('Senin, 2 Feb 2026 08:05', Format::tanggal('2026-02-02 08:05:00'));
    }

    public function test_helpers_delegate(): void
    {
        $this->assertSame(Format::rupiah(90000), rupiah(90000));
        $this->assertSame(Format::tanggal('2026-10-08 16:22'), tanggal_id('2026-10-08 16:22'));
    }
}
