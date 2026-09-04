<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Services\FacilityActivationService;
use App\Services\FacilitySyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FacilityActivationController extends Controller
{
    public function show(FacilityActivationService $activation): View
    {
        return view('facility.activate', ['installation' => $activation->localInstallation()]);
    }

    public function activate(Request $request, FacilityActivationService $activation, FacilitySyncService $sync): RedirectResponse
    {
        $data = $request->validate(['central_url' => ['required', 'url'], 'activation_code' => ['required', 'string', 'size:32']]);

        try {
            $activation->activateLocal($data['central_url'], $data['activation_code']);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['activation_code' => 'Activation failed. Check the central URL and activation code.'])->withInput();
        }

        try {
            $sync->synchronize();
        } catch (\Throwable $exception) {
            report($exception);

            return to_route('facility.setup')->with('status', 'Facility connected. Initial synchronization is pending; complete account setup, then use Sync now when Central is reachable.');
        }

        return to_route('facility.setup')->with('status', 'Facility connected and initial data synchronized.');
    }

    public function facilities(Request $request): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $filters = $request->validate([
            'region' => ['nullable', 'exists:regions,id'],
            'province' => ['nullable', 'exists:provinces,id'],
            'municipality' => ['nullable', 'exists:municipalities,id'],
            'barangay' => ['nullable', 'exists:barangays,id'],
        ]);
        $facilities = Facility::query()->with(['barangay.municipalityRelation.province.region'])->withCount([
            'connections as active_connections_count' => fn ($query) => $query->where('status', 'active'),
            'activationCodes',
        ])->when($filters['region'] ?? null, fn ($query, $id) => $query->whereHas('barangay.municipalityRelation.province', fn ($q) => $q->where('region_id', $id)))
            ->when($filters['province'] ?? null, fn ($query, $id) => $query->whereHas('barangay.municipalityRelation', fn ($q) => $q->where('province_id', $id)))
            ->when($filters['municipality'] ?? null, fn ($query, $id) => $query->whereHas('barangay', fn ($q) => $q->where('municipality_id', $id)))
            ->when($filters['barangay'] ?? null, fn ($query, $id) => $query->where('barangay_id', $id))
            ->latest()->get();
        $provinces = Province::query()
            ->when($filters['region'] ?? null, fn ($query, $id) => $query->where('region_id', $id))
            ->orderBy('name')->get();
        $municipalities = Municipality::query()
            ->when($filters['region'] ?? null, fn ($query, $id) => $query->whereHas('province', fn ($province) => $province->where('region_id', $id)))
            ->when($filters['province'] ?? null, fn ($query, $id) => $query->where('province_id', $id))
            ->orderBy('name')->get();
        $barangays = Barangay::query()
            ->when($filters['region'] ?? null, fn ($query, $id) => $query->whereHas('municipalityRelation.province', fn ($province) => $province->where('region_id', $id)))
            ->when($filters['province'] ?? null, fn ($query, $id) => $query->whereHas('municipalityRelation', fn ($municipality) => $municipality->where('province_id', $id)))
            ->when($filters['municipality'] ?? null, fn ($query, $id) => $query->where('municipality_id', $id))
            ->with('municipalityRelation')->orderBy('name')->get();
        $facilityBarangays = filled($filters['municipality'] ?? null) ? $barangays : collect();

        return view('central.facilities', [
            'facilities' => $facilities,
            'filters' => $filters,
            'fromGroupManagement' => $request->string('source')->toString() === 'groups',
            'regions' => Region::with('provinces.municipalities.barangays')->orderBy('name')->get(),
            'provinces' => $provinces,
            'municipalities' => $municipalities,
            'barangays' => $barangays,
            'facilityBarangays' => $facilityBarangays,
        ]);
    }

    public function storeFacility(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validate(['barangay_id' => ['required', 'exists:barangays,id'], 'code' => ['nullable', 'string', 'max:50'], 'name' => ['nullable', 'string', 'max:255']]);
        $barangay = Barangay::with('municipalityRelation')->findOrFail($data['barangay_id']);
        $name = filled($data['name'] ?? null) ? trim($data['name']) : $barangay->name.' Clinic';
        $code = filled($data['code'] ?? null) ? strtoupper(trim($data['code'])) : 'RHU-'.strtoupper(str_replace(' ', '-', $barangay->name));
        $code = substr($code, 0, 50);
        abort_if(Facility::whereRaw('UPPER(code) = ?', [$code])->exists(), 422, 'That facility code already exists. Enter a different code.');
        Facility::create(['barangay_id' => $barangay->id, 'code' => $code, 'name' => $name, 'active' => true]);

        return back()->with('status', 'Facility registered.');
    }

    public function updateFacility(Request $request, Facility $facility): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validate(['barangay_id' => ['required', 'exists:barangays,id']]);
        $facility->update(['barangay_id' => $data['barangay_id']]);

        return back()->with('status', 'Facility location assigned.');
    }

    public function issueCode(Facility $facility, FacilityActivationService $activation): RedirectResponse
    {
        abort_unless(request()->user()->isSuperAdmin(), 403);

        session()->put([
            'activation_code' => $activation->issueCode($facility),
            'activation_code_facility_id' => $facility->id,
            'activation_code_expires_at' => now()->addHours(config('system.activation_code_ttl_hours', 24))->toIso8601String(),
        ]);
        session()->flash('activation_code_notice', true);

        return back();
    }

    public function revokeConnections(Request $request, Facility $facility, FacilityActivationService $activation): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $count = $activation->revokeFacilityConnections($facility);

        return back()->with('status', $count === 1
            ? 'The facility connection was revoked.'
            : "{$count} facility connections were revoked.");
    }

    public function activateApi(Request $request, FacilityActivationService $activation): JsonResponse
    {
        $data = $request->validate(['activation_code' => ['required', 'string', 'size:32'], 'instance_uuid' => ['required', 'uuid'], 'instance_name' => ['nullable', 'string', 'max:255']]);

        return response()->json(['data' => $activation->activateCentral($data['activation_code'], $data['instance_uuid'], $data['instance_name'] ?? 'Facility installation')]);
    }
}
