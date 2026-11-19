<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShippingService
{
    public const COURIERS = [
        'jne' => 'JNE',
        'jnt' => 'J&T Express',
    ];

    public const ENABLED_COURIERS = [
        'jne', 'jnt',
    ];

    private const KARGO = ['JTR', 'GOKIL'];

    private const BERAT_MIN_KARGO = 10000;

    private const REGION_TTL = 2592000;

    private const COST_TTL = 43200;

    private string $baseUrl;

    private ?string $apiKey;

    private bool $mock;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.rajaongkir.base_url'), '/');
        $this->apiKey = config('services.rajaongkir.key');
        $this->mock = (bool) config('services.rajaongkir.mock') || blank($this->apiKey);
    }

    public function provinces(): array
    {
        return $this->regions('province', 'province');
    }

    public function cities(int $provinceId): array
    {
        return $this->regions("city/{$provinceId}", "city:{$provinceId}");
    }

    public function districts(int $cityId): array
    {
        return $this->regions("district/{$cityId}", "district:{$cityId}");
    }

    public function subDistricts(int $districtId): array
    {
        return $this->regions("sub-district/{$districtId}", "subdistrict:{$districtId}", true);
    }

    public function tariffs(int $destinationId, int $weight): array
    {
        $hasil = [];

        foreach (self::ENABLED_COURIERS as $kode) {
            foreach ($this->cost($destinationId, $weight, $kode) as $row) {
                if ($row['cost'] <= 0 || ($weight < self::BERAT_MIN_KARGO && $this->kargo($row['service']))) {
                    continue;
                }

                $hasil[] = [
                    ...$row,
                    'name' => self::COURIERS[$kode],
                    'code' => $kode,
                    'description' => $row['description'] !== '' ? $row['description'] : self::COURIERS[$kode],
                ];
            }
        }

        usort($hasil, static fn (array $a, array $b): int => $a['cost'] <=> $b['cost']);

        return $hasil;
    }

    private function kargo(string $service): bool
    {
        foreach (self::KARGO as $awalan) {
            if (str_starts_with(mb_strtoupper($service), $awalan)) {
                return true;
            }
        }

        return false;
    }

    public function quote(int $destinationId, int $weight, string $courier, ?string $service = null): array
    {
        foreach ($this->tariffs($destinationId, $weight) as $option) {
            if ($option['code'] !== $courier || ($service !== null && $option['service'] !== $service)) {
                continue;
            }

            return [
                'courier' => $courier,
                'courier_name' => $option['name'],
                'service' => $option['service'],
                'service_label' => $option['description'],
                'etd' => $option['etd'],
                'cost' => $option['cost'],
            ];
        }

        throw new BusinessRuleException('Layanan pengiriman yang dipilih tidak tersedia. Silakan pilih ulang.');
    }

    public function cost(int $destinationId, int $weight, string $courier): array
    {
        if ($this->mock) {
            return $this->mockCost($weight, $courier);
        }

        $origin = setting('origin_district_id');

        if (blank($origin)) {
            throw new BusinessRuleException('Titik asal pengiriman belum dikonfigurasi.');
        }

        $cacheKey = "shipping:cost:{$origin}:{$destinationId}:".max(1, $weight).":{$courier}";

        return Cache::remember($cacheKey, self::COST_TTL, function () use ($origin, $destinationId, $weight, $courier): array {
            try {
                $response = Http::withHeaders(['key' => $this->apiKey])
                    ->timeout(10)
                    ->asForm()
                    ->post($this->baseUrl.'/calculate/domestic-cost', [
                        'origin' => (string) $origin,
                        'destination' => $destinationId,
                        'weight' => max(1, $weight),
                        'courier' => $courier,
                    ]);
            } catch (ConnectionException) {
                throw new BusinessRuleException('Layanan ongkir sedang tidak bisa dihubungi. Coba lagi sebentar lagi.');
            }

            if ($response->status() === 404) {
                return [];
            }

            if ($response->failed()) {
                Log::warning('RajaOngkir cost gagal', ['status' => $response->status(), 'body' => $response->body()]);

                throw new BusinessRuleException('Gagal menghitung ongkos kirim. Silakan coba lagi.');
            }

            return array_map(static fn (array $c): array => [
                'name' => (string) ($c['name'] ?? ''),
                'code' => (string) ($c['code'] ?? ''),
                'service' => (string) ($c['service'] ?? ''),
                'description' => (string) ($c['description'] ?? ''),
                'cost' => (int) ($c['cost'] ?? 0),
                'etd' => (string) ($c['etd'] ?? ''),
            ], $response->json('data') ?? []);
        });
    }

    private function mockCost(int $weight, string $courier): array
    {
        $base = 9000 + (int) ceil(max(1, $weight) / 1000) * 5000;

        return [[
            'name' => self::COURIERS[$courier] ?? mb_strtoupper($courier),
            'code' => $courier,
            'service' => 'REG',
            'description' => 'Layanan Reguler',
            'cost' => $base,
            'etd' => '2-3 day',
        ]];
    }

    private function regions(string $path, string $cacheKey, bool $withZip = false): array
    {
        if ($this->mock) {
            return $this->mockRegions($cacheKey);
        }

        return Cache::remember(
            'shipping:region:'.$cacheKey,
            self::REGION_TTL,
            fn (): array => $this->fetchRegions($path, $withZip),
        );
    }

    private function fetchRegions(string $path, bool $withZip): array
    {
        try {
            $response = Http::withHeaders(['key' => $this->apiKey])
                ->timeout(10)
                ->get($this->baseUrl.'/destination/'.$path);
        } catch (ConnectionException) {
            throw new BusinessRuleException('Data wilayah sedang tidak bisa dimuat. Coba lagi sebentar lagi.');
        }

        if ($response->status() === 404) {
            return [];
        }

        if ($response->failed()) {
            Log::warning('RajaOngkir wilayah gagal', ['path' => $path, 'status' => $response->status(), 'body' => $response->body()]);

            throw new BusinessRuleException('Gagal memuat data wilayah pengiriman.');
        }

        return array_map(static function (array $row) use ($withZip): array {
            $item = ['id' => (int) ($row['id'] ?? 0), 'name' => (string) ($row['name'] ?? '')];

            if ($withZip) {
                $item['zip'] = (string) ($row['zip_code'] ?? '');
            }

            return $item;
        }, $response->json('data') ?? []);
    }

    private function mockRegions(string $cacheKey): array
    {
        return match ($cacheKey) {
            'province' => [
                ['id' => 10, 'name' => 'JAWA TENGAH'],
                ['id' => 11, 'name' => 'JAWA TIMUR'],
            ],
            'city:10' => [
                ['id' => 138, 'name' => 'KARANGANYAR'],
                ['id' => 135, 'name' => 'SURAKARTA'],
            ],
            'city:11' => [['id' => 444, 'name' => 'SURABAYA']],
            'district:138' => [['id' => 1348, 'name' => 'MOJOGEDANG']],
            'district:135' => [['id' => 1327, 'name' => 'LAWEYAN']],
            'district:444' => [['id' => 5000, 'name' => 'GUBENG']],
            'subdistrict:1348' => [
                ['id' => 17641, 'name' => 'MUNGGUR', 'zip' => '57752'],
                ['id' => 17642, 'name' => 'PERENG', 'zip' => '57752'],
                ['id' => 17643, 'name' => 'GEBYOG', 'zip' => '57752'],
            ],
            'subdistrict:1327' => [['id' => 17673, 'name' => 'LAWEYAN', 'zip' => '57148']],
            'subdistrict:5000' => [['id' => 44401, 'name' => 'GUBENG', 'zip' => '60281']],
            default => [],
        };
    }
}
