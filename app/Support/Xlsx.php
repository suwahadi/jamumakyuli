<?php

declare(strict_types=1);

namespace App\Support;

use LengthException;
use RuntimeException;
use XMLReader;
use ZipArchive;

class Xlsx
{
    public static function rows(string $path, ?int $maksByte = null): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Berkas xlsx tidak bisa dibuka.');
        }

        $sheet = self::firstSheet($zip);

        if ($maksByte !== null) {
            $ukuran = ($zip->statName($sheet)['size'] ?? 0) + ($zip->statName('xl/sharedStrings.xml')['size'] ?? 0);

            if ($ukuran > $maksByte) {
                $zip->close();

                throw new LengthException('Isi berkas xlsx melebihi batas '.$maksByte.' byte.');
            }
        }

        $shared = self::sharedStrings($zip);
        $xml = $zip->getFromName($sheet);
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('Sheet pertama tidak ditemukan di dalam berkas.');
        }

        return self::parse($xml, $shared);
    }

    public static function write(string $path, array $rows): void
    {
        $lebar = [];
        $sheetData = '';

        foreach (array_values($rows) as $r => $baris) {
            $nomor = $r + 1;
            $gaya = $r === 0 ? ' s="1"' : '';
            $sheetData .= '<row r="'.$nomor.'">';

            foreach (array_values($baris) as $c => $nilai) {
                $ref = self::columnName($c).$nomor;
                $teks = self::teksAman((string) $nilai);
                $lebar[$c] = max($lebar[$c] ?? 0, mb_strlen($teks));

                $sheetData .= is_int($nilai) || is_float($nilai)
                    ? '<c r="'.$ref.'"'.$gaya.'><v>'.$teks.'</v></c>'
                    : '<c r="'.$ref.'" t="inlineStr"'.$gaya.'><is><t xml:space="preserve">'.htmlspecialchars($teks, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            }

            $sheetData .= '</row>';
        }

        $cols = '';
        foreach ($lebar as $c => $panjang) {
            $cols .= '<col min="'.($c + 1).'" max="'.($c + 1).'" width="'.min(60, max(10, $panjang + 2)).'" customWidth="1"/>';
        }

        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $pkg = 'http://schemas.openxmlformats.org/package/2006/relationships';
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Berkas xlsx tidak bisa dibuat.');
        }

        $zip->addFromString('[Content_Types].xml', $xml.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', $xml.'<Relationships xmlns="'.$pkg.'">'
            .'<Relationship Id="rId1" Type="'.$rel.'/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $xml.'<workbook xmlns="'.$ns.'" xmlns:r="'.$rel.'">'
            .'<sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $xml.'<Relationships xmlns="'.$pkg.'">'
            .'<Relationship Id="rId1" Type="'.$rel.'/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="'.$rel.'/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', $xml.'<styleSheet xmlns="'.$ns.'">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml.'<worksheet xmlns="'.$ns.'">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .($cols === '' ? '' : '<cols>'.$cols.'</cols>')
            .'<sheetData>'.$sheetData.'</sheetData></worksheet>');

        $zip->close();
    }

    private static function teksAman(string $teks): string
    {
        return (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', mb_scrub($teks, 'UTF-8'));
    }

    private static function columnName(int $index): string
    {
        $nama = '';

        for ($i = $index + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $nama = chr(65 + ($i - 1) % 26).$nama;
        }

        return $nama;
    }

    private static function firstSheet(ZipArchive $zip): string
    {
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $book = $zip->getFromName('xl/workbook.xml');

        if ($rels === false || $book === false) {
            throw new RuntimeException('Struktur xlsx tidak dikenali.');
        }

        $bookXml = simplexml_load_string($book);
        $relsXml = simplexml_load_string($rels);

        if ($bookXml === false || $relsXml === false) {
            throw new RuntimeException('Struktur xlsx tidak dikenali.');
        }

        $id = (string) $bookXml->sheets->sheet[0]->attributes('r', true)->id;

        foreach ($relsXml->Relationship as $rel) {
            if ((string) $rel['Id'] === $id) {
                $target = (string) $rel['Target'];

                return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            }
        }

        throw new RuntimeException('Relasi sheet pertama tidak ditemukan di dalam berkas.');
    }

    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $reader = new XMLReader;
        $reader->XML($xml);

        $strings = [];
        $buffer = null;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                $buffer = '';
            } elseif ($reader->nodeType === XMLReader::TEXT && $buffer !== null) {
                $buffer .= $reader->value;
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si') {
                $strings[] = $buffer ?? '';
                $buffer = null;
            }
        }

        $reader->close();

        return $strings;
    }

    private static function parse(string $xml, array $shared): array
    {
        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            throw new RuntimeException('Sheet xlsx tidak bisa diurai.');
        }

        $rows = [];

        foreach ($doc->sheetData->row as $row) {
            $cells = [];
            $lebar = 0;

            foreach ($row->c as $cell) {
                $index = self::columnIndex((string) $cell['r']);
                $lebar = max($lebar, $index + 1);
                $cells[$index] = self::value($cell, $shared);
            }

            $baris = [];
            for ($i = 0; $i < $lebar; $i++) {
                $baris[] = $cells[$i] ?? '';
            }

            $nomor = (int) $row['r'];
            $indeks = $nomor > 0 ? $nomor - 1 : count($rows);

            while (count($rows) < $indeks) {
                $rows[] = [];
            }

            $rows[$indeks] = $baris;
        }

        return array_values($rows);
    }

    private static function value(\SimpleXMLElement $cell, array $shared): string
    {
        $type = (string) $cell['t'];

        if ($type === 's') {
            return $shared[(int) $cell->v] ?? '';
        }

        if ($type === 'inlineStr') {
            return (string) $cell->is->t;
        }

        return (string) $cell->v;
    }

    private static function columnIndex(string $ref): int
    {
        preg_match('/^[A-Z]+/', $ref, $cocok);

        $index = 0;
        foreach (str_split($cocok[0] ?? 'A') as $huruf) {
            $index = $index * 26 + (ord($huruf) - 64);
        }

        return $index - 1;
    }
}
