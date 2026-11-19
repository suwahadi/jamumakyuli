<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkemaVarianTest extends TestCase
{
    use RefreshDatabase;

    public function test_sku_varian_boleh_sama_dalam_satu_produk_bila_nama_beda(): void
    {
        $p = Product::factory()->create();
        Variant::factory()->create(['product_id' => $p->id, 'sku' => '999ABC-01', 'name' => 'KANAN']);
        Variant::factory()->create(['product_id' => $p->id, 'sku' => '999ABC-01', 'name' => 'KIRI']);

        $this->assertSame(2, $p->variants()->count());
    }

    public function test_sku_dan_nama_varian_tidak_boleh_kembar_dalam_satu_produk(): void
    {
        $p = Product::factory()->create();
        Variant::factory()->create(['product_id' => $p->id, 'sku' => '999ABC-01', 'name' => 'KANAN']);

        $this->expectException(QueryException::class);

        Variant::factory()->create(['product_id' => $p->id, 'sku' => '999ABC-01', 'name' => 'KANAN']);
    }
}
