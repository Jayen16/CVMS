<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PsgcSeeder extends Seeder
{
    private const API = 'https://psgc.cloud/api/v2';

    /**
     * Barangays currently included for Indang.
     * All other Indang barangays are intentionally excluded from seeding.
     */
    private const INDANG_BARANGAYS = [
        'Bancod',
        'Barangay 4 (Pob.)',
        'Kaytapos',
        'Kaytambog',
        'Buna Cerca',

        // Other Indang barangays are intentionally commented out and excluded.
    ];

    public function run(): void
    {
        $regions = collect($this->get('regions'))
            ->filter(fn (array $row) => $row['code'] === '0400000000' || $row['name'] === 'Region IV-A (CALABARZON)')
            ->values()->all();
        $provinces = collect($this->get('provinces'))
            ->filter(fn (array $row) => $row['name'] === 'Cavite'
                && $this->parentName($row, 'region') === 'Region IV-A (CALABARZON)')
            ->values()->all();
        $places = collect($this->get('cities-municipalities'))
            ->filter(fn (array $row) => $this->parentName($row, 'province') === 'Cavite'
                && $this->parentName($row, 'region') === 'Region IV-A (CALABARZON)')
            ->values()->all();
        $barangays = collect($this->getBarangays(array_values(array_filter(
            $places,
            fn (array $row) => $row['name'] === 'Indang'
        ))))
            ->filter(fn (array $row) => in_array($row['name'], self::INDANG_BARANGAYS, true))
            ->values()
            ->all();

        $regionIds = [];
        $regionNames = [];
        foreach ($regions as $row) {
            $region = Region::updateOrCreate(['code' => $row['code']], ['name' => $this->repairEncoding($row['name'])]);
            $regionIds[$row['code']] = $region->id;
            $regionNames[$row['name']] = $row['code'];
        }

        $provinceIds = [];
        $provinceNames = [];
        foreach ($provinces as $row) {
            $regionCode = $this->parentCode($row, 'region');
            $regionCode = $regionNames[$regionCode] ?? $regionCode;
            if (! isset($regionIds[$regionCode])) {
                continue;
            }
            $province = Province::updateOrCreate(['code' => $row['code']], [
                'region_id' => $regionIds[$regionCode], 'name' => $this->repairEncoding($row['name']),
            ]);
            $provinceIds[$row['code']] = $province->id;
            $provinceNames[$this->parentName($row, 'region').'|'.$row['name']] = $row['code'];
        }

        $municipalityIds = [];
        $municipalityNames = [];
        foreach ($places as $row) {
            $provinceCode = $this->parentCode($row, 'province');
            $regionCode = $this->parentCode($row, 'region');
            $provinceCode = $provinceNames[$regionCode.'|'.$provinceCode] ?? $provinceCode;
            $regionCode = $regionNames[$regionCode] ?? $regionCode;
            $provinceId = $provinceIds[$provinceCode] ?? null;
            if (! $provinceId) {
                continue;
            }
            $municipality = Municipality::updateOrCreate(['code' => $row['code']], [
                'province_id' => $provinceId, 'name' => $this->repairEncoding($row['name']),
            ]);
            $municipalityIds[$row['code']] = $municipality->id;
            $municipalityNames[$this->parentName($row, 'region').'|'.$this->parentName($row, 'province').'|'.$row['name']] = $row['code'];
        }

        $now = now();
        $barangayRows = [];
        foreach ($barangays as $row) {
            $placeCode = $this->parentCode($row, 'city_municipality') ?: $row['municipality_code'] ?? null;
            $placeCode = $municipalityNames[$this->parentName($row, 'region').'|'.$this->parentName($row, 'province').'|'.$placeCode] ?? $placeCode;
            $municipalityId = $municipalityIds[$placeCode] ?? null;
            if (! $municipalityId) {
                continue;
            }
            $barangayRows[] = [
                'id' => (string) Str::uuid(), 'municipality_id' => $municipalityId, 'name' => $this->repairEncoding($row['name']),
                'municipality' => $this->parentName($row, 'city_municipality'), 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($barangayRows, 1000) as $batch) {
            Barangay::upsert($batch, ['municipality_id', 'name'], ['municipality', 'updated_at']);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function get(string $resource): array
    {
        $all = [];
        $firstCode = null;
        for ($page = 1; $page <= 500; $page++) {
            $response = Http::retry(3, 500)->timeout(60)->get(self::API.'/'.$resource, ['page' => $page])->throw()->json();
            $data = $response['data'] ?? $response;
            if (! is_array($data)) {
                throw new \RuntimeException('Invalid PSGC response for '.$resource);
            }
            $rows = array_values(array_filter($data, 'is_array'));
            if ($rows === []) {
                break;
            }
            $pageCode = $rows[0]['code'] ?? null;
            if ($page > 1 && $pageCode === $firstCode) {
                break;
            }
            $firstCode ??= $pageCode;
            $all = [...$all, ...$rows];
            if (count($rows) < 100) {
                break;
            }
        }

        return $all;
    }

    /** @param array<int, array<string, mixed>> $places */
    private function getBarangays(array $places): array
    {
        $all = [];
        foreach ($places as $place) {
            $response = Http::retry(3, 1000)->timeout(60)
                ->get(self::API.'/cities-municipalities/'.$place['code'].'/barangays')
                ->throw()->json();
            $rows = $response['data'] ?? $response;
            if (is_array($rows)) {
                $all = [...$all, ...array_filter($rows, 'is_array')];
            }
        }

        return $all;
    }

    /** @param array<string, mixed> $row */
    private function parentCode(array $row, string $parent): ?string
    {
        $value = $row[$parent] ?? null;
        if (is_array($value)) {
            return isset($value['code']) ? (string) $value['code'] : null;
        }

        $flatKey = $parent.'_code';

        return isset($row[$flatKey]) ? (string) $row[$flatKey] : (is_string($value) ? $value : null);
    }

    /** @param array<string, mixed> $row */
    private function parentName(array $row, string $parent): ?string
    {
        $value = $row[$parent] ?? null;
        if (is_array($value) && isset($value['name'])) {
            return (string) $value['name'];
        }

        return is_string($value) ? $value : null;
    }

    private function repairEncoding(?string $value): ?string
    {
        if ($value === null || ! preg_match('/[ÃÂ]/u', $value)) {
            return $value;
        }

        $latin = iconv('UTF-8', 'ISO-8859-1//IGNORE', $value);

        return $latin === false ? $value : (iconv('ISO-8859-1', 'UTF-8//IGNORE', $latin) ?: $value);
    }
}
