<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use Tests\TestCase;

class StatusSelesaiTest extends TestCase
{
    /** Pesanan yang sudah sampai tetap pendapatan karena uangnya diterima sejak lunas. */
    public function test_status_selesai_tersedia_dan_dihitung_sebagai_pendapatan(): void
    {
        $this->assertSame('selesai', OrderStatus::DELIVERED->value);
        $this->assertSame('Pesanan Diterima', OrderStatus::DELIVERED->label());
        $this->assertNotSame('', OrderStatus::DELIVERED->color());
        $this->assertContains(OrderStatus::DELIVERED, OrderStatus::berpenghasilan());
    }

    public function test_nama_status_mengikuti_alur_diproses_dikirim_pesanan_diterima(): void
    {
        $this->assertSame('Diproses', OrderStatus::PAID->label());
        $this->assertSame('Dikirim', OrderStatus::SHIPPED->label());
        $this->assertSame('Pesanan Diterima', OrderStatus::DELIVERED->label());
        $this->assertSame('Dibatalkan', OrderStatus::CANCELLED->label());
    }
}
