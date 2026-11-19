<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use App\Services\Csv\CsvImporter;
use App\Services\Csv\ProductCsv;
use App\Support\Xlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    // Ekspor/impor produk dinonaktifkan lewat storefront.disabled_pages; test tetap
    // menjalankannya supaya aman saat dimunculkan kembali.
    protected function setUp(): void
    {
        parent::setUp();

        config(['storefront.disabled_pages' => []]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_satu_baris_tidak_valid_membatalkan_seluruh_impor(): void
    {
        $kategori = Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);
        $sebelum = Product::query()->count();

        $csv = "sku,variant_name,variant_sku,name,category_slug,original_price,promo_price,stock,weight,is_active,description\n"
            ."JMY-A,,,Jamu A,rempah,10000,,5,1000,1,Deskripsi A\n"
            ."JMY-B,,,Jamu B,kategori-tidak-ada,20000,,5,1000,1,Deskripsi B\n"
            ."JMY-C,,,Jamu C,rempah,30000,,5,1000,1,Deskripsi C\n";

        $berkas = UploadedFile::fake()->createWithContent('produk.csv', $csv);

        Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer')
            ->set('berkas', $berkas)
            ->call('impor')
            ->assertSee('baris 3');

        $this->assertSame($sebelum, Product::query()->count(), 'Ada baris yang masuk padahal impor harus dibatalkan seluruhnya.');
        $this->assertNull(Product::query()->where('sku', 'JMY-A')->first());
    }

    /** Baris yang gagal saat ditulis membatalkan baris sebelumnya: menguji DB::transaction di import(). */
    public function test_kegagalan_di_tengah_penulisan_membatalkan_baris_yang_sudah_ditulis(): void
    {
        Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);

        $insert = 0;
        DB::listen(function ($query) use (&$insert) {
            if (str_contains($query->sql, 'insert into `products`')) {
                $insert++;

                if ($insert === 2) {
                    throw new \RuntimeException('gagal buatan');
                }
            }
        });

        $csv = "sku,variant_name,variant_sku,name,category_slug,original_price,promo_price,stock,weight,is_active,description\n"
            ."JMY-SATU,,,Jamu Satu,rempah,10000,,5,1000,1,Deskripsi\n"
            ."JMY-DUA,,,Jamu Dua,rempah,20000,,5,1000,1,Deskripsi\n";

        Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer')
            ->set('berkas', UploadedFile::fake()->createWithContent('produk.csv', $csv))
            ->call('impor');

        $this->assertSame(2, $insert, 'Kedua baris seharusnya sempat dicoba ditulis.');
        $this->assertSame(0, Product::query()->count(), 'Baris pertama tetap tersimpan padahal baris kedua gagal.');
    }

    public function test_impor_valid_membuat_dan_memperbarui_lewat_sku(): void
    {
        $kategori = Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);
        Product::factory()->create(['sku' => 'JMY-LAMA', 'name' => 'Nama Lama', 'category_id' => $kategori->id]);

        $csv = "sku,variant_name,variant_sku,name,category_slug,original_price,promo_price,stock,weight,is_active,description\n"
            ."JMY-LAMA,,,Nama Baru,rempah,10000,,5,1000,1,Deskripsi\n"
            ."JMY-BARU,,,Jamu Baru,rempah,20000,,5,1000,1,Deskripsi\n";

        Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer')
            ->set('berkas', UploadedFile::fake()->createWithContent('produk.csv', $csv))
            ->call('impor')
            ->assertHasNoErrors();

        $this->assertSame('Nama Baru', Product::query()->where('sku', 'JMY-LAMA')->value('name'));
        $this->assertSame('nama-baru', Product::query()->where('sku', 'JMY-LAMA')->value('slug'));
        $this->assertNotNull(Product::query()->where('sku', 'JMY-BARU')->first());
    }

    /** Berkas ekspor diunggah kembali apa adanya: deskripsi kosong dan berat 0 harus lolos rules(). */
    public function test_ekspor_berdeskripsi_kosong_dan_berat_nol_dapat_diimpor_ulang(): void
    {
        $kategori = Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);
        Product::factory()->create([
            'sku' => 'JMY-KOSONG',
            'name' => 'Jamu Tanpa Deskripsi',
            'category_id' => $kategori->id,
            'description' => '',
            'weight' => 0,
        ]);

        $isi = file_get_contents($this->actingAs($this->admin())
            ->get(route('admin.transfer.products.export'))
            ->assertOk()
            ->baseResponse->getFile()->getPathname());

        $komponen = Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer')
            ->set('berkas', UploadedFile::fake()->createWithContent('produk.xlsx', $isi))
            ->call('impor');

        $this->assertSame([], $komponen->get('galat'), 'Berkas hasil ekspor sendiri ditolak saat diimpor ulang.');

        $produk = Product::query()->where('sku', 'JMY-KOSONG')->firstOrFail();
        $this->assertSame(1, Product::query()->count());
        $this->assertSame('', $produk->description);
        $this->assertSame(0, $produk->weight);
    }

    public function test_ekspor_sebagian_kolom_tidak_bisa_diimpor_ulang(): void
    {
        $kategori = Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);
        Product::factory()->create(['sku' => 'JMY-SEBAGIAN', 'name' => 'Nama Lama', 'category_id' => $kategori->id]);

        $isi = file_get_contents($this->actingAs($this->admin())
            ->get(route('admin.transfer.products.export', ['kolom' => ['sku', 'name']]))
            ->assertOk()
            ->baseResponse->getFile()->getPathname());

        $komponen = Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer')
            ->set('berkas', UploadedFile::fake()->createWithContent('produk.xlsx', $isi))
            ->call('impor');

        $this->assertNotSame([], $komponen->get('galat'));
        $this->assertSame('Nama Lama', Product::query()->where('sku', 'JMY-SEBAGIAN')->value('name'));
    }

    public function test_berkas_excel_rusak_ditolak_dengan_pesan(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer')
            ->set('berkas', UploadedFile::fake()->createWithContent('produk.xlsx', 'bukan berkas excel'))
            ->call('impor')
            ->assertHasErrors('berkas');
    }

    public function test_berkas_excel_berisi_xml_rusak_ditolak_dengan_pesan(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'uji');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="A" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet><sheetData><row r="1">');
        $zip->close();

        Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer')
            ->set('berkas', UploadedFile::fake()->createWithContent('produk.xlsx', file_get_contents($path)))
            ->call('impor')
            ->assertHasErrors('berkas');

        unlink($path);
    }

    private function validasiBerkas(string $isi): array
    {
        $path = tempnam(sys_get_temp_dir(), 'uji');
        file_put_contents($path, $isi);
        $galat = (new CsvImporter)->validate($path, new ProductCsv);
        unlink($path);

        return $galat;
    }

    public function test_validasi_ratusan_baris_tidak_menanyai_database_per_baris(): void
    {
        $kategori = Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);
        $baris = [];
        for ($i = 1; $i <= 200; $i++) {
            $produk = Product::factory()->create(['sku' => "JMY-{$i}", 'category_id' => $kategori->id]);
            if ($i % 2 === 0) {
                Variant::factory()->create(['product_id' => $produk->id, 'name' => 'KIRI', 'sku' => "JMY-{$i}-K"]);
                $baris[] = "JMY-{$i},KIRI,JMY-{$i}-K,Jamu {$i},rempah,10000,,5,1000,1,Deskripsi";
            } else {
                $baris[] = "JMY-{$i},,,Jamu {$i},rempah,10000,,5,1000,1,Deskripsi";
            }
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $galat = $this->validasiBerkas($this->barisCsv(...$baris));
        $jumlahQuery = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame([], $galat);
        $this->assertLessThan(10, $jumlahQuery);
    }

    public function test_impor_ulang_ratusan_baris_tanpa_perubahan_tidak_menanyai_database_per_baris(): void
    {
        $kategori = Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);
        for ($i = 1; $i <= 100; $i++) {
            $produk = Product::factory()->create(['sku' => "JMY-{$i}", 'category_id' => $kategori->id]);
            Variant::factory()->create(['product_id' => $produk->id, 'name' => 'KIRI', 'sku' => "JMY-{$i}-K"]);
        }
        $isi = file_get_contents($this->actingAs($this->admin())
            ->get(route('admin.transfer.products.export'))
            ->baseResponse->getFile()->getPathname());
        $path = tempnam(sys_get_temp_dir(), 'uji');
        file_put_contents($path, $isi);
        $csv = tempnam(sys_get_temp_dir(), 'uji');
        $tulis = fopen($csv, 'wb');
        foreach (Xlsx::rows($path) as $baris) {
            fputcsv($tulis, $baris);
        }
        fclose($tulis);
        $skema = new ProductCsv;

        DB::flushQueryLog();
        DB::enableQueryLog();
        $jumlah = (new CsvImporter)->import($csv, $skema);
        $jumlahQuery = count(DB::getQueryLog());
        DB::disableQueryLog();
        unlink($path);
        unlink($csv);

        $this->assertSame(100, $jumlah);
        $this->assertLessThan(15, $jumlahQuery);
    }

    public function test_produk_baru_dengan_dua_baris_varian_tetap_satu_produk_dan_satu_slug(): void
    {
        Category::query()->create(['name' => 'Akar', 'slug' => 'akar', 'is_active' => true]);

        $this->impor($this->barisCsv(
            'JMY-KENCUR,KIRI,JMY-250-K,Jamu Kencur,akar,450000,,3,500,1,Deskripsi',
            'JMY-KENCUR,KANAN,JMY-200-K,Jamu Kencur,akar,460000,,2,500,1,Deskripsi',
        ))->assertSet('galat', []);

        $produk = Product::query()->where('sku', 'JMY-KENCUR')->sole();
        $this->assertSame('jamu-kencur', $produk->slug);
        $this->assertSame(['KIRI' => 450000, 'KANAN' => 460000], $produk->variants()->orderBy('id')->pluck('price', 'name')->all());
    }

    public function test_slug_kategori_beda_huruf_besar_diterima_dan_yang_tidak_ada_ditolak(): void
    {
        Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);

        $galat = $this->validasiBerkas($this->barisCsv(
            'JMY-A,,,Jamu A,REMPAH,10000,,5,1000,1,Deskripsi',
            'JMY-B,,,Jamu B,daun,10000,,5,1000,1,Deskripsi',
        ));

        $this->assertSame([['baris' => 3, 'pesan' => 'Category slug yang dipilih tidak valid.']], $galat);
    }

    public function test_sku_yang_dipakai_produk_lain_tetap_ditolak_setelah_dimuat_sekaligus(): void
    {
        $kategori = Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);
        $pemilik = Product::factory()->create(['sku' => 'JMY-PEMILIK', 'name' => 'Jamu Pemilik', 'category_id' => $kategori->id]);
        Variant::factory()->create(['product_id' => $pemilik->id, 'name' => 'KIRI', 'sku' => 'JMY-250-KIRI']);
        Product::factory()->create(['sku' => 'JMY-LAIN', 'category_id' => $kategori->id]);

        $galat = $this->validasiBerkas($this->barisCsv(
            'JMY-PEMILIK,KIRI,JMY-250-kiri ,Jamu Pemilik,rempah,10000,,5,1000,1,Deskripsi',
            'JMY-LAIN,KANAN,JMY-250-KIRI,Jamu Lain,rempah,10000,,5,1000,1,Deskripsi',
            'jmy-pemilik,,,Jamu Baru,rempah,10000,,5,1000,1,Deskripsi',
        ));

        $this->assertSame([
            ['baris' => 3, 'pesan' => 'SKU JMY-250-KIRI sudah dipakai produk "Jamu Pemilik"'],
        ], $galat);
    }

    public function test_baris_yang_isinya_dihapus_dilewati(): void
    {
        Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);

        $this->impor($this->barisCsv(
            'JMY-SATU,,,Jamu Satu,rempah,10000,,5,1000,1,Deskripsi',
            ',,,,,,,,,,',
            ' , ,,,,,,,,,',
            'JMY-DUA,,,Jamu Dua,rempah,20000,,5,1000,1,Deskripsi',
        ))->assertSet('galat', []);

        $this->assertSame(2, Product::query()->whereIn('sku', ['JMY-SATU', 'JMY-DUA'])->count());
    }

    public function test_baris_kosong_di_berkas_excel_tidak_membatalkan_impor(): void
    {
        Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);
        $path = tempnam(sys_get_temp_dir(), 'uji');
        Xlsx::write($path, [
            ['sku', 'variant_name', 'variant_sku', 'name', 'category_slug', 'original_price', 'promo_price', 'stock', 'weight', 'is_active', 'description'],
            ['JMY-SATU', '', '', 'Jamu Satu', 'rempah', 10000, '', 5, 1000, 1, 'Deskripsi'],
            array_fill(0, 11, ''),
            ['JMY-DUA', '', '', 'Jamu Dua', 'rempah', 20000, '', 5, 1000, 1, 'Deskripsi'],
        ]);

        $this->impor((string) file_get_contents($path), 'produk.xlsx')->assertHasNoErrors()->assertSet('galat', []);
        unlink($path);

        $this->assertSame(2, Product::query()->whereIn('sku', ['JMY-SATU', 'JMY-DUA'])->count());
    }

    public function test_excel_yang_isinya_terlalu_besar_setelah_dibuka_ditolak(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'uji');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="A" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet><sheetData>'.str_repeat(' ', 51 * 1024 * 1024).'</sheetData></worksheet>');
        $zip->close();
        $isi = (string) file_get_contents($path);
        unlink($path);

        $this->assertLessThan(1024 * 1024, strlen($isi));

        $this->impor($isi, 'produk.xlsx')->assertHasErrors('berkas');
    }

    public function test_impor_excel_tidak_meninggalkan_berkas_sementara(): void
    {
        $kategori = Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);
        Product::factory()->create(['sku' => 'JMY-SEMENTARA', 'category_id' => $kategori->id]);
        $isi = file_get_contents($this->actingAs($this->admin())
            ->get(route('admin.transfer.products.export'))
            ->baseResponse->getFile()->getPathname());
        $sebelum = count(glob(sys_get_temp_dir().'/imp*') ?: []);

        $this->impor($isi, 'produk.xlsx')->assertSet('galat', []);

        $this->assertSame($sebelum, count(glob(sys_get_temp_dir().'/imp*') ?: []));
    }

    public function test_berkas_dengan_kolom_peran_ditolak(): void
    {
        $csv = "name,email,phone,role\nBudi,budi@contoh.test,08123,admin\n";

        Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer', ['entitas' => 'users'])
            ->set('berkas', UploadedFile::fake()->createWithContent('pengguna.csv', $csv))
            ->call('impor')
            ->assertSee('kolom tidak dikenal');

        $this->assertNull(User::query()->where('email', 'budi@contoh.test')->first());
    }

    private function barisCsv(string ...$baris): string
    {
        return "sku,variant_name,variant_sku,name,category_slug,original_price,promo_price,stock,weight,is_active,description\n"
            .implode("\n", $baris)."\n";
    }

    private function impor(string $isi, string $nama = 'produk.csv'): Testable
    {
        return Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer')
            ->set('berkas', UploadedFile::fake()->createWithContent($nama, $isi))
            ->call('impor');
    }

    private function produkBervarian(): Product
    {
        $kategori = Category::factory()->create(['slug' => 'akar']);
        $produk = Product::factory()->create(['category_id' => $kategori->id, 'sku' => 'JMY-INDUK', 'original_price' => 500000, 'stock' => 0, 'weight' => 900]);
        Variant::factory()->create(['product_id' => $produk->id, 'name' => 'KANAN', 'sku' => '999ABC-01', 'price' => 100000, 'promo_price' => 90000, 'stock' => 3, 'weight' => 500]);
        Variant::factory()->create(['product_id' => $produk->id, 'name' => 'KIRI', 'sku' => '999ABC-01', 'price' => 120000, 'promo_price' => null, 'stock' => 2, 'weight' => 600]);

        return $produk;
    }

    public function test_impor_ulang_hasil_ekspor_tidak_mengubah_varian(): void
    {
        $produk = $this->produkBervarian();
        $varianSebelum = Variant::query()->orderBy('id')->get()->toArray();

        $isi = file_get_contents($this->actingAs($this->admin())
            ->get(route('admin.transfer.products.export'))
            ->baseResponse->getFile()->getPathname());

        $this->impor($isi, 'produk.xlsx')->assertSet('galat', []);

        $this->assertSame($varianSebelum, Variant::query()->orderBy('id')->get()->toArray());
        $this->assertSame(500000, $produk->fresh()->original_price);
        $this->assertSame(900, $produk->fresh()->weight);
    }

    public function test_impor_memperbarui_harga_dan_stok_varian_tanpa_mengubah_harga_induk(): void
    {
        $produk = $this->produkBervarian();

        $this->impor($this->barisCsv(
            "JMY-INDUK,KANAN,999ABC-01,{$produk->name},akar,150000,,7,550,1,Deskripsi",
        ))->assertSet('galat', []);

        $varian = Variant::query()->where('name', 'KANAN')->firstOrFail();

        $this->assertSame(150000, $varian->price);
        $this->assertNull($varian->promo_price);
        $this->assertSame(7, $varian->stock);
        $this->assertSame(550, $varian->weight);
        $this->assertSame(2, Variant::query()->count());
        $this->assertSame(500000, $produk->fresh()->original_price);
        $this->assertSame(0, $produk->fresh()->stock);
    }

    public function test_impor_menolak_sku_varian_yang_dipakai_produk_lain(): void
    {
        $this->produkBervarian();
        Product::factory()->create(['sku' => 'JMY-LAIN', 'category_id' => Category::query()->value('id')]);

        $this->impor($this->barisCsv(
            'JMY-LAIN,KANAN,999ABC-01,Produk Lain,akar,150000,,7,550,1,Deskripsi',
        ))->assertSee('sudah dipakai produk');

        $this->assertSame(2, Variant::query()->count());
    }

    public function test_impor_menolak_sku_baru_yang_dipakai_dua_produk_dalam_satu_berkas(): void
    {
        Category::factory()->create(['slug' => 'akar']);

        $this->impor($this->barisCsv(
            'JMY-SATU,KANAN,777-01,Produk Satu,akar,150000,,7,550,1,Deskripsi',
            'JMY-DUA,KANAN,777-01,Produk Dua,akar,150000,,7,550,1,Deskripsi',
        ))->assertSee('dua produk');

        $this->assertSame(0, Product::query()->count());
    }

    public function test_impor_menerima_kanan_kiri_ber_sku_sama(): void
    {
        Category::factory()->create(['slug' => 'akar']);

        $this->impor($this->barisCsv(
            'JMY-BARU,KANAN,777-01,Produk Baru,akar,150000,,7,550,1,Deskripsi',
            'JMY-BARU,KIRI,777-01,Produk Baru,akar,160000,,4,550,1,Deskripsi',
        ))->assertSet('galat', []);

        $produk = Product::query()->where('sku', 'JMY-BARU')->firstOrFail();

        $this->assertSame(['KANAN', 'KIRI'], $produk->variants()->orderBy('name')->pluck('name')->all());
        $this->assertSame(160000, $produk->variants()->where('name', 'KIRI')->value('price'));
    }

    public function test_baris_varian_kembar_dalam_berkas_ditolak(): void
    {
        Category::factory()->create(['slug' => 'akar']);

        $this->impor($this->barisCsv(
            'JMY-BARU,KANAN,777-01,Produk Baru,akar,150000,,7,550,1,Deskripsi',
            'JMY-BARU,kanan,777-01,Produk Baru,akar,160000,,4,550,1,Deskripsi',
        ))->assertSee('duplikat dalam berkas');

        $this->assertSame(0, Product::query()->count());
    }

    public function test_nama_varian_tanpa_sku_varian_ditolak(): void
    {
        Category::factory()->create(['slug' => 'akar']);

        $this->impor($this->barisCsv(
            'JMY-BARU,KANAN,,Produk Baru,akar,150000,,7,550,1,Deskripsi',
        ));

        $this->assertSame(0, Product::query()->count());
    }

    public function test_berkas_enam_mb_lolos_batas_ukuran(): void
    {
        $this->impor(str_repeat('a', 6 * 1024 * 1024))->assertHasNoErrors('berkas');
    }

    public function test_berkas_melebihi_batas_10_000_baris_ditolak(): void
    {
        $csv = "sku,variant_name,variant_sku,name,category_slug,original_price,promo_price,stock,weight,is_active,description\n"
            .str_repeat("JMY-X,,,Jamu,rempah,1000,,1,1,1,d\n", 10001);

        $komponen = Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer')
            ->set('berkas', UploadedFile::fake()->createWithContent('besar.csv', $csv))
            ->call('impor')
            ->assertSee('10.000 baris')
            ->assertSee('melebihi batas');

        $this->assertCount(1, $komponen->get('galat'));
        $this->assertSame(1, $komponen->get('totalGalat'));
    }

    public function test_baris_dengan_jumlah_kolom_tidak_sesuai_header_ditolak(): void
    {
        Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);

        $csv = "sku,variant_name,variant_sku,name,category_slug,original_price,promo_price,stock,weight,is_active,description\n"
            .'JMY-A,,,Jamu A,rempah,10000,,5,1000,1,Deskripsi A, lanjutannya'."\n";

        Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer')
            ->set('berkas', UploadedFile::fake()->createWithContent('produk.csv', $csv))
            ->call('impor')
            ->assertSee('jumlah kolom tidak sesuai header');

        $this->assertSame(0, Product::query()->count());
    }

    public function test_header_dengan_kolom_ganda_ditolak(): void
    {
        $csv = "name,email,phone,phone\nBudi,budi@contoh.test,08123,08124\n";

        Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer', ['entitas' => 'users'])
            ->set('berkas', UploadedFile::fake()->createWithContent('pengguna.csv', $csv))
            ->call('impor')
            ->assertSee('kolom ganda: phone');

        $this->assertNull(User::query()->where('email', 'budi@contoh.test')->first());
    }

    public function test_bom_di_awal_berkas_tidak_membuat_header_ditolak(): void
    {
        Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);

        $csv = "\xEF\xBB\xBF".' sku,variant_name,variant_sku,name,category_slug,original_price,promo_price,stock,weight,is_active,description'."\n"
            ."JMY-A,,,Jamu A,rempah,10000,,5,1000,1,Deskripsi A\n";

        Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer')
            ->set('berkas', UploadedFile::fake()->createWithContent('produk.csv', $csv))
            ->call('impor')
            ->assertDontSee('kolom tidak dikenal');

        $this->assertNotNull(Product::query()->where('sku', 'JMY-A')->first());
    }

    public function test_impor_ulang_tidak_menulis_ulang_slug_produk_yang_namanya_tetap(): void
    {
        $kategori = Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);
        $produk = Product::factory()->create([
            'sku' => 'JMY-A',
            'name' => 'Jamu Kunyit',
            'slug' => 'jamu-kunyit-premium-2',
            'category_id' => $kategori->id,
        ]);

        $csv = "sku,variant_name,variant_sku,name,category_slug,original_price,promo_price,stock,weight,is_active,description\n"
            ."JMY-A,,,Jamu Kunyit,rempah,10000,,5,1000,1,Deskripsi\n";

        Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer')
            ->set('berkas', UploadedFile::fake()->createWithContent('produk.csv', $csv))
            ->call('impor')
            ->assertHasNoErrors();

        $this->assertSame('jamu-kunyit-premium-2', $produk->refresh()->slug, 'URL publik produk berubah padahal namanya tidak.');
        $this->assertSame(10000, $produk->original_price);
    }

    public function test_dua_produk_baru_bernama_sama_tetap_dapat_slug_berbeda(): void
    {
        Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);

        $csv = "sku,variant_name,variant_sku,name,category_slug,original_price,promo_price,stock,weight,is_active,description\n"
            ."JMY-A,,,Jamu Kunyit,rempah,10000,,5,1000,1,Deskripsi\n"
            ."JMY-B,,,Jamu Kunyit,rempah,20000,,5,1000,1,Deskripsi\n";

        Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer')
            ->set('berkas', UploadedFile::fake()->createWithContent('produk.csv', $csv))
            ->call('impor')
            ->assertHasNoErrors();

        $this->assertSame('jamu-kunyit', Product::query()->where('sku', 'JMY-A')->value('slug'));
        $this->assertSame('jamu-kunyit-2', Product::query()->where('sku', 'JMY-B')->value('slug'));
    }

    public function test_daftar_error_yang_ditampilkan_dibatasi_lima_puluh(): void
    {
        $csv = "sku,variant_name,variant_sku,name,category_slug,original_price,promo_price,stock,weight,is_active,description\n";

        for ($i = 1; $i <= 60; $i++) {
            $csv .= "JMY-{$i},Jamu {$i},kategori-tidak-ada,10000,,5,1000,1,Deskripsi\n";
        }

        $komponen = Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer')
            ->set('berkas', UploadedFile::fake()->createWithContent('produk.csv', $csv))
            ->call('impor')
            ->assertSee('baris 51')
            ->assertDontSee('baris 52')
            ->assertSee('dan 10 error lain tidak ditampilkan');

        $this->assertCount(50, $komponen->get('galat'), 'Error yang tidak ditampilkan tidak boleh ikut disimpan di snapshot Livewire.');
        $this->assertSame(60, $komponen->get('totalGalat'));
        $this->assertSame(0, Product::query()->count());
    }

    public function test_dua_baris_pengguna_dengan_email_sama_ditolak_sebagai_duplikat(): void
    {
        $csv = "name,email,phone\n"
            ."Budi Santoso,sama@contoh.test,08123\n"
            ."Budi Hartono,sama@contoh.test,08124\n";

        Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer', ['entitas' => 'users'])
            ->set('berkas', UploadedFile::fake()->createWithContent('pengguna.csv', $csv))
            ->call('impor')
            ->assertSee('email duplikat dalam berkas');

        $this->assertSame(1, User::query()->count(), 'Hanya admin penguji yang boleh ada; impor harus dibatalkan.');
    }

    /** Duplikat dideteksi mengikuti collation MySQL: tidak peka huruf besar-kecil dan spasi di ujung. */
    public static function kunciVarianProvider(): array
    {
        return [
            'beda huruf besar-kecil' => ["Budi Satu,sama@contoh.test,08123\n", "Budi Dua,SAMA@contoh.test,08124\n"],
            'spasi di ujung' => ["Budi Satu,sama@contoh.test,08123\n", "Budi Dua,\"sama@contoh.test \",08124\n"],
        ];
    }

    #[DataProvider('kunciVarianProvider')]
    public function test_kunci_yang_hanya_beda_huruf_atau_spasi_tetap_duplikat(string $satu, string $dua): void
    {
        $csv = "name,email,phone\n".$satu.$dua;

        Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer', ['entitas' => 'users'])
            ->set('berkas', UploadedFile::fake()->createWithContent('pengguna.csv', $csv))
            ->call('impor')
            ->assertSee('email duplikat dalam berkas');

        $this->assertSame(1, User::query()->count(), 'Baris kedua menimpa baris pertama tanpa terdeteksi duplikat.');
    }

    public function test_duplikat_sku_beda_huruf_besar_kecil_tidak_menimpa_diam_diam(): void
    {
        Category::query()->create(['name' => 'Rempah', 'slug' => 'rempah', 'is_active' => true]);

        $csv = "sku,variant_name,variant_sku,name,category_slug,original_price,promo_price,stock,weight,is_active,description\n"
            ."JMY-A,,,Jamu Satu,rempah,10000,,5,1000,1,Deskripsi\n"
            ."jmy-a,,,Jamu Dua,rempah,20000,,5,1000,1,Deskripsi\n";

        Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer')
            ->set('berkas', UploadedFile::fake()->createWithContent('produk.csv', $csv))
            ->call('impor')
            ->assertSee('sku duplikat dalam berkas');

        $this->assertSame(0, Product::query()->count());
    }

    public function test_dua_baris_pengguna_dengan_nama_sama_tapi_email_beda_diterima(): void
    {
        $csv = "name,email,phone\n"
            ."Budi Santoso,budi.satu@contoh.test,08123\n"
            ."Budi Santoso,budi.dua@contoh.test,08124\n";

        Livewire::actingAs($this->admin())
            ->test('pages::admin.transfer', ['entitas' => 'users'])
            ->set('berkas', UploadedFile::fake()->createWithContent('pengguna.csv', $csv))
            ->call('impor')
            ->assertHasNoErrors()
            ->assertDontSee('duplikat dalam berkas');

        $this->assertNotNull(User::query()->where('email', 'budi.satu@contoh.test')->first());
        $this->assertNotNull(User::query()->where('email', 'budi.dua@contoh.test')->first());
        $this->assertSame('customer', User::query()->where('email', 'budi.satu@contoh.test')->value('role')->value, 'Impor tidak boleh jadi jalur pembuatan akun selain customer.');
    }
}
