<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PencarianProdukTest extends TestCase
{
    use RefreshDatabase;

    private Category $kategori;

    private int $urut = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kategori = Category::factory()->create();
    }

    private function produk(string $nama, ?string $sku = null): Product
    {
        $this->urut++;

        return Product::factory()->create([
            'category_id' => $this->kategori->id,
            'name' => $nama,
            'sku' => $sku ?? sprintf('JMY-AAA-%05d', $this->urut),
            'is_active' => true,
        ]);
    }

    /** @return list<int> */
    private function hasil(string $kata): array
    {
        return Product::query()->cari($kata)->orderBy('id')->pluck('id')->all();
    }

    public function test_kata_kunci_dipecah_pada_spasi_dan_tanda_baca(): void
    {
        $this->assertSame(['jamu', 'k12', 'j07', 'asam'], Product::kataKunci('Jamu  K12, J07/(Asam)'));
    }

    public function test_tanda_strip_dihapus_dari_kata_kunci(): void
    {
        $this->assertSame(['k12', 'jmyrgx45328'], Product::kataKunci('K-12 JMY-RGX-45328'));
    }

    public function test_kata_kembar_dibuang(): void
    {
        $this->assertSame(['k12'], Product::kataKunci('k12 K12 k-12'));
    }

    public function test_dibatasi_delapan_kata(): void
    {
        $this->assertCount(8, Product::kataKunci('a b c d e f g h i j'));
    }

    public function test_dibatasi_seratus_karakter(): void
    {
        $this->assertSame([str_repeat('x', 100)], Product::kataKunci(str_repeat('x', 300)));
    }

    // Review Focus 2: wildcard LIKE dari pembeli dicari apa adanya.
    public function test_wildcard_di_escape(): void
    {
        $this->assertSame(['50\%', 'a\_b'], Product::kataKunci('50% a_b'));
    }

    // Review Focus 1: input yang hanya berisi tanda baca tidak menghasilkan kata.
    public function test_input_hanya_tanda_baca_menghasilkan_kosong(): void
    {
        $this->assertSame([], Product::kataKunci(' ,, - / '));
        $this->assertSame([], Product::kataKunci(null));
    }

    public function test_tiga_judul_contoh_cocok_untuk_jamu_k12(): void
    {
        $cocok = [
            $this->produk('Paket Jamu Seduh Hangat K12 2018-2021')->id,
            $this->produk('Jamu Seduh Hangat K12 2018')->id,
            $this->produk('Ramuan Jamu 2020: K12, J07, Asam')->id,
        ];
        $this->produk('Jamu Seduh Asam 2018');
        $this->produk('Ramuan Kering K12 2016');

        $this->assertSame($cocok, $this->hasil('jamu k12'));
    }

    public function test_ejaan_berstrip_cocok_dengan_judul_tanpa_strip(): void
    {
        $k12 = $this->produk('Ramuan Kering K12 2016');
        $this->produk('Ramuan Kering Asam 2016');

        $this->assertSame([$k12->id], $this->hasil('k-12'));
    }

    public function test_judul_berstrip_cocok_dengan_kata_tanpa_strip(): void
    {
        $j07 = $this->produk('Rempah Seduh J-07 2019');
        $this->produk('Rempah Seduh Manis 2019');

        $this->assertSame([$j07->id], $this->hasil('j07'));
    }

    public function test_potongan_sku_cocok_dengan_sku_berstrip(): void
    {
        $target = $this->produk('Jamu Jahe Seduh', 'JMY-RGX-45328');
        $this->produk('Jamu Jahe Kering', 'JMY-NZN-24973');

        $this->assertSame([$target->id], $this->hasil('RGX 45328'));
    }

    // Review Focus 2: "%" tidak boleh berubah menjadi "cocokkan semuanya".
    public function test_persen_tidak_mencocokkan_semua_produk(): void
    {
        $this->produk('Jamu Seduh K12');
        $this->produk('Ramuan Asam');

        $this->assertSame([], $this->hasil('%'));
    }

    // Review Focus 3: huruf besar dan kecil campur memberi hasil yang sama.
    public function test_huruf_besar_kecil_tidak_dibedakan(): void
    {
        $p = $this->produk('Jamu Seduh Hangat K12 2018');

        $this->assertSame([$p->id], $this->hasil('JAMU k12'));
    }

    // Review Focus 1: input kosong atau hanya tanda baca tidak menyaring.
    public function test_input_kosong_tidak_menyaring(): void
    {
        $a = $this->produk('Jamu Seduh K12');
        $b = $this->produk('Ramuan Asam');

        $this->assertSame([$a->id, $b->id], $this->hasil(''));
        $this->assertSame([$a->id, $b->id], $this->hasil(' ,, - / '));
    }

    // Temuan tinjauan akhir: tanda baca di luar daftar pemisah ikut menjadi kata
    // kunci. iPhone menyisipkan ". " saat spasi diketuk dua kali, sehingga
    // "jamu. k12" dulu menghasilkan nol produk.
    public function test_titik_akhir_kalimat_tidak_menjadi_bagian_kata(): void
    {
        $this->assertSame(['jamu', 'k12'], Product::kataKunci('jamu. k12'));
        $this->assertSame(['jamu', 'k12'], Product::kataKunci('"jamu" k12.'));
    }

    // Takaran seperti "1.5" harus tetap satu kata, bukan "1" dan "5".
    public function test_angka_desimal_tetap_utuh(): void
    {
        $this->assertSame(['1.5'], Product::kataKunci('1.5'));
    }

    public function test_tanda_baca_lain_saja_tidak_menyaring(): void
    {
        $a = $this->produk('Jamu Seduh K12');
        $b = $this->produk('Ramuan Asam');

        $this->assertSame([$a->id, $b->id], $this->hasil('?'));
        $this->assertSame([$a->id, $b->id], $this->hasil('* . !'));
    }

    public function test_titik_dan_kutip_tidak_menggagalkan_pencarian(): void
    {
        $cocok = $this->produk('Jamu Seduh Hangat K12 2018');
        $this->produk('Jamu Seduh Asam 2018');

        $this->assertSame([$cocok->id], $this->hasil('jamu. k12'));
        $this->assertSame([$cocok->id], $this->hasil('jamu k12.'));
        $this->assertSame([$cocok->id], $this->hasil('"jamu" k12'));
    }

    public function test_tanda_pisah_panjang_tetap_menemukan_k12(): void
    {
        $k12 = $this->produk('Ramuan Kering K12 2016');
        $this->produk('Ramuan Kering Asam 2016');

        $this->assertSame([$k12->id], $this->hasil('k–12'));
    }

    public function test_angka_desimal_mencocokkan_takaran(): void
    {
        $tepat = $this->produk('Jahe Merah 1.5kg Asam');
        $this->produk('Kunyit Bubuk 15 gr Asam');

        $this->assertSame([$tepat->id], $this->hasil('1.5'));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_katalog_toko_memakai_pencarian_per_kata(): void
    {
        $this->produk('Paket Jamu Seduh Hangat K12 2018-2021');
        $this->produk('Ramuan Jamu 2020: K12, J07, Asam');
        $this->produk('Jamu Seduh Asam 2018');

        Livewire::test('pages::storefront.catalog')
            ->set('search', 'jamu k12')
            ->assertSee('Paket Jamu Seduh Hangat K12 2018-2021')
            ->assertSee('Ramuan Jamu 2020: K12, J07, Asam')
            ->assertDontSee('Jamu Seduh Asam 2018');
    }

    public function test_kotak_cari_header_mengirim_ejaan_berstrip(): void
    {
        $this->produk('Ramuan Kering K12 2016');
        $this->produk('Ramuan Kering Asam 2016');

        $this->get(route('products.index', ['q' => 'ramuan k-12']))
            ->assertOk()
            ->assertSee('Ramuan Kering K12 2016')
            ->assertDontSee('Ramuan Kering Asam 2016');
    }

    public function test_daftar_produk_admin_memakai_pencarian_per_kata(): void
    {
        $this->produk('Jamu Seduh Hangat K12 2018');
        $this->produk('Jamu Seduh Asam 2018');

        Livewire::actingAs($this->admin())
            ->test('pages::admin.products.index')
            ->set('cari', 'jamu k12')
            ->assertSee('Jamu Seduh Hangat K12 2018')
            ->assertDontSee('Jamu Seduh Asam 2018');
    }

    public function test_produk_ditemukan_lewat_sku_varian_dengan_atau_tanpa_strip(): void
    {
        $p = $this->produk('Jamu Daun Sirih');
        Variant::factory()->create(['product_id' => $p->id, 'sku' => '57752-SRH-003', 'name' => 'KANAN']);

        $this->assertTrue(Product::query()->cari('57752-SRH-003')->whereKey($p->id)->exists());
        $this->assertTrue(Product::query()->cari('57752SRH003')->whereKey($p->id)->exists());
    }

    public function test_stok_opname_memakai_pencarian_per_kata(): void
    {
        $this->produk('Rempah Seduh J-07 2019');
        $this->produk('Rempah Seduh Manis 2019');

        Livewire::actingAs($this->admin())
            ->test('pages::admin.stock-opname')
            ->set('cari', 'rempah j07')
            ->assertSee('Rempah Seduh J-07 2019')
            ->assertDontSee('Rempah Seduh Manis 2019');
    }
}
