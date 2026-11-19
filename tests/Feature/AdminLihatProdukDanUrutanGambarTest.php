<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLihatProdukDanUrutanGambarTest extends TestCase
{
    use RefreshDatabase;

    private const FORM = 'pages::admin.products.form';

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function produk(array $atribut = []): Product
    {
        return Product::factory()->inStock()->create(['category_id' => Category::factory()->create()->id, ...$atribut]);
    }

    private function tautanToko(Product $product): string
    {
        return 'href="'.route('products.show', $product).'"';
    }

    public function test_daftar_produk_punya_tautan_lihat_di_toko_pada_tab_baru(): void
    {
        $aktif = $this->produk(['is_active' => true]);

        $html = Livewire::actingAs($this->admin())->test('pages::admin.products.index')->html();

        $this->assertStringContainsString($this->tautanToko($aktif), $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('Lihat produk di toko', $html);
    }

    // Produk nonaktif menghasilkan 404 di toko, jadi tautannya tidak ditawarkan.
    public function test_produk_nonaktif_tidak_punya_tautan_lihat_di_toko(): void
    {
        $nonaktif = $this->produk(['is_active' => false]);

        $html = Livewire::actingAs($this->admin())->test('pages::admin.products.index')->html();

        $this->assertStringNotContainsString($this->tautanToko($nonaktif), $html);
    }

    public function test_halaman_ubah_produk_aktif_punya_tombol_lihat_produk(): void
    {
        $product = $this->produk(['is_active' => true]);

        $html = Livewire::actingAs($this->admin())->test(self::FORM, ['product' => $product])->html();

        $this->assertStringContainsString('Lihat Produk', $html);
        $this->assertStringContainsString($this->tautanToko($product), $html);
    }

    public function test_tombol_lihat_produk_tidak_tampil_untuk_produk_nonaktif_dan_produk_baru(): void
    {
        $nonaktif = $this->produk(['is_active' => false]);

        Livewire::actingAs($this->admin())->test(self::FORM, ['product' => $nonaktif])->assertDontSee('Lihat Produk');
        Livewire::actingAs($this->admin())->test(self::FORM)->assertDontSee('Lihat Produk');
    }

    public function test_urutan_gambar_hasil_geser_tersimpan(): void
    {
        $product = $this->produk();
        [$a, $b, $c] = collect(['a', 'b', 'c'])->map(fn (string $n, int $i) => $product->images()->create([
            'path' => "products/{$n}.webp", 'is_main' => $i === 0, 'sort_order' => $i,
        ]))->all();

        Livewire::actingAs($this->admin())
            ->test(self::FORM, ['product' => $product])
            ->call('sortImages', "id:{$c->id}", 0)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([$c->id, $a->id, $b->id], $product->images()->pluck('id')->all());
        $this->assertTrue($a->fresh()->is_main);
    }

    public function test_foto_baru_bisa_digeser_ke_depan_sebelum_disimpan(): void
    {
        Storage::fake('public');
        $product = $this->produk();
        $lama = $product->images()->create(['path' => 'products/lama.webp', 'is_main' => true, 'sort_order' => 0]);

        $komponen = Livewire::actingAs($this->admin())
            ->test(self::FORM, ['product' => $product])
            ->set('photos', [UploadedFile::fake()->image('baru.jpg', 40, 40)]);

        $kunciBaru = 'tmp:'.$komponen->get('photos')[0]->getFilename();

        $komponen->call('sortImages', $kunciBaru, 0)->call('save')->assertHasNoErrors();

        $urutan = $product->images()->get();
        $this->assertCount(2, $urutan);
        $this->assertNotSame($lama->id, $urutan[0]->id);
        $this->assertSame($lama->id, $urutan[1]->id);
    }
}
