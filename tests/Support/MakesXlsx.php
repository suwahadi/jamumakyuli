<?php

declare(strict_types=1);

namespace Tests\Support;

use ZipArchive;

trait MakesXlsx
{
    /**
     * @param  list<list<string>>  $rows
     * @param  list<int>  $numericColumns  indeks kolom yang dipancarkan sebagai sel numerik
     *                                     mentah (tanpa atribut t, nilai langsung di <v>),
     *                                     bentuk yang dipakai Excel untuk angka yang
     *                                     diketik manual.
     */
    protected function makeXlsx(array $rows, array $numericColumns = []): string
    {
        $sheet = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $r => $row) {
            $sheet .= '<row r="'.($r + 1).'">';
            foreach ($row as $c => $value) {
                if ($value === '') {
                    continue;
                }
                $ref = $this->columnLetter($c).($r + 1);
                if (in_array($c, $numericColumns, true)) {
                    $sheet .= '<c r="'.$ref.'"><v>'.htmlspecialchars($value, ENT_XML1).'</v></c>';
                } else {
                    $sheet .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
                }
            }
            $sheet .= '</row>';
        }

        $sheet .= '</sheetData></worksheet>';

        return $this->assembleXlsx($sheet);
    }

    /**
     * Bentuk berkas xlsx dari XML sheet mentah, dengan kendali penuh atas id relasi dan
     * target sheet — dipakai untuk menguji jalur `firstSheet()` yang tidak bisa dicapai
     * lewat `makeXlsx()` (relasi tidak cocok, target absolut-paket, baris yang hilang).
     */
    protected function makeXlsxRaw(
        string $sheetXml,
        string $relTarget = 'worksheets/sheet1.xml',
        string $bookRid = 'rId1',
        string $relsRid = 'rId1',
    ): string {
        return $this->assembleXlsx($sheetXml, $relTarget, $bookRid, $relsRid);
    }

    private function assembleXlsx(
        string $sheetXml,
        string $relTarget = 'worksheets/sheet1.xml',
        string $bookRid = 'rId1',
        string $relsRid = 'rId1',
    ): string {
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE | ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Template" sheetId="1" r:id="'.$bookRid.'"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="'.$relsRid.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="'.$relTarget.'"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        return $path;
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }
}
