<?php

declare(strict_types=1);

namespace App\Services\Csv;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class CsvImporter
{
    public const MAKS_BARIS = 10000;

    public function validate(string $path, CsvSchema $schema): array
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return [['baris' => 0, 'pesan' => 'Berkas tidak bisa dibaca.']];
        }

        $error = [];
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [['baris' => 0, 'pesan' => 'Berkas kosong.']];
        }

        $header = $this->header($header);

        $takDikenal = array_diff($header, $schema->columns());
        $kurang = array_diff($schema->columns(), $header);
        $ganda = array_keys(array_filter(array_count_values($header), fn (int $n): bool => $n > 1));

        if ($takDikenal !== []) {
            $error[] = ['baris' => 1, 'pesan' => 'kolom tidak dikenal: '.implode(', ', $takDikenal)];
        }

        if ($kurang !== []) {
            $error[] = ['baris' => 1, 'pesan' => 'kolom wajib tidak ada: '.implode(', ', $kurang)];
        }

        if ($ganda !== []) {
            $error[] = ['baris' => 1, 'pesan' => 'kolom ganda: '.implode(', ', $ganda)];
        }

        if ($error !== []) {
            fclose($handle);

            return $error;
        }

        $kunci = $schema->matchKey();
        $nomor = 1;
        $jumlahData = 0;
        $kunciTerlihat = [];

        while (($baris = fgetcsv($handle)) !== false) {
            $nomor++;

            if ($this->barisKosong($baris)) {
                continue;
            }

            $jumlahData++;

            if ($jumlahData > self::MAKS_BARIS) {
                fclose($handle);

                return [['baris' => $nomor, 'pesan' => 'berkas melebihi batas 10.000 baris']];
            }

            if (count($baris) !== count($header)) {
                $error[] = ['baris' => $nomor, 'pesan' => 'jumlah kolom tidak sesuai header: '.count($baris).' dari '.count($header)];

                continue;
            }

            $data = array_combine($header, $baris);

            $validator = Validator::make($data, $schema->rules());

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $pesan) {
                    $error[] = ['baris' => $nomor, 'pesan' => $pesan];
                }
            } elseif (($pesan = $schema->galatBaris($data)) !== null) {
                $error[] = ['baris' => $nomor, 'pesan' => $pesan];
            }

            $nilaiKunci = $schema->nilaiKunci($data);

            $kunciNormal = mb_strtolower(rtrim($nilaiKunci));

            if (isset($kunciTerlihat[$kunciNormal])) {
                $error[] = ['baris' => $nomor, 'pesan' => "{$kunci} duplikat dalam berkas: {$nilaiKunci}"];
            }

            $kunciTerlihat[$kunciNormal] = true;
        }

        fclose($handle);

        return $error;
    }

    public function import(string $path, CsvSchema $schema): int
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Berkas impor tidak bisa dibaca.');
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return 0;
        }

        $header = $this->header($header);
        $jumlah = 0;

        try {
            DB::transaction(function () use ($handle, $header, $schema, &$jumlah): void {
                while (($baris = fgetcsv($handle)) !== false) {
                    if ($this->barisKosong($baris)) {
                        continue;
                    }

                    $schema->upsert(array_combine($header, array_pad(array_slice($baris, 0, count($header)), count($header), null)));
                    $jumlah++;
                }
            });
        } finally {
            fclose($handle);
        }

        return $jumlah;
    }

    private function header(array $header): array
    {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($header[0] ?? ''));

        return array_map(fn ($k) => trim((string) $k), $header);
    }

    private function barisKosong(array $baris): bool
    {
        return array_filter($baris, fn (?string $sel): bool => trim((string) $sel) !== '') === [];
    }
}
