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

    public function run(): void
    {
        $regions = $this->get('regions');
        if (! collect($regions)->contains('code', '1800000000')) {
            $regions[] = ['code' => '1800000000', 'name' => 'Negros Island Region (NIR)'];
        }
        $provinces = collect($this->get('provinces'))
            ->filter(fn (array $row) => $row['name'] === 'Cavite' && $this->parentName($row, 'region') === 'Region IV-A (CALABARZON)')
            ->values()->all();
        $places = collect($this->get('cities-municipalities'))
            ->filter(fn (array $row) => $row['name'] === 'Indang' && $this->parentName($row, 'province') === 'Cavite' && $this->parentName($row, 'region') === 'Region IV-A (CALABARZON)')
            ->values()->all();
        $barangays = $this->getBarangays($places);

        $regionIds = [];
        $regionNames = [];
        foreach ($regions as $row) {
            $region = Region::updateOrCreate(['code' => $row['code']], ['name' => $row['name']]);
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
                'region_id' => $regionIds[$regionCode], 'name' => $row['name'],
            ]);
            $provinceIds[$row['code']] = $province->id;
            $provinceNames[$this->parentName($row, 'region').'|'.$row['name']] = $row['code'];
        }

        // NCR has no province in PSGC, but the normalized schema keeps province required.
        $ncrId = $regionIds['1300000000'] ?? null;
        if ($ncrId && ! isset($provinceIds['NCR'])) {
            $provinceIds['NCR'] = Province::firstOrCreate(['region_id' => $ncrId, 'name' => 'National Capital Region (NCR)'])->id;
        }

        $municipalityIds = [];
        $municipalityNames = [];
        foreach ($places as $row) {
            $provinceCode = $this->parentCode($row, 'province');
            $regionCode = $this->parentCode($row, 'region');
            $provinceCode = $provinceNames[$regionCode.'|'.$provinceCode] ?? $provinceCode;
            $regionCode = $regionNames[$regionCode] ?? $regionCode;
            $provinceId = $provinceIds[$provinceCode] ?? ($regionCode === '1300000000' ? $provinceIds['NCR'] ?? null : null);
            if (! $provinceId) {
                continue;
            }
            $municipality = Municipality::updateOrCreate(['code' => $row['code']], [
                'province_id' => $provinceId, 'name' => $row['name'],
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
                'id' => (string) Str::uuid(), 'municipality_id' => $municipalityId, 'name' => $row['name'],
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
}
