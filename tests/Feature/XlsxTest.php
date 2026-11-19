<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Xlsx;
use Tests\Support\MakesXlsx;
use Tests\TestCase;

class XlsxTest extends TestCase
{
    use MakesXlsx;

    // Id 19 digit dari file impor. Begitu nilainya
    // melewati float, presisinya hilang jadi 1.7308142422206E+18, SKU jadi salah,
    // dan impor kedua menduplikasi seluruh katalog tanpa gejala apa pun.
    public function test_id_sembilan_belas_digit_terbaca_utuh(): void
    {
        $path = $this->makeXlsx([
            ['product_id', 'product_name'],
            ['1730814242220573795', 'Keychain'],
        ]);

        $rows = Xlsx::rows($path);

        $this->assertSame('1730814242220573795', $rows[1][0]);
    }

    // Kolom `stock` yang diketik manual oleh pemilik toko ditulis Excel sebagai sel
    // numerik mentah, bukan shared string. Cabang itulah satu-satunya jalur yang
    // melewati casting, dan sampai sekarang tidak ada tes yang menyentuhnya.
    public function test_sel_numerik_mentah_juga_terbaca_utuh(): void
    {
        $path = $this->makeXlsx([
            ['product_id', 'stock'],
            ['1730814242220573795', '7'],
        ], numericColumns: [0, 1]);

        $rows = Xlsx::rows($path);

        $this->assertSame('1730814242220573795', $rows[1][0]);
        $this->assertSame('7', $rows[1][1]);
    }

    public function test_sel_kosong_tidak_menggeser_kolom(): void
    {
        $path = $this->makeXlsx([
            ['a', '', 'c'],
        ]);

        $rows = Xlsx::rows($path);

        $this->assertSame(['a', '', 'c'], $rows[0]);
    }

    public function test_berkas_rusak_melempar_galat(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bukan').'.xlsx';
        file_put_contents($path, 'ini bukan zip');

        $this->expectException(\RuntimeException::class);

        Xlsx::rows($path);
    }

    // Kalau rId sheet pertama tak ada di rels dan fungsi diam-diam jatuh ke
    // xl/worksheets/sheet1.xml, data pengecoh di path itu akan terbaca seolah sah.
    // Ini harus melempar galat, bukan mengembalikan sheet yang salah.
    public function test_relasi_sheet_tidak_cocok_melempar_galat(): void
    {
        $sheet = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="inlineStr"><is><t>pengecoh</t></is></c></row>'
            .'</sheetData></worksheet>';

        $path = $this->makeXlsxRaw($sheet, relsRid: 'rId2');

        $this->expectException(\RuntimeException::class);

        Xlsx::rows($path);
    }

    // OOXML mengizinkan Target berbentuk absolut-paket (diawali /xl/...), bukan cuma
    // relatif terhadap folder xl/. Sebagian penulis xlsx mencampur kedua gaya.
    public function test_target_absolut_paket_tetap_terbaca(): void
    {
        $sheet = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="inlineStr"><is><t>ok</t></is></c></row>'
            .'</sheetData></worksheet>';

        $path = $this->makeXlsxRaw($sheet, relTarget: '/xl/worksheets/sheet1.xml');

        $rows = Xlsx::rows($path);

        $this->assertSame('ok', $rows[0][0]);
    }

    // Penulis xlsx yang menghilangkan baris kosong dari XML membuat elemen <row>
    // berikutnya tetap membawa atribut r yang benar walau urutannya berurutan di file.
    // Baris yang hilang harus diisi larik kosong, bukan menggeser baris sesudahnya naik.
    public function test_baris_kosong_di_tengah_tidak_menggeser_indeks(): void
    {
        $sheet = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="inlineStr"><is><t>satu</t></is></c></row>'
            .'<row r="3"><c r="A3" t="inlineStr"><is><t>tiga</t></is></c></row>'
            .'</sheetData></worksheet>';

        $path = $this->makeXlsxRaw($sheet);

        $rows = Xlsx::rows($path);

        $this->assertSame(['satu'], $rows[0]);
        $this->assertSame([], $rows[1]);
        $this->assertSame(['tiga'], $rows[2]);
    }
}
