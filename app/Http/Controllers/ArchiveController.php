<?php

namespace App\Http\Controllers;

use App\Models\AdverseEventReport;
use App\Models\AuditLog;
use App\Models\ChildProfile;
use App\Models\ReportExport;
use App\Models\VaccinationRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArchiveController extends Controller
{
    private const TYPES = [
        'aefi' => [
            'label' => 'AEFI reports',
            'model' => AdverseEventReport::class,
            'date' => 'event_date',
        ],
        'vaccinations' => [
            'label' => 'Vaccination records',
            'model' => VaccinationRecord::class,
            'date' => 'administered_at',
        ],
        'exports' => [
            'label' => 'Saved report exports',
            'model' => ReportExport::class,
            'date' => 'created_at',
        ],
        'audit_logs' => [
            'label' => 'Audit logs',
            'model' => AuditLog::class,
            'date' => 'created_at',
        ],
    ];

    public function index(): View
    {
        abort_unless(auth()->user()->canArchiveReports(), 403);

        $types = self::TYPES;
        if (! auth()->user()->canArchiveAuditLogs()) {
            unset($types['audit_logs']);
        }

        $archived = collect();
        foreach ($types as $type => $definition) {
            $records = $definition['model']::query()
                ->withoutGlobalScope('not_archived')
                ->whereNotNull('archived_at')
                ->when($type === 'aefi', fn ($query) => $query->whereHas('child', fn ($child) => $this->scopeChild($child)))
                ->when($type === 'vaccinations', fn ($query) => $query->whereHas('child', fn ($child) => $this->scopeChild($child)))
                ->when($type === 'exports' && ! auth()->user()->isSuperAdmin(), fn ($query) => $query->where('user_id', auth()->id()))
                ->with($type === 'aefi' || $type === 'vaccinations' ? ['child'] : ['user'])
                ->latest('archived_at')
                ->limit(100)
                ->get()
                ->map(fn ($record) => [
                    'type' => $type,
                    'label' => $definition['label'],
                    'id' => $record->id,
                    'description' => $this->description($type, $record),
                    'record_date' => $record->{$definition['date']}?->format('M d, Y') ?? 'Undated',
                    'archived_at' => $record->archived_at?->format('M d, Y h:i A'),
                    'reason' => $record->archive_reason,
                ]);

            $archived = $archived->concat($records);
        }

        return view('archives.index', [
            'types' => $types,
            'archived' => $archived->sortByDesc('archived_at')->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canArchiveReports(), 403);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(self::TYPES))],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'archive_reason' => ['required', 'string', 'max:100'],
        ]);

        if ($validated['type'] === 'audit_logs') {
            abort_unless(auth()->user()->canArchiveAuditLogs(), 403);
        }

        $definition = self::TYPES[$validated['type']];
        $query = $definition['model']::query()
            ->whereBetween($definition['date'], [$validated['date_from'], $validated['date_to']]);

        if ($validated['type'] === 'aefi' || $validated['type'] === 'vaccinations') {
            $query->whereHas('child', fn ($child) => $this->scopeChild($child));
        } elseif ($validated['type'] === 'exports' && ! auth()->user()->isSuperAdmin()) {
            $query->where('user_id', auth()->id());
        }

        $records = $query->get();
        abort_if($records->isEmpty(), 422, 'No active records matched that type and date range.');

        foreach ($records as $record) {
            $record->forceFill([
                'archived_at' => now(),
                'archived_by' => auth()->id(),
                'archive_reason' => $validated['archive_reason'],
            ])->save();
        }

        AuditLog::recordAction('reports_archived', 'Archived '.$records->count().' '.$definition['label'], null, [
            'type' => $validated['type'],
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'reason' => $validated['archive_reason'],
            'count' => $records->count(),
        ]);

        return to_route('archives.index')->with('status', $records->count().' '.$definition['label'].' archived.');
    }

    public function restore(Request $request, string $type, string $recordId): RedirectResponse
    {
        abort_unless(auth()->user()->canArchiveReports(), 403);
        abort_unless(isset(self::TYPES[$type]), 404);
        if ($type === 'audit_logs') {
            abort_unless(auth()->user()->canArchiveAuditLogs(), 403);
        }

        $definition = self::TYPES[$type];
        $record = $definition['model']::withoutGlobalScope('not_archived')->findOrFail($recordId);

        if ($type === 'aefi' || $type === 'vaccinations') {
            $child = ChildProfile::withoutGlobalScope('not_archived')->findOrFail($record->child_profile_id);
            abort_unless(auth()->user()->isSuperAdmin() || auth()->user()->accessibleBarangayIds()->contains($child->barangay_id), 403);
        } elseif ($type === 'exports' && ! auth()->user()->isSuperAdmin()) {
            abort_unless($record->user_id === auth()->id(), 403);
        }

        $record->forceFill(['archived_at' => null, 'archived_by' => null, 'archive_reason' => null])->save();
        AuditLog::recordAction('report_restored', 'Restored '.$definition['label'].' record', $record);

        return to_route('archives.index')->with('status', $definition['label'].' record restored.');
    }

    private function scopeChild($query): void
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin()) {
            $query->whereIn('barangay_id', $user->accessibleBarangayIds());
        }
    }

    private function description(string $type, mixed $record): string
    {
        return match ($type) {
            'aefi' => ($record->child?->full_name ?? 'Unknown child').' · '.($record->severity ?? 'AEFI'),
            'vaccinations' => ($record->child?->full_name ?? 'Unknown child').' · '.($record->vaccineType?->name ?? 'Vaccination'),
            'audit_logs' => $record->description ?? 'Audit event',
            default => strtoupper($record->format).' export · '.($record->user?->name ?? 'Unknown user'),
        };
    }
}
