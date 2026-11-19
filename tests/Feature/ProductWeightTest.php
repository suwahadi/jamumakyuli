<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCatalog;
use Tests\TestCase;

class ProductWeightTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCatalog;

    public function test_halaman_detail_menampilkan_berat_setelah_sku(): void
    {
        $produk = $this->seedCatalog();
        $produk->variants()->delete();
        $produk->update(['sku' => 'TKP-UJI-001', 'weight' => 1250]);

        $this->get(route('products.show', $produk->slug))
            ->assertOk()
            ->assertSeeInOrder(['SKU: TKP-UJI-001', '|', 'Berat: 1.250 (gram)']);
    }
}
