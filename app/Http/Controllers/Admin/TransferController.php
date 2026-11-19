<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Csv\CsvSchema;
use App\Services\Csv\ProductCsv;
use App\Services\Csv\UserCsv;
use App\Support\Xlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TransferController extends Controller
{
    public function exportProducts(Request $request): BinaryFileResponse
    {
        return $this->export(new ProductCsv, $request);
    }

    public function exportUsers(Request $request): BinaryFileResponse
    {
        return $this->export(new UserCsv, $request);
    }

    private function export(CsvSchema $schema, Request $request): BinaryFileResponse
    {
        $semua = $schema->columns();
        $dipilih = array_filter((array) $request->query('kolom', $semua), 'is_string');
        $posisi = array_keys(array_intersect($semua, $dipilih));

        abort_if($posisi === [], 422, 'Pilih minimal satu kolom untuk diekspor.');

        $baris = [array_map(fn (int $i): string => $semua[$i], $posisi)];
        foreach ($schema->rows() as $row) {
            $row = array_values($row);
            $baris[] = array_map(fn (int $i) => $row[$i] ?? null, $posisi);
        }

        $path = tempnam(sys_get_temp_dir(), 'ekspor');

        try {
            Xlsx::write($path, $baris);
        } catch (\Throwable $e) {
            @unlink($path);

            throw $e;
        }

        $nama = Str::slug($schema->label()).'-'.now()->format('Y-m-d').(count($posisi) < count($semua) ? '-sebagian' : '').'.xlsx';

        return response()->download($path, $nama, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
