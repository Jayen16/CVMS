<?php

namespace App\Jobs;

use App\Models\Barangay;
use App\Models\ReportExport;
use App\Models\VaccinationRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\LaravelPdf\Facades\Pdf;
use ZipArchive;

class BuildReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(public string $exportId) {}

    public function handle(): void
    {
        $export = ReportExport::findOrFail($this->exportId);
        if ($export->status === 'ready') {
            return;
        }
        $export->update(['status' => 'processing', 'processed_items' => 0, 'error' => null]);

        $filters = $export->filters ?? [];
        if ($export->user->isSuperAdmin() && ($filters['region_id'] ?? 'all') === 'all') {
            throw new RuntimeException('Select a region before exporting reports.');
        }
        set_time_limit($this->timeout);

        $barangayQuery = Barangay::query()
            ->whereIn('id', $export->user->accessibleBarangayIds())
            ->with(['municipalityRelation.province.region'])
            ->when(($filters['region_id'] ?? 'all') !== 'all', fn ($q) => $q->whereHas('municipalityRelation.province', fn ($p) => $p->where('region_id', $filters['region_id'])))
            ->when(($filters['province_id'] ?? 'all') !== 'all', fn ($q) => $q->whereHas('municipalityRelation', fn ($p) => $p->where('province_id', $filters['province_id'])))
            ->when(($filters['municipality_id'] ?? 'all') !== 'all', fn ($q) => $q->where('municipality_id', $filters['municipality_id']))
            ->when(($filters['barangay_id'] ?? 'all') !== 'all', fn ($q) => $q->whereKey($filters['barangay_id']))
            ->orderBy('id');

        $total = $barangayQuery->count();
        if ($total === 0) {
            throw new RuntimeException('No accessible barangays match the selected export filters.');
        }
        $export->update(['total_items' => $total]);
        $base = 'report-exports/'.$export->id;
        $directory = Storage::disk('local')->path($base);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $zipPath = $directory.'.zip';
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the report ZIP file.');
        }

        $barangayQuery->chunkById(100, function ($barangays) use ($export, $filters, $directory, $zip): void {
            foreach ($barangays as $barangay) {
                $location = collect([$barangay->municipalityRelation?->province?->region?->name, $barangay->municipalityRelation?->province?->name, $barangay->municipalityRelation?->name, $barangay->name])->map(fn ($name) => $this->safeName($name ?? 'Unknown'))->implode('/');
                $csv = $this->csvFor($barangay, $filters);
                $zip->addFromString($location.'/vaccination-records.csv', $csv);

                if ($export->format === 'pdf' || $export->format === 'both') {
                    $pdfPath = $directory.'/barangay.pdf';
                    Pdf::view('reports.barangay-pdf', [
                        'barangay' => $barangay,
                        'children' => $barangay->children()->count(),
                        'vaccinations' => $this->recordsQuery($barangay, $filters)->count(),
                        'generatedAt' => now(),
                    ])->format('a4')->save($pdfPath);
                    $zip->addFromString($location.'/report.pdf', file_get_contents($pdfPath));
                    @unlink($pdfPath);
                }

                $export->increment('processed_items');
            }
        });

        if (! $zip->close()) {
            throw new RuntimeException('Could not finish the report ZIP file.');
        }
        $export->update(['status' => 'ready', 'path' => $zipPath]);
    }

    public function failed(\Throwable $exception): void
    {
        ReportExport::whereKey($this->exportId)->update(['status' => 'failed', 'error' => $exception->getMessage()]);
    }

    private function recordsQuery(Barangay $barangay, array $filters)
    {
        return VaccinationRecord::query()->whereHas('child', fn ($q) => $q->where('barangay_id', $barangay->id))
            ->whereBetween('administered_at', [$filters['start_date'], $filters['end_date']])
            ->when(($filters['schedule_version'] ?? 'all') === 'unassigned', fn ($q) => $q->whereNull('suggested_schedule_version_id'))
            ->when(isset($filters['schedule_version']) && ! in_array($filters['schedule_version'], ['all', 'unassigned'], true), fn ($q) => $q->where('suggested_schedule_version_id', $filters['schedule_version']));
    }

    private function csvFor(Barangay $barangay, array $filters): string
    {
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['record_id', 'administered_at', 'child_name', 'vaccine', 'dose_number', 'verification_status']);
        $this->recordsQuery($barangay, $filters)->with(['child', 'vaccineType'])->orderBy('id')->chunkById(1000, function ($records) use ($handle): void {
            foreach ($records as $record) {
                fputcsv($handle, [$record->id, $record->administered_at?->toDateString(), $record->child?->full_name, $record->vaccineType?->name, $record->dose_number, $record->verification_status]);
            }
        });
        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }

    private function safeName(string $name): string
    {
        return trim(preg_replace('/[^A-Za-z0-9._ -]+/', '-', $name), ' .-') ?: 'Unknown';
    }
}
