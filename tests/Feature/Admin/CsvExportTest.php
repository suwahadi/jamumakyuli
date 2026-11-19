<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use App\Services\Csv\ProductCsv;
use App\Services\Csv\UserCsv;
use App\Support\Xlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Concerns\SeedsCatalog;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCatalog;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function unduh(string $route, array $query = []): TestResponse
    {
        return $this->actingAs($this->admin())
            ->get(route($route, $query))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @return list<list<string>> */
    private function baris(TestResponse $respons): array
    {
        return Xlsx::rows($respons->baseResponse->getFile()->getPathname());
    }

    public function test_ekspor_produk_memuat_header_dan_baris(): void
    {
        $produk = $this->seedCatalog();

        $baris = $this->baris($this->unduh('admin.transfer.products.export'));

        $this->assertSame(
            ['sku', 'variant_name', 'variant_sku', 'name', 'category_slug', 'original_price', 'promo_price', 'stock', 'weight', 'is_active', 'description'],
            $baris[0],
        );
        $this->assertSame($produk->sku, $baris[1][0]);
    }

    public function test_ekspor_menulis_satu_baris_per_varian(): void
    {
        $kategori = Category::factory()->create(['slug' => 'akar']);
        $produk = Product::factory()->create(['category_id' => $kategori->id, 'sku' => 'JMY-INDUK', 'description' => 'Deskripsi', 'is_active' => true]);
        Variant::factory()->create(['product_id' => $produk->id, 'name' => 'KANAN', 'sku' => '999ABC-01', 'price' => 100000, 'promo_price' => 90000, 'stock' => 3, 'weight' => 500]);
        Variant::factory()->create(['product_id' => $produk->id, 'name' => 'KIRI', 'sku' => '999ABC-01', 'price' => 120000, 'promo_price' => null, 'stock' => 2, 'weight' => 600]);
        Product::factory()->create(['category_id' => $kategori->id, 'sku' => 'JMY-TUNGGAL', 'original_price' => 50000, 'promo_price' => null, 'stock' => 9, 'weight' => 100, 'description' => 'Deskripsi', 'is_active' => true]);

        $baris = $this->baris($this->unduh('admin.transfer.products.export'));

        $this->assertCount(4, $baris);
        $this->assertSame(['JMY-INDUK', 'KANAN', '999ABC-01', $produk->name, 'akar', '100000', '90000', '3', '500', '1', 'Deskripsi'], $baris[1]);
        $this->assertSame(['JMY-INDUK', 'KIRI', '999ABC-01', $produk->name, 'akar', '120000', '', '2', '600', '1', 'Deskripsi'], $baris[2]);
        $this->assertSame(['JMY-TUNGGAL', '', ''], array_slice($baris[3], 0, 3));
        $this->assertSame('50000', $baris[3][5]);
    }

    public function test_ekspor_pengguna_tidak_memuat_kolom_peran(): void
    {
        User::factory()->create(['role' => 'admin']);

        $this->assertSame(['name', 'email', 'phone'], $this->baris($this->unduh('admin.transfer.users.export'))[0]);
    }

    public function test_ekspor_hanya_kolom_yang_dicentang_dengan_urutan_bawaan(): void
    {
        $produk = $this->seedCatalog();

        $respons = $this->unduh('admin.transfer.products.export', ['kolom' => ['name', 'sku', 'stock']]);
        $baris = $this->baris($respons);

        $this->assertSame(['sku', 'name', 'stock'], $baris[0]);
        $this->assertSame([$produk->sku, $produk->name, (string) $produk->stock], $baris[1]);
        $this->assertStringContainsString('-sebagian.xlsx', (string) $respons->headers->get('content-disposition'));
    }

    public function test_ekspor_semua_kolom_bukan_berkas_sebagian(): void
    {
        $this->seedCatalog();

        $respons = $this->unduh('admin.transfer.products.export');

        $this->assertMatchesRegularExpression('#produk-\d{4}-\d{2}-\d{2}\.xlsx#', (string) $respons->headers->get('content-disposition'));
    }

    public function test_tanpa_kolom_yang_dikenal_ekspor_ditolak(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.transfer.products.export', ['kolom' => ['harga_modal']]))
            ->assertStatus(422);
    }

    public function test_parameter_kolom_bersarang_diabaikan_tanpa_galat_server(): void
    {
        $produk = $this->seedCatalog();

        $this->actingAs($this->admin())
            ->get(route('admin.transfer.products.export', ['kolom' => [['sku']]]))
            ->assertStatus(422);

        $baris = $this->baris($this->unduh('admin.transfer.products.export', ['kolom' => ['sku', ['name']]]));

        $this->assertSame(['sku'], $baris[0]);
        $this->assertSame([$produk->sku], $baris[1]);
    }

    public function test_teks_angka_dan_karakter_khusus_tidak_berubah(): void
    {
        User::factory()->create(['name' => 'Budi & Rina <Jamu>', 'email' => 'budi@contoh.id', 'phone' => '081234567890']);

        $baris = $this->baris($this->unduh('admin.transfer.users.export'));
        $budi = collect($baris)->firstWhere(1, 'budi@contoh.id');

        $this->assertSame(['Budi & Rina <Jamu>', 'budi@contoh.id', '081234567890'], $budi);
    }

    public function test_halaman_transfer_punya_centang_kolom_tercentang_semua(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.transfer.products'))->assertOk()->assertSee('Unduh Excel')->getContent();

        $this->assertSame(11, substr_count($html, 'name="kolom[]"'));
        $this->assertMatchesRegularExpression('#<fieldset[^>]*>\s*<legend[^>]*>Kolom yang diekspor</legend>#', $html);
        $this->assertStringContainsString('Harga asli', $html);
    }

    public function test_halaman_transfer_menampilkan_kolom_sesuai_entitas_dari_nama_route(): void
    {
        $this->actingAs($this->admin())->get(route('admin.transfer.products'))
            ->assertOk()
            ->assertSee('Ekspor Produk')
            ->assertDontSee('Ekspor Pengguna');

        $this->actingAs($this->admin())->get(route('admin.transfer.users'))
            ->assertOk()
            ->assertSee('Ekspor Pengguna')
            ->assertDontSee('Ekspor Produk')
            ->assertSee(route('admin.transfer.users.export'), false)
            ->assertDontSee(route('admin.transfer.products.export'), false);
    }

    public function test_parameter_mount_entitas_menang_atas_nama_route(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer', ['entitas' => 'users'])
            ->assertSet('entitas', 'users')
            ->assertSee('Ekspor Pengguna')
            ->assertDontSee('Ekspor Produk');
    }

    public function test_upsert_pengguna_baru_memakai_role_bawaan_dan_mengabaikan_role_yang_dikirim(): void
    {
        (new UserCsv)->upsert(['name' => 'Andi Wijaya', 'email' => 'andi@contoh.test', 'phone' => '0812', 'role' => 'admin']);

        $pengguna = User::query()->where('email', 'andi@contoh.test')->firstOrFail();

        $this->assertSame('customer', $pengguna->role->value);
    }

    public function test_upsert_pengguna_yang_sudah_ada_tidak_pernah_mengubah_role_walau_dikirim(): void
    {
        $admin = User::factory()->create(['email' => 'admin@contoh.test', 'role' => 'admin']);

        (new UserCsv)->upsert(['name' => 'Nama Baru', 'email' => 'admin@contoh.test', 'phone' => null, 'role' => 'customer']);

        $this->assertSame('admin', $admin->refresh()->role->value);
        $this->assertSame('Nama Baru', $admin->name);
    }

    public function test_upsert_produk_memperbarui_produk_yang_sudah_ada_lewat_sku(): void
    {
        $produk = $this->seedCatalog();
        $kategoriBaru = Category::factory()->create(['slug' => 'kelistrikan']);

        (new ProductCsv)->upsert([
            'sku' => $produk->sku,
            'variant_name' => null,
            'variant_sku' => null,
            'name' => 'Jamu Paket Promil Ideal Baru',
            'category_slug' => 'kelistrikan',
            'original_price' => 400000,
            'promo_price' => null,
            'stock' => 5,
            'weight' => 1000,
            'is_active' => 0,
            'description' => 'Deskripsi baru.',
        ]);

        $this->assertSame(1, Product::query()->count());
        $produk->refresh();
        $this->assertSame('Jamu Paket Promil Ideal Baru', $produk->name);
        $this->assertSame($kategoriBaru->id, $produk->category_id);
        $this->assertSame(400000, $produk->original_price);
        $this->assertNull($produk->promo_price);
        $this->assertSame(5, $produk->stock);
        $this->assertFalse($produk->is_active);
    }
}
