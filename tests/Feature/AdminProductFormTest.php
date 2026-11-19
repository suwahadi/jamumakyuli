<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProductFormTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENT = 'pages::admin.products.form';

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function category(): Category
    {
        return Category::factory()->create(['name' => 'Office']);
    }

    public function test_simpan_produk_yang_sudah_ada_tetap_di_halaman_ubah(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category()->id, 'promo_price' => null]);

        Livewire::actingAs($this->admin())
            ->test(self::COMPONENT, ['product' => $product])
            ->set('name', 'Nama Produk Diubah')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.products.edit', $product));
    }

    public function test_simpan_produk_baru_kembali_ke_daftar_produk(): void
    {
        Livewire::actingAs($this->admin())
            ->test(self::COMPONENT)
            ->set('category_id', $this->category()->id)
            ->set('name', 'Produk Baru Uji')
            ->set('sku', 'JMY-UJI-BARU')
            ->set('original_price', 100000)
            ->set('description', 'Deskripsi.')
            ->set('weight', 100)
            ->set('stock', 1)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.products.index'));
    }

    public function test_admin_menyimpan_produk_baru_tanpa_varian(): void
    {
        $category = $this->category();

        Livewire::actingAs($this->admin())
            ->test(self::COMPONENT)
            ->set('category_id', $category->id)
            ->set('name', 'Jamu Kunyit Uji')
            ->set('sku', 'JMY-UJI-001')
            ->set('original_price', 500000)
            ->set('promo_price', 450000)
            ->set('description', 'Deskripsi produk uji.')
            ->set('weight', 12000)
            ->set('stock', 7)
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::query()->where('sku', 'JMY-UJI-001')->first();

        $this->assertNotNull($product);
        $this->assertSame('Jamu Kunyit Uji', $product->name);
        $this->assertSame('jamu-kunyit-uji', $product->slug);
        $this->assertSame(450000, $product->promo_price);
        $this->assertSame(7, $product->stock);
        $this->assertTrue($product->is_active);
    }

    // Formulir produk tinggal empat kartu: Informasi Dasar, Harga dan Stok, Gambar, Varian.
    public function test_formulir_tidak_lagi_punya_addon_dan_pengaturan_lanjutan(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category()->id]);

        $html = Livewire::actingAs($this->admin())
            ->test(self::COMPONENT, ['product' => $product])
            ->html();

        $this->assertStringNotContainsString('Addon', $html);
        $this->assertStringNotContainsString('Pengaturan Lanjutan', $html);
        $this->assertStringContainsString('Varian', $html);
    }

    public function test_admin_mengubah_produk_yang_sudah_ada(): void
    {
        $product = Product::factory()->inStock()->create([
            'category_id' => $this->category()->id,
            'name' => 'Nama Lama',
            'sku' => 'JMY-LAMA-001',
        ]);

        Livewire::actingAs($this->admin())
            ->test(self::COMPONENT, ['product' => $product])
            ->assertSet('name', 'Nama Lama')
            ->set('name', 'Nama Baru')
            ->set('stock', 3)
            ->call('save')
            ->assertHasNoErrors();

        $product->refresh();

        $this->assertSame('Nama Baru', $product->name);
        $this->assertSame(3, $product->stock);
        $this->assertSame('nama-baru', $product->slug);
    }

    public function test_menyunting_bidang_lain_tidak_menulis_ulang_slug_yang_diatur_manual(): void
    {
        $product = Product::factory()->inStock()->create([
            'category_id' => $this->category()->id,
            'name' => 'Jamu Kunyit',
            'slug' => 'jamu-kunyit-premium-2',
            'sku' => 'JMY-TETAP-001',
        ]);

        Livewire::actingAs($this->admin())
            ->test(self::COMPONENT, ['product' => $product])
            ->set('stock', 42)
            ->call('save')
            ->assertHasNoErrors();

        $product->refresh();

        $this->assertSame(42, $product->stock);
        $this->assertSame('jamu-kunyit-premium-2', $product->slug, 'URL publik produk berubah padahal namanya tidak.');
    }

    public function test_admin_menyimpan_produk_dengan_varian(): void
    {
        $category = $this->category();

        Livewire::actingAs($this->admin())
            ->test(self::COMPONENT)
            ->set('category_id', $category->id)
            ->set('name', 'Jamu Kunyit Bervarian')
            ->set('sku', 'JMY-VAR-001')
            ->set('original_price', 700000)
            ->set('promo_price', null)
            ->set('description', 'Punya dua ukuran.')
            ->set('weight', 15000)
            ->set('stock', 0)
            ->set('variants', [
                ['id' => null, 'name' => 'Standar', 'sku' => 'JMY-VAR-001-A', 'price' => 700000, 'promo_price' => null, 'stock' => 4, 'weight' => 15000, 'image_key' => null],
                ['id' => null, 'name' => 'Heavy Duty', 'sku' => 'JMY-VAR-001-B', 'price' => 800000, 'promo_price' => null, 'stock' => 2, 'weight' => 17000, 'image_key' => null],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::query()->where('sku', 'JMY-VAR-001')->firstOrFail();
        $variants = Variant::query()->where('product_id', $product->id)->orderBy('id')->get();

        $this->assertCount(2, $variants);
        $this->assertSame('Standar', $variants[0]->name);
        $this->assertSame(2, $variants[1]->stock);
        $this->assertSame(6, $product->totalStock());
    }

    public function test_varian_yang_pernah_diopname_tidak_bisa_dihapus_lewat_formulir(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->inStock()->create([
            'category_id' => $this->category()->id,
            'sku' => 'JMY-OPNAME-001',
        ]);
        $variant = Variant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'JMY-OPNAME-001-A',
            'stock' => 5,
        ]);

        StockOpname::query()->create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'user_id' => $admin->id,
            'system_stock' => 5,
            'physical_stock' => 4,
        ]);

        $uji = Livewire::actingAs($admin)
            ->test(self::COMPONENT, ['product' => $product])
            ->set('variants', [])
            ->call('save')
            ->assertHasErrors('variants');

        // percuma masuk error bag kalau tidak ada yang mencetaknya di formulir
        $this->assertStringContainsString(
            'Varian yang pernah dihitung di stok opname tidak bisa dihapus.',
            $uji->html(),
        );

        $this->assertNotNull($variant->fresh(), 'varian terhapus padahal jejak opname-nya masih ada.');
    }

    public function test_penolakan_hapus_varian_tidak_ikut_menyimpan_perubahan_lain(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->inStock()->create([
            'category_id' => $this->category()->id,
            'sku' => 'JMY-OPNAME-002',
            'name' => 'Nama Asli',
        ]);
        $variant = Variant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'JMY-OPNAME-002-A',
            'stock' => 5,
        ]);

        StockOpname::query()->create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'user_id' => $admin->id,
            'system_stock' => 5,
            'physical_stock' => 4,
        ]);

        Livewire::actingAs($admin)
            ->test(self::COMPONENT, ['product' => $product])
            ->set('name', 'Nama Yang Diubah')
            ->set('variants', [])
            ->call('save')
            ->assertHasErrors('variants');

        // simpan ditolak, jadi tidak boleh ada satu pun bidang lain yang ikut tersimpan
        $this->assertSame('Nama Asli', $product->fresh()->name);
    }

    public function test_sku_ganda_ditolak(): void
    {
        Product::factory()->inStock()->create(['sku' => 'JMY-DUPE-001']);

        Livewire::actingAs($this->admin())
            ->test(self::COMPONENT)
            ->set('category_id', $this->category()->id)
            ->set('name', 'Produk Kembar')
            ->set('sku', 'JMY-DUPE-001')
            ->set('original_price', 100000)
            ->set('description', 'Deskripsi.')
            ->set('weight', 1000)
            ->set('stock', 1)
            ->call('save')
            ->assertHasErrors('sku');
    }

    public function test_harga_promo_harus_di_bawah_harga_asli(): void
    {
        Livewire::actingAs($this->admin())
            ->test(self::COMPONENT)
            ->set('category_id', $this->category()->id)
            ->set('name', 'Promo Keliru')
            ->set('sku', 'JMY-PROMO-001')
            ->set('original_price', 100000)
            ->set('promo_price', 150000)
            ->set('description', 'Deskripsi.')
            ->set('weight', 1000)
            ->set('stock', 1)
            ->call('save')
            ->assertHasErrors('promo_price');
    }

    // Admin mengisi form dari HP. Pesan galat harus memakai nama isian yang
    // dikenal admin, dan layar diarahkan ke galat pertama setelah Simpan.
    public function test_form_kosong_menampilkan_galat_berbahasa_indonesia_dan_meminta_gulir_ke_galat(): void
    {
        $this->category();

        $komponen = Livewire::actingAs($this->admin())
            ->test('pages::admin.products.form')
            ->call('save')
            ->assertHasErrors(['name', 'sku', 'original_price', 'weight'])
            ->assertDispatched('form-invalid');

        $html = $komponen->html();
        $this->assertStringContainsString('Nama produk wajib diisi.', $html);
        $this->assertStringContainsString('SKU produk wajib diisi.', $html);
        $this->assertStringNotContainsString('name wajib diisi.', $html);
    }
}
