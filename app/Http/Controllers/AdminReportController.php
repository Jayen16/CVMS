<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\LaravelPdf\Facades\Pdf;

class AdminReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin();

        return view('reports.index', $this->reportData($request));
    }

    public function pdf(Request $request)
    {
        $this->authorizeAdmin();

        $data = $this->reportData($request);
        $name = 'vaccination-report-'.$data['startDate']->format('Ymd').'-'.$data['endDate']->format('Ymd').'.pdf';

        return Pdf::view('reports.admin-pdf', $data)
            ->format('a4')
            ->landscape()
            ->margins(8, 8, 8, 8)
            ->name($name);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportData(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = filled($validated['start_date'] ?? null)
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : now()->startOfMonth();

        $endDate = filled($validated['end_date'] ?? null)
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : now()->endOfDay();

        $recordScope = VaccinationRecord::query()
            ->whereBetween('administered_at', [$startDate->toDateString(), $endDate->toDateString()]);

        $barangayRecords = VaccinationRecord::query()
            ->select('child_profiles.barangay_id', DB::raw('count(*) as total'))
            ->join('child_profiles', 'vaccination_records.child_profile_id', '=', 'child_profiles.id')
            ->whereBetween('vaccination_records.administered_at', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('child_profiles.barangay_id')
            ->pluck('total', 'barangay_id');

        $barangays = Barangay::query()
            ->withCount(['children', 'nurses'])
            ->orderBy('name')
            ->get()
            ->map(function (Barangay $barangay) use ($barangayRecords) {
                $barangay->report_vaccinations_count = (int) ($barangayRecords[$barangay->id] ?? 0);

                return $barangay;
            });

        $vaccines = VaccineType::query()
            ->withCount([
                'records as report_records_count' => fn ($query) => $query->whereBetween('administered_at', [
                    $startDate->toDateString(),
                    $endDate->toDateString(),
                ]),
            ])
            ->orderBy('name')
            ->get();

        $verificationCounts = (clone $recordScope)
            ->select('verification_status', DB::raw('count(*) as total'))
            ->groupBy('verification_status')
            ->pluck('total', 'verification_status');

        $sourceCounts = (clone $recordScope)
            ->select('source', DB::raw('count(*) as total'))
            ->groupBy('source')
            ->pluck('total', 'source');

        $recentRecords = (clone $recordScope)
            ->with(['child.barangay', 'vaccineType', 'recorder'])
            ->latest('administered_at')
            ->take(25)
            ->get();

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'generatedAt' => now(),
            'stats' => [
                'barangays' => Barangay::count(),
                'nurses' => User::where('role', 'nurse')->count(),
                'children' => ChildProfile::count(),
                'vaccinations' => (clone $recordScope)->count(),
                'pending' => VaccinationRecord::where('verification_status', 'pending')->count(),
            ],
            'barangays' => $barangays,
            'vaccines' => $vaccines,
            'verificationCounts' => $verificationCounts,
            'sourceCounts' => $sourceCounts,
            'recentRecords' => $recentRecords,
        ];
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }
}
