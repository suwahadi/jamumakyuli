<?php

use App\Services\Csv\CsvImporter;
use App\Services\Csv\CsvSchema;
use App\Services\Csv\ProductCsv;
use App\Services\Csv\UserCsv;
use App\Support\Xlsx;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Export / Import Data')] #[Layout('layouts::admin')] class extends Component {
    use WithFileUploads;

    public const MAKS_GALAT_TAMPIL = 50;

    public const MAKS_ISI_XLSX = 50 * 1024 * 1024;

    public string $entitas = 'products';

    public $berkas = null;

    public array $galat = [];

    public int $totalGalat = 0;

    public const JUDUL_KOLOM = [
        'sku' => 'SKU induk',
        'variant_name' => 'Nama varian',
        'variant_sku' => 'SKU varian',
        'name' => 'Nama',
        'category_slug' => 'Kategori',
        'original_price' => 'Harga asli',
        'promo_price' => 'Harga promo',
        'stock' => 'Stok',
        'weight' => 'Berat (gram)',
        'is_active' => 'Aktif',
        'description' => 'Deskripsi',
        'email' => 'Email',
        'phone' => 'Telepon',
    ];

    public function mount(?string $entitas = null): void
    {
        $this->entitas = $entitas ?? (str(request()->route()?->getName() ?? '')->endsWith('users') ? 'users' : 'products');
    }

    #[Computed]
    public function schema(): CsvSchema
    {
        return match ($this->entitas) {
            'users' => new UserCsv(),
            default => new ProductCsv(),
        };
    }

    public function impor(): void
    {
        $this->galat = [];
        $this->totalGalat = 0;

        $this->validate([
            'berkas' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:10240'],
        ], attributes: ['berkas' => 'berkas CSV atau Excel']);

        $path = $this->berkas->getRealPath();
        $sementara = null;

        if (strtolower($this->berkas->getClientOriginalExtension()) === 'xlsx') {
            try {
                $path = $sementara = $this->xlsxKeCsv($path);
            } catch (\LengthException) {
                $this->addError('berkas', 'Isi berkas Excel terlalu besar. Pecah menjadi beberapa berkas lalu impor satu per satu.');

                return;
            } catch (\Throwable) {
                $this->addError('berkas', 'Berkas Excel tidak bisa dibaca. Simpan ulang sebagai .xlsx lalu unggah kembali.');

                return;
            }
        }

        try {
            $this->imporBerkas($path);
        } finally {
            if ($sementara !== null) {
                @unlink($sementara);
            }
        }
    }

    private function imporBerkas(string $path): void
    {
        $importer = new CsvImporter;
        $galat = $importer->validate($path, $this->schema);

        $this->totalGalat = count($galat);
        $this->galat = array_slice($galat, 0, self::MAKS_GALAT_TAMPIL);

        if ($this->galat !== []) {
            Flux::toast(variant: 'danger', text: 'Impor dibatalkan. Tidak ada data yang diubah.');

            return;
        }

        try {
            $jumlah = $importer->import($path, $this->schema);
        } catch (\Throwable $e) {
            report($e);
            Flux::toast(variant: 'danger', text: 'Impor gagal. Tidak ada data yang diubah.');

            return;
        }

        $this->reset('berkas');
        Flux::toast(variant: 'success', text: 'Impor selesai. '.$jumlah.' baris '.Str::lower($this->schema->label()).' ditulis.');
    }

    private function xlsxKeCsv(string $path): string
    {
        $baris = Xlsx::rows($path, self::MAKS_ISI_XLSX);
        $lebar = count($baris[0] ?? []);
        $csv = tempnam(sys_get_temp_dir(), 'impor');
        $handle = fopen($csv, 'wb');

        foreach ($baris as $isi) {
            fputcsv($handle, array_pad($isi, $lebar, ''));
        }

        fclose($handle);

        return $csv;
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold tracking-tight text-brand-dark dark:text-zinc-100 sm:text-[28px]">Ekspor & Impor {{ $this->schema->label() }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">Unduh data {{ Str::lower($this->schema->label()) }} sebagai berkas Excel, atau unggah Excel atau CSV untuk memperbarui data secara massal.</p>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
                <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Ekspor {{ $this->schema->label() }}</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">Berkas Excel (.xlsx) berisi seluruh data {{ Str::lower($this->schema->label()) }} saat ini.</p>
            </div>

            <form method="GET" action="{{ route('admin.transfer.'.$entitas.'.export') }}"
                  x-data="{ semua: @js($this->schema->columns()), kolom: @js($this->schema->columns()) }">
                <fieldset class="p-5 sm:p-6">
                    <legend class="lbl float-left">Kolom yang diekspor</legend>
                    <button type="button" x-show="kolom.length < semua.length" x-cloak x-on:click="kolom = [...semua]"
                            class="float-right -mt-2 min-h-9 cursor-pointer text-xs font-semibold text-brand-green underline-offset-4 hover:underline">Centang semua</button>
                    <div class="clear-both grid gap-x-4 pt-2 sm:grid-cols-2">
                        @foreach ($this->schema->columns() as $kolom)
                            <label class="flex min-h-10 cursor-pointer items-center gap-2.5 text-sm text-gray-700 dark:text-zinc-200">
                                <input type="checkbox" name="kolom[]" value="{{ $kolom }}" x-model="kolom" checked
                                       class="size-4 cursor-pointer rounded border-gray-300 accent-brand-green dark:border-zinc-600">
                                <span>{{ self::JUDUL_KOLOM[$kolom] ?? $kolom }}</span>
                                <span class="font-mono text-xs text-gray-400 dark:text-zinc-500">{{ $kolom }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-4 text-xs text-gray-500 dark:text-zinc-400" x-show="kolom.length === semua.length">Semua kolom tercentang: berkas bisa disunting lalu diimpor kembali.</p>
                    <p class="mt-4 text-xs font-medium text-amber-700 dark:text-amber-400" x-show="kolom.length < semua.length" x-cloak>Sebagian kolom dilepas: berkas hanya untuk dibaca dan tidak bisa diimpor kembali.</p>
                </fieldset>

                <div class="flex items-center justify-end border-t border-gray-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
                    <button type="submit" x-bind:disabled="kolom.length === 0"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-brand-green px-4 py-2.5 font-display text-sm font-bold text-white transition hover:bg-brand-green/90 disabled:cursor-not-allowed disabled:opacity-60">
                        <svg class="h-4 w-4 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-file-down"/></svg>
                        Unduh Excel
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
                <h2 class="font-display text-base font-semibold tracking-tight text-brand-dark dark:text-zinc-100">Impor {{ $this->schema->label() }}</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">Unggah berkas hasil ekspor (Excel atau CSV) untuk membuat atau memperbarui data.</p>
            </div>

            <div class="p-5 sm:p-6">
                <flux:input type="file" wire:model="berkas" accept=".xlsx,.csv,text/csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" label="Berkas Excel atau CSV" />
                <p wire:loading wire:target="berkas" class="mt-1 text-xs text-primary">Mengunggah berkas…</p>
                <p class="mt-2 text-xs text-gray-500 dark:text-zinc-400">Maksimal 10 MB dan 10.000 baris. Baris dicocokkan lewat kolom <span class="font-mono">{{ $this->schema->matchKey() }}</span>: yang cocok diperbarui, sisanya dibuat baru.</p>

                @if ($galat !== [])
                    <div class="mt-4 rounded-lg border border-brand-green/30 bg-brand-green/5 p-4">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 fill-none stroke-brand-green stroke-2" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                            <p class="font-display text-sm font-bold text-rose-700 dark:text-rose-400">Impor dibatalkan, tidak ada data yang diubah.</p>
                        </div>
                        <ul class="mt-2 space-y-1 text-xs text-gray-600 dark:text-zinc-300">
                            @foreach ($galat as $item)
                                <li>baris {{ $item['baris'] }}: {{ $item['pesan'] }}</li>
                            @endforeach
                        </ul>
                        @if ($totalGalat > count($galat))
                            <p class="mt-2 text-xs text-gray-500 dark:text-zinc-400">…dan {{ $totalGalat - count($galat) }} error lain tidak ditampilkan.</p>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-end border-t border-gray-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
                <button type="button" wire:click="impor" wire:loading.attr="disabled" wire:target="berkas,impor" class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-brand-green px-4 py-2.5 font-display text-sm font-bold text-white transition hover:bg-brand-green/90 disabled:cursor-not-allowed disabled:opacity-60">
                    <svg class="h-4 w-4 fill-none stroke-current stroke-2" aria-hidden="true"><use href="#i-file-up"/></svg>
                    Impor Data
                </button>
            </div>
        </section>
    </div>
</div>
