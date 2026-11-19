<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\StockOpname;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\SeedsCatalog;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCatalog;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** @return array{0: Product, 1: Collection} */
    private function produkTigaVarian(): array
    {
        $produk = $this->seedCatalog();

        $varian = collect([
            ['name' => '100mm', 'sku' => 'JMY-V-100', 'stock' => 18],
            ['name' => '200mm', 'sku' => 'JMY-V-200', 'stock' => 4],
            ['name' => '300mm', 'sku' => 'JMY-V-300', 'stock' => 0],
        ])->map(fn (array $atribut) => Variant::factory()->create([
            'product_id' => $produk->id,
            ...$atribut,
        ]));

        return [$produk->refresh(), $varian];
    }

    public function test_menyimpan_opname_menyelaraskan_stok_dan_mencatat_jejaknya(): void
    {
        $produk = $this->seedCatalog();
        $this->assertSame(18, $produk->stock);

        Livewire::actingAs($admin = $this->admin())
            ->test('pages::admin.stock-opname')
            ->set('productId', $produk->id)
            ->set('physicalStock', 15)
            ->set('note', 'Selisih tiga, dus rusak.')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame(15, $produk->refresh()->stock);

        $opname = StockOpname::query()->firstOrFail();
        $this->assertSame($produk->id, $opname->product_id);
        $this->assertSame(18, $opname->system_stock);
        $this->assertSame(15, $opname->physical_stock);
        $this->assertSame(-3, $opname->difference);
        $this->assertSame($admin->id, $opname->user_id);
    }

    public function test_stok_tidak_pernah_berubah_tanpa_jejaknya_ikut_tercatat(): void
    {
        $produk = $this->seedCatalog();

        DB::listen(function ($query) {
            if (str_contains($query->sql, 'insert into `stock_opnames`')) {
                throw new \RuntimeException('gagal buatan');
            }
        });

        try {
            Livewire::actingAs($this->admin())
                ->test('pages::admin.stock-opname')
                ->set('productId', $produk->id)
                ->set('physicalStock', 15)
                ->call('simpan');

            $this->fail('Insert stock_opnames seharusnya gagal dan melempar RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertSame('gagal buatan', $e->getMessage());
        }

        $this->assertSame(18, $produk->refresh()->stock, 'Stok berubah padahal jejaknya gagal tercatat.');
        $this->assertSame(0, StockOpname::query()->count());
    }

    public function test_stok_fisik_tidak_boleh_negatif(): void
    {
        $produk = $this->seedCatalog();

        Livewire::actingAs($this->admin())
            ->test('pages::admin.stock-opname')
            ->set('productId', $produk->id)
            ->set('physicalStock', -1)
            ->call('simpan')
            ->assertHasErrors(['physicalStock']);
    }

    // Sampai rencana ini, produk bervarian disaring dari pencarian karena
    // stoknya belum bisa di-opname sama sekali. Task 3 mencabut penyaring itu,
    // jadi guarantee yang bertahan bukan lagi "tidak muncul", melainkan bahwa
    // pencarian nama tetap cocok dengan OR untuk kedua jenis produk sekaligus —
    // produk bervarian tidak diam-diam disaring oleh jalur lain.
    public function test_produk_bervarian_dan_sederhana_sama_sama_muncul_di_pencarian(): void
    {
        $produk = $this->seedCatalog();

        $bervarian = Product::factory()->create([
            'category_id' => $produk->category_id,
            'name' => 'Jamu Paket Promil Gemoy Bervarian',
        ]);
        Variant::factory()->create(['product_id' => $bervarian->id]);

        Livewire::actingAs($this->admin())
            ->test('pages::admin.stock-opname')
            ->set('cari', 'Jamu Paket Promil')
            ->assertSee($produk->name)
            ->assertSee($bervarian->name);
    }

    public function test_produk_bervarian_muncul_di_pencarian(): void
    {
        [$produk] = $this->produkTigaVarian();

        Livewire::actingAs($this->admin())
            ->test('pages::admin.stock-opname')
            ->set('cari', $produk->name)
            ->assertSee($produk->sku);
    }

    public function test_setiap_input_varian_punya_nama_yang_terbaca_pembaca_layar(): void
    {
        [$produk, $varian] = $this->produkTigaVarian();

        $html = Livewire::actingAs($this->admin())
            ->test('pages::admin.stock-opname')
            ->set('productId', $produk->id)
            ->html();

        foreach ($varian as $v) {
            $this->assertStringContainsString('aria-label="Stok fisik '.$v->name.'"', $html);
        }
    }

    public function test_riwayat_menampilkan_nama_varian(): void
    {
        [$produk, $varian] = $this->produkTigaVarian();

        StockOpname::query()->create([
            'product_id' => $produk->id,
            'variant_id' => $varian[0]->id,
            'user_id' => $this->admin()->id,
            'system_stock' => 18,
            'physical_stock' => 15,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.stock-opname.index'))
            ->assertOk()
            ->assertSeeInOrder([$produk->name, $varian[0]->name]);
    }

    // productId adalah properti publik Livewire, jadi pencarian yang menyaring
    // doesntHave('variants') bukan penjaganya — permintaan yang dirakit sendiri bisa
    // melewatinya dan menulis kolom stock induk yang tidak berarti apa-apa.
    // Produk bervarian sekarang boleh di-opname, tapi hanya lewat $fisik per varian:
    // physicalStock diabaikan seluruhnya dan kolom stock induk tetap tak tersentuh.
    public function test_physical_stock_pada_produk_bervarian_tidak_menulis_stok_induk(): void
    {
        $produk = $this->seedCatalog();

        $bervarian = Product::factory()->create([
            'category_id' => $produk->category_id,
            'stock' => 4,
        ]);
        $varian = Variant::factory()->create(['product_id' => $bervarian->id, 'stock' => 7]);

        Livewire::actingAs($this->admin())
            ->test('pages::admin.stock-opname')
            ->set('productId', $bervarian->id)
            ->set('physicalStock', 15)
            ->call('simpan')
            ->assertHasErrors(['fisik']);

        $this->assertSame(0, StockOpname::query()->count());
        $this->assertSame(4, $bervarian->refresh()->stock, 'Stok induk produk bervarian ikut tertulis.');
        $this->assertSame(7, $varian->refresh()->stock);
    }

    // Menutup kelima properti publik komponen sekaligus, bukan hanya yang sedang
    // dipersoalkan: isian yang tertinggal saat ganti produk sudah dua kali lolos
    // ke tinjauan, jadi test ini sengaja mengunci daftarnya secara utuh.
    // Yang paling menggigit adalah $fisik — id varian produk lama yang tertinggal
    // akan ditolak pemeriksaan kepemilikan pada produk yang baru dipilih, dan
    // admin tidak punya cara membersihkannya selain memuat ulang halaman.
    public function test_ganti_produk_membuang_seluruh_isian_sebelumnya(): void
    {
        [$produk, $varian] = $this->produkTigaVarian();

        Livewire::actingAs($this->admin())
            ->test('pages::admin.stock-opname')
            ->set('productId', $produk->id)
            ->set('cari', 'Jamu Kunyit')
            ->set('physicalStock', 15)
            ->set('fisik', [$varian[0]->id => 15])
            ->set('note', 'Selisih tiga, dus rusak.')
            ->call('gantiProduk')
            ->assertSet('productId', null)
            ->assertSet('cari', '')
            ->assertSet('physicalStock', null)
            ->assertSet('fisik', [])
            ->assertSet('note', '');
    }

    public function test_baris_opname_bisa_menunjuk_varian(): void
    {
        $produk = $this->seedCatalog();
        $varian = Variant::factory()->create([
            'product_id' => $produk->id,
            'stock' => 10,
        ]);

        $opname = StockOpname::query()->create([
            'product_id' => $produk->id,
            'variant_id' => $varian->id,
            'user_id' => $this->admin()->id,
            'system_stock' => 10,
            'physical_stock' => 7,
        ]);

        $this->assertSame($varian->id, $opname->refresh()->variant?->id);
        $this->assertSame(-3, $opname->difference);
    }

    public function test_baris_opname_tanpa_varian_tetap_sah(): void
    {
        $produk = $this->seedCatalog();

        $opname = StockOpname::query()->create([
            'product_id' => $produk->id,
            'user_id' => $this->admin()->id,
            'system_stock' => 18,
            'physical_stock' => 18,
        ]);

        $this->assertDatabaseHas('stock_opnames', ['id' => $opname->id, 'variant_id' => null]);
        $this->assertSame(0, $opname->difference);
    }

    public function test_varian_dengan_baris_opname_tidak_bisa_dihapus(): void
    {
        $produk = $this->seedCatalog();
        $varian = Variant::factory()->create([
            'product_id' => $produk->id,
            'stock' => 10,
        ]);

        StockOpname::query()->create([
            'product_id' => $produk->id,
            'variant_id' => $varian->id,
            'user_id' => $this->admin()->id,
            'system_stock' => 10,
            'physical_stock' => 7,
        ]);

        try {
            $varian->delete();
            $this->fail('varian ber-opname seharusnya tidak bisa dihapus.');
        } catch (QueryException) {
        }

        $this->assertDatabaseHas('stock_opnames', ['variant_id' => $varian->id]);
        $this->assertNotNull($varian->fresh(), 'varian ikut terhapus padahal jejak opname-nya masih ada.');
    }

    public function test_menyimpan_dua_dari_tiga_varian_menyelaraskan_stok_dan_mencatat_jejaknya(): void
    {
        [$produk, $varian] = $this->produkTigaVarian();

        Livewire::actingAs($admin = $this->admin())
            ->test('pages::admin.stock-opname')
            ->set('productId', $produk->id)
            ->set('fisik', [$varian[0]->id => 15, $varian[2]->id => 2])
            ->set('note', 'Dus rusak di rak bawah.')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame(15, $varian[0]->refresh()->stock);
        $this->assertSame(2, $varian[2]->refresh()->stock);

        $this->assertSame(2, StockOpname::query()->count());

        $baris = StockOpname::query()->where('variant_id', $varian[0]->id)->firstOrFail();
        $this->assertSame($produk->id, $baris->product_id);
        $this->assertSame(18, $baris->system_stock);
        $this->assertSame(15, $baris->physical_stock);
        $this->assertSame(-3, $baris->difference);
        $this->assertSame($admin->id, $baris->user_id);
        $this->assertSame('Dus rusak di rak bawah.', $baris->note);

        // Baris kedua ini yang benar-benar mengunci system_stock ke stok varian.
        // Pada $varian[0] angkanya kebetulan sama dengan stok produk induk (dua-duanya
        // 18), jadi asersi di atas tidak bisa membedakan keduanya. Di sini stok varian
        // 0 sedangkan induknya 18, sehingga mencatat stok induk akan langsung merah.
        $barisKedua = StockOpname::query()->where('variant_id', $varian[2]->id)->firstOrFail();
        $this->assertSame($produk->id, $barisKedua->product_id);
        $this->assertSame(0, $barisKedua->system_stock, 'Stok induk tercatat sebagai stok sistem varian.');
        $this->assertSame(2, $barisKedua->physical_stock);
        $this->assertSame(2, $barisKedua->difference);
    }

    public function test_varian_yang_dikosongkan_tidak_tersentuh(): void
    {
        [$produk, $varian] = $this->produkTigaVarian();

        Livewire::actingAs($this->admin())
            ->test('pages::admin.stock-opname')
            ->set('productId', $produk->id)
            ->set('fisik', [$varian[0]->id => 15, $varian[1]->id => '', $varian[2]->id => null])
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame(4, $varian[1]->refresh()->stock, 'Varian kosong ikut berubah.');
        $this->assertSame(0, $varian[2]->refresh()->stock, 'Varian kosong ikut berubah.');
        $this->assertSame(1, StockOpname::query()->count());
        $this->assertSame(0, StockOpname::query()->where('variant_id', $varian[1]->id)->count());
    }

    // Pasangan test di atas: nol adalah hitungan yang sah, bukan "belum dihitung".
    // Rak yang benar-benar habis harus bisa dicatat. Sengaja memakai $varian[1] yang
    // stoknya 4, bukan $varian[2] yang stoknya sudah 0 — mengasersi 0 menjadi 0 tidak
    // membedakan apa pun, dan justru itu jenis cacat yang sedang ditutup di sini.
    public function test_hitungan_nol_dicatat_bukan_dilewati(): void
    {
        [$produk, $varian] = $this->produkTigaVarian();

        Livewire::actingAs($this->admin())
            ->test('pages::admin.stock-opname')
            ->set('productId', $produk->id)
            ->set('fisik', [$varian[0]->id => 15, $varian[1]->id => 0])
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame(0, $varian[1]->refresh()->stock, 'Hitungan nol diperlakukan sebagai belum dihitung.');
        $this->assertSame(15, $varian[0]->refresh()->stock);

        $baris = StockOpname::query()->where('variant_id', $varian[1]->id)->firstOrFail();
        $this->assertSame(4, $baris->system_stock);
        $this->assertSame(0, $baris->physical_stock);
        $this->assertSame(-4, $baris->difference);
        $this->assertSame(2, StockOpname::query()->count());
    }

    public function test_kegagalan_di_tengah_sesi_membatalkan_seluruh_varian(): void
    {
        [$produk, $varian] = $this->produkTigaVarian();

        $sisipan = 0;
        DB::listen(function ($query) use (&$sisipan) {
            if (str_contains($query->sql, 'insert into `stock_opnames`')) {
                $sisipan++;

                if ($sisipan === 2) {
                    throw new \RuntimeException('gagal buatan');
                }
            }
        });

        // fail() tidak boleh ada di dalam try: AssertionFailedError milik PHPUnit
        // adalah turunan RuntimeException, jadi ia akan ditelan catch di bawahnya
        // dan muncul kembali sebagai ketidakcocokan pesan yang menyesatkan.
        $galat = null;

        try {
            Livewire::actingAs($this->admin())
                ->test('pages::admin.stock-opname')
                ->set('productId', $produk->id)
                ->set('fisik', [$varian[0]->id => 15, $varian[1]->id => 1])
                ->call('simpan');
        } catch (\RuntimeException $e) {
            $galat = $e;
        }

        $this->assertNotNull($galat, 'Seharusnya melempar RuntimeException.');
        $this->assertSame('gagal buatan', $galat->getMessage());

        $this->assertSame(2, $sisipan, 'Penulisan kedua tidak pernah dicoba.');
        $this->assertSame(18, $varian[0]->refresh()->stock, 'Stok varian pertama berubah padahal sesi gagal.');
        $this->assertSame(4, $varian[1]->refresh()->stock);
        $this->assertSame(0, StockOpname::query()->count());
    }

    public function test_stok_produk_induk_tidak_berubah_saat_opname_varian(): void
    {
        [$produk, $varian] = $this->produkTigaVarian();
        $stokInduk = $produk->stock;

        Livewire::actingAs($this->admin())
            ->test('pages::admin.stock-opname')
            ->set('productId', $produk->id)
            ->set('fisik', [$varian[0]->id => 15])
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame(15, $varian[0]->refresh()->stock, 'stok varian tidak tersimpan — asersi induk di bawah jadi hampa.');
        $this->assertSame($stokInduk, $produk->refresh()->stock, 'products.stock ikut diselaraskan padahal tidak boleh.');
    }

    public function test_varian_milik_produk_lain_ditolak(): void
    {
        [$produk] = $this->produkTigaVarian();

        $produkLain = Product::query()->create([
            'category_id' => $produk->category_id,
            'name' => 'Produk Lain',
            'slug' => 'produk-lain',
            'sku' => 'JMY-LAIN-001',
            'original_price' => 100000,
            'description' => 'Lain.',
            'weight' => 500,
            'stock' => 0,
            'is_active' => true,
        ]);
        $varianAsing = Variant::factory()->create([
            'product_id' => $produkLain->id,
            'sku' => 'JMY-ASING-1',
            'stock' => 9,
        ]);

        Livewire::actingAs($this->admin())
            ->test('pages::admin.stock-opname')
            ->set('productId', $produk->id)
            ->set('fisik', [$varianAsing->id => 99])
            ->call('simpan')
            ->assertHasErrors(['fisik']);

        $this->assertSame(9, $varianAsing->refresh()->stock);
        $this->assertSame(0, StockOpname::query()->count());
    }

    public function test_menyimpan_tanpa_mengisi_satu_varian_pun_ditolak(): void
    {
        [$produk, $varian] = $this->produkTigaVarian();

        Livewire::actingAs($this->admin())
            ->test('pages::admin.stock-opname')
            ->set('productId', $produk->id)
            ->set('fisik', [$varian[0]->id => '', $varian[1]->id => null])
            ->call('simpan')
            ->assertHasErrors(['fisik']);

        $this->assertSame(0, StockOpname::query()->count());
    }

    public function test_stok_fisik_varian_negatif_ditolak(): void
    {
        [$produk, $varian] = $this->produkTigaVarian();

        $uji = Livewire::actingAs($this->admin())
            ->test('pages::admin.stock-opname')
            ->set('productId', $produk->id)
            ->set('fisik', [$varian[0]->id => -1])
            ->call('simpan')
            ->assertHasErrors(['fisik.'.$varian[0]->id]);

        // pesan untuk admin, bukan jalur properti internal seperti "fisik.5"
        $this->assertSame(
            'Stok fisik harus minimal 0.',
            $uji->errors()->first('fisik.'.$varian[0]->id),
        );

        // <flux:error name="fisik"> default-nya deep, jadi tanpa :deep="false" pesan
        // baris varian ikut tercetak lagi di bawah tabel lewat pencocokan "fisik.*"
        $this->assertSame(
            1,
            substr_count($uji->html(), 'Stok fisik harus minimal 0.'),
            'pesan galat baris varian tercetak lebih dari sekali.',
        );

        $this->assertSame(18, $varian[0]->refresh()->stock);
        $this->assertSame(0, StockOpname::query()->count());
    }
}
