<?php

namespace App\Http\Controllers;

use App\Models\AdverseEventReport;
use App\Models\ChildProfile;
use App\Models\VaccinationRecord;
use App\Services\OfflineSyncService;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdverseEventReportController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->canViewAefiReports(), 403);

        $reports = AdverseEventReport::query()
            ->with(['child.barangay', 'vaccineType', 'reporter'])
            ->when(
                ! auth()->user()->isSuperAdmin(),
                fn ($query) => $query->whereHas('child', fn ($child) => $child->whereIn('barangay_id', auth()->user()->accessibleBarangayIds()))
            )
            ->latest('event_date')
            ->paginate(15);

        return view('aefi.index', ['reports' => $reports]);
    }

    public function csv()
    {
        abort_unless(auth()->user()->canViewAefiReports(), 403);

        $reports = AdverseEventReport::query()
            ->with(['child.barangay', 'vaccineType', 'reporter'])
            ->when(! auth()->user()->isSuperAdmin(), fn ($query) => $query->whereHas('child', fn ($child) => $child->whereIn('barangay_id', auth()->user()->accessibleBarangayIds())))
            ->orderBy('event_date')
            ->get();

        return CsvExport::download('aefi-reports-'.now()->format('Ymd').'.csv', [
            'report_id', 'event_date', 'child_name', 'birthdate', 'barangay', 'vaccine', 'vaccine_code',
            'severity', 'outcome', 'symptoms', 'notes', 'reported_by',
        ], $reports->map(fn (AdverseEventReport $report): array => [
            $report->id,
            $report->event_date?->toDateString(),
            $report->child?->full_name,
            $report->child?->birthdate?->toDateString(),
            $report->child?->barangay?->name ?? 'Unassigned',
            $report->vaccineType?->name,
            $report->vaccineType?->code,
            $report->severity,
            $report->outcome,
            $report->symptoms,
            $report->notes,
            $report->reporter?->name,
        ]));
    }

    public function store(Request $request, ChildProfile $child, OfflineSyncService $offlineSync): RedirectResponse
    {
        abort_unless(auth()->user()->canSubmitAefiReports(), 403);
        abort_if($child->barangay_id !== auth()->user()->barangay_id, 403);

        $validated = $request->validate([
            'vaccination_record_id' => ['nullable', 'exists:vaccination_records,id'],
            'vaccine_type_id' => ['nullable', 'exists:vaccine_types,id'],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'severity' => ['required', 'in:mild,moderate,severe'],
            'outcome' => ['nullable', 'string', 'max:255'],
            'symptoms' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (filled($validated['vaccination_record_id'] ?? null)) {
            $record = VaccinationRecord::findOrFail($validated['vaccination_record_id']);
            abort_if($record->child_profile_id !== $child->id, 422, 'Selected vaccination record does not belong to this child.');
        }

        $report = AdverseEventReport::create([
            ...$validated,
            'child_profile_id' => $child->id,
            'reported_by' => auth()->id(),
        ]);
        $offlineSync->queueUpsert($report->load(['child.barangay', 'vaccinationRecord', 'vaccineType', 'reporter']));

        return to_route('children.show', $child)->with('status', 'AEFI report saved.');
    }
}
