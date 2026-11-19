<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class FormProdukBervarianTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENT = 'pages::admin.products.form';

    private function formBaru(): Testable
    {
        return Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(self::COMPONENT)
            ->set('category_id', Category::factory()->create()->id)
            ->set('name', 'Lampu Spion Uji')
            ->set('description', 'Kiri dan kanan.');
    }

    private function varian(): array
    {
        return [
            ['id' => null, 'name' => 'Kiri', 'sku' => '33550-T5L-K', 'price' => 450000, 'promo_price' => null, 'stock' => 3, 'weight' => 400, 'image_key' => null],
            ['id' => null, 'name' => 'Kanan', 'sku' => '33550-T5L-N', 'price' => 420000, 'promo_price' => null, 'stock' => 2, 'weight' => 450, 'image_key' => null],
        ];
    }

    public function test_produk_bervarian_boleh_tanpa_sku_harga_berat_dan_stok_induk(): void
    {
        $this->formBaru()
            ->set('sku', '')
            ->set('original_price', null)
            ->set('weight', null)
            ->set('stock', null)
            ->set('variants', $this->varian())
            ->call('save')
            ->assertHasNoErrors();

        $produk = Product::query()->where('name', 'Lampu Spion Uji')->firstOrFail();

        $this->assertSame('33550-T5L-K', $produk->sku);
        $this->assertSame(420000, $produk->original_price);
        $this->assertSame(400, $produk->weight);
        $this->assertSame(0, $produk->stock);
        $this->assertSame(5, $produk->totalStock());
    }

    public function test_sku_induk_yang_diisi_tetap_dipakai_pada_produk_bervarian(): void
    {
        $this->formBaru()
            ->set('sku', 'SPION-INDUK')
            ->set('variants', $this->varian())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Product::query()->where('sku', 'SPION-INDUK')->exists());
    }

    public function test_harga_promo_induk_ditolak_bila_tidak_di_bawah_harga_varian_termurah(): void
    {
        $this->formBaru()
            ->set('sku', '')
            ->set('original_price', null)
            ->set('promo_price', 420000)
            ->set('weight', null)
            ->set('variants', $this->varian())
            ->call('save')
            ->assertHasErrors(['promo_price' => 'lt']);

        $this->assertFalse(Product::query()->where('name', 'Lampu Spion Uji')->exists());
    }

    public function test_harga_promo_induk_di_bawah_harga_varian_termurah_diterima(): void
    {
        $this->formBaru()
            ->set('sku', '')
            ->set('original_price', null)
            ->set('promo_price', 400000)
            ->set('weight', null)
            ->set('variants', $this->varian())
            ->call('save')
            ->assertHasNoErrors();

        $produk = Product::query()->where('name', 'Lampu Spion Uji')->firstOrFail();

        $this->assertSame(420000, $produk->original_price);
        $this->assertSame(400000, $produk->promo_price);
    }

    public function test_produk_tanpa_varian_tetap_wajib_sku_harga_dan_berat(): void
    {
        $this->formBaru()
            ->set('sku', '')
            ->set('original_price', null)
            ->set('weight', null)
            ->set('variants', [])
            ->call('save')
            ->assertHasErrors(['sku', 'original_price', 'weight']);
    }

    public function test_kotak_varian_tepat_di_bawah_kotak_harga_dan_stok(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(self::COMPONENT)
            ->assertSeeInOrder(['Informasi Dasar', 'Harga dan Stok', 'Varian (opsional)', 'Gambar']);
    }

    public function test_keterangan_sku_ada_di_label_bukan_di_bawah_isian(): void
    {
        $html = $this->formBaru()->set('variants', $this->varian())->html();

        $this->assertMatchesRegularExpression('/<ui-label[^>]*>.*SKU Produk \(induk\).*- Boleh dikosongkan, diisi otomatis dari SKU varian pertama\..*<\/ui-label>/s', $html);
        $this->assertStringNotContainsString('Boleh dikosongkan. Diisi otomatis', $html);
        $this->assertStringNotContainsString('pre_order_days', $html);
    }

    public function test_keterangan_sku_tidak_tampil_pada_produk_tanpa_varian(): void
    {
        $html = $this->formBaru()->set('variants', [])->html();

        $this->assertStringContainsString('SKU Produk (induk)', $html);
        $this->assertStringNotContainsString('Boleh dikosongkan', $html);
    }

    public function test_tombol_tambah_dan_hapus_varian_berikon_dan_berukuran_normal(): void
    {
        $html = $this->formBaru()->set('variants', $this->varian())->html();

        $tombol = function (string $aksi) use ($html): string {
            preg_match('/<button(?=[^>]*wire:click="'.preg_quote($aksi, '/').'")[^>]*>.*?<\/button>/s', $html, $cocok);

            return $cocok[0] ?? '';
        };

        $tambah = $tombol('addVariant');
        $hapus = $tombol('removeVariant(0)');

        $this->assertStringContainsString('data-flux-icon', $tambah);
        $this->assertStringContainsString('h-8', $tambah);
        $this->assertStringContainsString('Tambah Varian', $tambah);
        $this->assertStringContainsString('data-flux-icon', $hapus);
        $this->assertStringContainsString('text-red-600', $hapus);
        $this->assertStringContainsString('Hapus Varian', $hapus);
        $this->assertSame(2, substr_count($html, 'Hapus Varian'));
    }
}
