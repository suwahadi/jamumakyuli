<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use App\Support\AturanSku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AturanSkuTest extends TestCase
{
    use RefreshDatabase;

    // Fitur varian disembunyikan lewat storefront.hidden.product_variants; test tetap
    // menjalankannya supaya aman saat dimunculkan kembali.
    protected function setUp(): void
    {
        parent::setUp();

        config(['storefront.hidden.product_variants' => false]);
    }

    private const FORM = 'pages::admin.products.form';

    private function produk(array $atribut = []): Product
    {
        return Product::factory()->inStock()->create(['category_id' => Category::factory()->create()->id, ...$atribut]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function varian(string $sku, string $nama, ?int $id = null): array
    {
        return ['id' => $id, 'name' => $nama, 'sku' => $sku, 'price' => 100000, 'promo_price' => null, 'stock' => 3, 'weight' => 500, 'image_key' => null];
    }

    public function test_pemakai_lain_menemukan_sku_di_produk_dan_varian(): void
    {
        $induk = $this->produk(['sku' => 'JMY-SRH-300']);
        $bervarian = $this->produk();
        Variant::factory()->create(['product_id' => $bervarian->id, 'sku' => 'JMY-SRH-350']);

        $this->assertTrue($induk->is(AturanSku::pemakaiLain('JMY-SRH-300', null)));
        $this->assertTrue($bervarian->is(AturanSku::pemakaiLain(' JMY-SRH-350 ', null)));
        $this->assertNull(AturanSku::pemakaiLain('JMY-SRH-300', $induk->id));
        $this->assertNull(AturanSku::pemakaiLain('TIDAK-ADA', null));
    }

    public function test_form_menolak_sku_produk_yang_dipakai_varian_produk_lain(): void
    {
        $lain = $this->produk(['name' => 'Jamu Sirih Merah']);
        Variant::factory()->create(['product_id' => $lain->id, 'sku' => 'JMY-SRH-300']);
        $product = $this->produk(['sku' => 'JMY-LAMA']);

        Livewire::actingAs($this->admin())
            ->test(self::FORM, ['product' => $product])
            ->set('sku', 'JMY-SRH-300')
            ->call('save')
            ->assertHasErrors('sku')
            ->assertSee('Jamu Sirih Merah');

        $this->assertSame('JMY-LAMA', $product->fresh()->sku);
    }

    public function test_form_menolak_sku_varian_yang_dipakai_produk_lain_dan_menyebut_namanya(): void
    {
        $this->produk(['sku' => 'JMY-SRH-300', 'name' => 'Jamu Sirih Hijau']);
        $product = $this->produk();

        Livewire::actingAs($this->admin())
            ->test(self::FORM, ['product' => $product])
            ->set('variants', [$this->varian('JMY-SRH-300', 'KANAN')])
            ->call('save')
            ->assertHasErrors('variants.0.sku')
            ->assertSee('Jamu Sirih Hijau');

        $this->assertSame(0, $product->variants()->count());
    }

    public function test_form_mengizinkan_kanan_kiri_ber_sku_sama_dalam_satu_produk(): void
    {
        $product = $this->produk();

        Livewire::actingAs($this->admin())
            ->test(self::FORM, ['product' => $product])
            ->set('variants', [$this->varian('999ABC-01', 'KANAN'), $this->varian('999ABC-01', 'KIRI')])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, $product->variants()->count());
    }

    public function test_form_menolak_sku_dan_nama_varian_kembar_dalam_satu_produk(): void
    {
        $product = $this->produk();

        Livewire::actingAs($this->admin())
            ->test(self::FORM, ['product' => $product])
            ->set('variants', [$this->varian('999ABC-01', 'KANAN'), $this->varian('999abc-01', 'kanan')])
            ->call('save')
            ->assertHasErrors('variants.1.sku');

        $this->assertSame(0, $product->variants()->count());
    }

    // Sebelum tahap 2 selesai masih ada SKU yang ada di beberapa produk; admin
    // tidak boleh terhalang mengubah hal lain pada produk seperti itu.
    public function test_sku_lama_yang_masih_dipakai_bersama_tetap_bisa_disimpan_bila_tidak_diubah(): void
    {
        $lain = $this->produk();
        Variant::factory()->create(['product_id' => $lain->id, 'sku' => 'JMY-SRH-300', 'name' => 'KANAN']);
        $product = $this->produk();
        Variant::factory()->create(['product_id' => $product->id, 'sku' => 'JMY-SRH-300', 'name' => 'KANAN']);

        Livewire::actingAs($this->admin())
            ->test(self::FORM, ['product' => $product])
            ->set('name', 'Judul Baru')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Judul Baru', $product->fresh()->name);
    }
}
