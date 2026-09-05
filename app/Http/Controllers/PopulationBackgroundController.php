<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\PopulationBackground;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PopulationBackgroundController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        abort_unless($user->canViewOversight(), 403);
        $selectedRegion = request()->string('region_id')->toString();
        $selectedProvince = request()->string('province_id')->toString();
        $selectedMunicipality = request()->string('municipality_id')->toString();
        $selectedBarangay = request()->string('barangay_id')->toString();
        $isManagePage = request()->routeIs('population-background.manage');
        $allowedMunicipalities = $user->isSuperAdmin()
            ? Municipality::query()->pluck('id')->map(fn ($id) => (string) $id)
            : collect([$user->municipality_id])->filter();
        $allowedBarangays = $user->accessibleBarangayIds()->map(fn ($id) => (string) $id);
        abort_unless($user->isSuperAdmin() || ($selectedMunicipality === '' && $selectedBarangay === '' && $selectedRegion === '' && $selectedProvince === ''), 403);
        abort_unless(($selectedMunicipality === '' || $allowedMunicipalities->contains($selectedMunicipality)) && ($selectedBarangay === '' || $allowedBarangays->contains($selectedBarangay)), 403);

        $perPage = in_array((int) request('per_page', 25), [10, 25, 50, 100], true) ? (int) request('per_page', 25) : 25;
        $requiresLocationSelection = $user->isSuperAdmin() && $selectedRegion === '' && ! $isManagePage;

        if ($requiresLocationSelection) {
            return view('population-background.index', [
                'isManagePage' => false,
                'records' => collect(),
                'manageRecords' => PopulationBackground::query()->whereRaw('1 = 0')->paginate($perPage),
                'perPage' => $perPage,
                'matrix' => collect(),
                'years' => collect(),
                'selectedMunicipality' => $selectedMunicipality,
                'selectedBarangay' => $selectedBarangay,
                'selectedRegion' => $selectedRegion,
                'selectedProvince' => $selectedProvince,
                'regions' => Region::query()->orderBy('name')->get(),
                'provinces' => collect(),
                'municipalities' => collect(),
                'barangays' => collect(),
                'canManage' => $user->canManagePopulationBackground(),
                'requiresLocationSelection' => true,
            ]);
        }

        $records = PopulationBackground::query()->visibleTo($user)->with(['municipality', 'barangay'])
            ->when($selectedRegion !== '', fn ($query) => $query->where(function ($location) use ($selectedRegion) {
                $location->whereHas('municipality.province', fn ($province) => $province->where('region_id', $selectedRegion))->orWhereHas('barangay.municipalityRelation.province', fn ($province) => $province->where('region_id', $selectedRegion));
            }))
            ->when($selectedProvince !== '', fn ($query) => $query->where(function ($location) use ($selectedProvince) {
                $location->whereHas('municipality', fn ($municipality) => $municipality->where('province_id', $selectedProvince))->orWhereHas('barangay.municipalityRelation', fn ($municipality) => $municipality->where('province_id', $selectedProvince));
            }))
            ->when($selectedBarangay !== '', fn ($query) => $query->where('barangay_id', $selectedBarangay))
            ->when($selectedBarangay === '' && $selectedMunicipality !== '', fn ($query) => $query->where(function ($location) use ($selectedMunicipality) {
                $location->where('municipality_id', $selectedMunicipality)->orWhereHas('barangay', fn ($barangay) => $barangay->where('municipality_id', $selectedMunicipality));
            }))->get();
        $manageRecords = PopulationBackground::query()->visibleTo($user)->with(['municipality', 'barangay'])->latest('reference_year')->latest()->paginate($perPage)->withQueryString();
        $years = $records->pluck('reference_year')->unique()->sort()->values();
        $matrix = $records->groupBy(fn (PopulationBackground $record) => implode('|', [$record->sex, $record->age_group]))->map(function (Collection $rows): array {
            $first = $rows->first();

            return [
                'location' => 'All locations',
                'sex' => $first->sex,
                'age_group' => $first->age_group,
                'values' => $rows->mapWithKeys(fn (PopulationBackground $row) => [$row->reference_year => $row->target_population]),
            ];
        })->values();
        $selectedBarangayRecord = $selectedBarangay !== ''
            ? Barangay::with('municipalityRelation.province.region')->find($selectedBarangay)
            : null;
        $selectedMunicipalityRecord = $selectedMunicipality !== ''
            ? Municipality::with('province.region')->find($selectedMunicipality)
            : ($selectedBarangayRecord?->municipalityRelation);
        $locationLabel = collect([
            $selectedRegion !== '' ? Region::whereKey($selectedRegion)->value('name') : $selectedMunicipalityRecord?->province?->region?->name,
            $selectedProvince !== '' ? Province::whereKey($selectedProvince)->value('name') : $selectedMunicipalityRecord?->province?->name,
            $selectedMunicipalityRecord?->name,
            $selectedBarangayRecord?->name ?? ($selectedMunicipalityRecord ? 'All barangays' : null),
        ])->filter()->implode(' · ') ?: 'All locations';
        $matrix = $matrix->map(fn (array $row) => [...$row, 'location' => $locationLabel]);

        return view('population-background.index', [
            'isManagePage' => $isManagePage,
            'records' => $records->sortByDesc('reference_year')->values(),
            'manageRecords' => $manageRecords,
            'perPage' => $perPage,
            'matrix' => $matrix,
            'years' => $years,
            'selectedMunicipality' => $selectedMunicipality,
            'selectedBarangay' => $selectedBarangay,
            'selectedRegion' => $selectedRegion,
            'selectedProvince' => $selectedProvince,
            'regions' => $user->isSuperAdmin() ? Region::orderBy('name')->get() : collect(),
            'provinces' => $user->isSuperAdmin() ? Province::when($selectedRegion !== '', fn ($query) => $query->where('region_id', $selectedRegion))->orderBy('name')->get() : collect(),
            'municipalities' => $user->isSuperAdmin() ? Municipality::when($selectedProvince !== '', fn ($query) => $query->where('province_id', $selectedProvince))->orderBy('name')->get() : Municipality::whereKey($user->municipality_id)->get(),
            'barangays' => $user->isSuperAdmin() ? Barangay::with('municipalityRelation')->when($selectedMunicipality !== '', fn ($query) => $query->where('municipality_id', $selectedMunicipality))->orderBy('name')->get() : Barangay::where('municipality_id', $user->municipality_id)->orderBy('name')->get(),
            'canManage' => $user->canManagePopulationBackground(),
            'requiresLocationSelection' => false,
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->canManagePopulationBackground(), 403);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240']]);

        $rows = $this->spreadsheetRows($request->file('file')->getRealPath(), $request->file('file')->getClientOriginalExtension());
        abort_if(count($rows) < 2, 422, 'The upload must include a header row and at least one data row.');
        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), array_shift($rows));
        $locationColumns = array_flip($headers);
        foreach (['sex', 'age_group'] as $required) {
            abort_unless(isset($locationColumns[$required]), 422, "The upload is missing the {$required} column.");
        }
        $source = $request->string('source')->trim()->toString() ?: 'Uploaded population background';
        $saved = 0;
        foreach ($rows as $row) {
            $row = array_pad($row, count($headers), null);
            $values = array_combine($headers, $row);
            if (blank($values['sex'] ?? null) || blank($values['age_group'] ?? null)) {
                continue;
            }
            $municipality = $this->findMunicipality($values['municipality'] ?? null, $user);
            $barangay = $this->findBarangay($values['barangay'] ?? null, $municipality, $user);
            abort_if($municipality === null && $barangay === null, 422, 'Each row must include a valid municipality or barangay.');
            $yearFound = false;
            foreach ($headers as $header) {
                if (! preg_match('/^\d{4}$/', $header) || blank($values[$header])) {
                    continue;
                }
                abort_unless(is_numeric($values[$header]) && (int) $values[$header] >= 0, 422, "Invalid target for {$header}.");
                $yearFound = true;
                PopulationBackground::updateOrCreate([
                    'municipality_id' => $municipality?->id,
                    'barangay_id' => $barangay?->id,
                    'reference_year' => (int) $header,
                    'age_group' => trim($values['age_group']),
                    'sex' => trim($values['sex']),
                ], ['target_population' => (int) $values[$header], 'source' => $source, 'created_by' => $user->id, 'updated_by' => $user->id]);
                $saved++;
            }
            abort_unless($yearFound, 422, 'Each row must include at least one year target.');
        }

        return back()->with('status', "Imported {$saved} population targets.");
    }

    public function template(): Response
    {
        abort_unless(auth()->user()->canManagePopulationBackground(), 403);

        return response("municipality,barangay,sex,age_group,2020,2021,2022\nCavite City,,Both Sexes,All Ages,0,0,0\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=population-background-template.csv',
        ]);
    }

    private function findMunicipality(?string $name, $user): ?Municipality
    {
        return Municipality::query()->when($user->isMunicipalAdmin(), fn ($query) => $query->whereKey($user->municipality_id))->whereRaw('lower(name) = ?', [strtolower(trim((string) $name))])->first();
    }

    private function findBarangay(?string $name, ?Municipality $municipality, $user): ?Barangay
    {
        if (blank($name)) {
            return null;
        }

        return Barangay::query()->when($municipality, fn ($query) => $query->where('municipality_id', $municipality->id))->when($user->isMunicipalAdmin(), fn ($query) => $query->where('municipality_id', $user->municipality_id))->whereRaw('lower(name) = ?', [strtolower(trim($name))])->firstOrFail();
    }

    /** @return array<int, array<int, string|null>> */
    private function spreadsheetRows(string $path, string $extension): array
    {
        if (strtolower($extension) !== 'xlsx') {
            $handle = fopen($path, 'rb');
            $rows = [];
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = $row;
            }
            fclose($handle);

            return $rows;
        }

        $zip = new \ZipArchive;
        abort_unless($zip->open($path) === true, 422, 'The Excel file could not be opened.');
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $document = simplexml_load_string($xml);
            foreach ($document->si as $item) {
                $shared[] = (string) ($item->t ?: implode('', array_map('strval', $item->xpath('.//t') ?: [])));
            }
        }
        $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $rows = [];
        foreach ($sheet->sheetData->row as $xmlRow) {
            $row = [];
            foreach ($xmlRow->c as $cell) {
                preg_match('/([A-Z]+)\d+/', (string) $cell['r'], $match);
                $index = 0;
                foreach (str_split($match[1] ?? 'A') as $letter) {
                    $index = $index * 26 + ord($letter) - 64;
                }
                $index--;
                $value = (string) ($cell->v ?? '');
                if ((string) $cell['t'] === 's') {
                    $value = $shared[(int) $value] ?? '';
                }
                $row[$index] = $value;
            }
            ksort($row);
            $rows[] = array_values($row);
        }
        $zip->close();

        return $rows;
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->canManagePopulationBackground(), 403);
        $data = $this->validated($request);
        $this->assertLocation($data, $user);
        $data['created_by'] = $user->id;
        PopulationBackground::create($data);

        return back()->with('status', 'Population background saved.');
    }

    public function update(Request $request, PopulationBackground $populationBackground): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->canManagePopulationBackground(), 403);
        abort_unless(PopulationBackground::query()->visibleTo($user)->whereKey($populationBackground->id)->exists(), 403);
        $data = $this->validated($request);
        $this->assertLocation($data, $user);
        $data['updated_by'] = $user->id;
        $populationBackground->update($data);

        return back()->with('status', 'Population background updated.');
    }

    public function destroy(PopulationBackground $populationBackground): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->canManagePopulationBackground(), 403);
        abort_unless(PopulationBackground::query()->visibleTo($user)->whereKey($populationBackground->id)->exists(), 403);
        $populationBackground->delete();

        return back()->with('status', 'Population background deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'municipality_id' => ['nullable', 'uuid', 'exists:municipalities,id'],
            'barangay_id' => ['nullable', 'uuid', 'exists:barangays,id'],
            'reference_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'age_group' => ['required', 'string', 'max:100'],
            'sex' => ['required', 'string', 'max:30'],
            'target_population' => ['required', 'integer', 'min:0'],
            'source' => ['required', 'string', 'max:255'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function assertLocation(array $data, $user): void
    {
        if (blank($data['municipality_id'] ?? null) && blank($data['barangay_id'] ?? null)) {
            throw ValidationException::withMessages(['municipality_id' => 'Select a municipality or barangay.']);
        }
        if ($user->isMunicipalAdmin()) {
            abort_unless(($data['municipality_id'] ?? null) === $user->municipality_id || ($data['barangay_id'] ?? null) !== null && Barangay::whereKey($data['barangay_id'])->where('municipality_id', $user->municipality_id)->exists(), 403);
        }
        if (filled($data['barangay_id'] ?? null) && filled($data['municipality_id'] ?? null)) {
            $barangay = Barangay::findOrFail($data['barangay_id']);
            if ($barangay->municipality_id !== null && $barangay->municipality_id !== $data['municipality_id']) {
                throw ValidationException::withMessages(['barangay_id' => 'Barangay does not belong to the selected municipality.']);
            }
        }
    }
}
