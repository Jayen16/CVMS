<?php

namespace App\Services;

use App\Models\ClinicAnnouncement;
use App\Models\ChildTransferHistory;
use App\Models\FacilityStaff;
use App\Models\FacilityConnection;
use App\Models\VaccineSchedule;
use App\Models\VaccineType;
use App\Models\ParentChangeRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Passport\Client;

class CentralSyncService
{
    /** @return array<string, mixed> */
    public function pull(Request $request, ?string $cursor, ?Client $client = null): array
    {
        $client ??= auth('api')->client();
        $connection = $client ? FacilityConnection::query()->where('passport_client_id', $client->getKey())->where('status', 'active')->first() : null;

        abort_unless($connection, 403, 'Facility connection is not active.');

        $after = $cursor ? Carbon::parse($cursor) : null;
        $data = [
            'facility_staff' => $this->serializeModels(FacilityStaff::query()->where('facility_id', $connection->facility_id)->when($after, fn (Builder $query) => $query->where('updated_at', '>', $after))->orderBy('updated_at')->get(), fn (FacilityStaff $staff): array => [
                'uuid' => (string) $staff->staff_uuid, 'name' => $staff->name, 'role' => $staff->role, 'active' => $staff->active,
                'last_seen_at' => $staff->last_seen_at?->toIso8601String(), 'updated_at' => $staff->updated_at?->toIso8601String(),
            ]),
            'child_transfers' => $this->serializeModels(ChildTransferHistory::query()->where('facility_uuid', $connection->facility_id)->when($after, fn (Builder $query) => $query->where('updated_at', '>', $after))->orderBy('updated_at')->get(), fn (ChildTransferHistory $transfer): array => [
                'uuid' => (string) $transfer->id, 'child_uuid' => $transfer->child_sync_uuid, 'facility_uuid' => $transfer->facility_uuid,
                'from_barangay_name' => $transfer->from_barangay_name, 'to_barangay_name' => $transfer->to_barangay_name,
                'municipality_code' => $transfer->municipality_code, 'transferred_by_uuid' => $transfer->transferred_by_uuid,
                'transferred_by_name' => $transfer->transferred_by_name, 'transferred_by_role' => $transfer->transferred_by_role,
                'transferred_at' => $transfer->transferred_at?->toIso8601String(), 'reason' => $transfer->reason,
                'version' => $transfer->sync_version, 'updated_at' => $transfer->updated_at?->toIso8601String(),
            ]),
            'vaccines' => $this->serializeModels(VaccineType::query()->when($after, fn (Builder $query) => $query->where('updated_at', '>', $after))->orderBy('updated_at')->get(), fn (VaccineType $vaccine): array => [
                'uuid' => (string) $vaccine->getKey(),
                'code' => $vaccine->code,
                'name' => $vaccine->name,
                'active' => $vaccine->active,
                'created_at' => $vaccine->created_at?->toIso8601String(),
                'updated_at' => $vaccine->updated_at?->toIso8601String(),
            ]),
            'schedule_rules' => $this->serializeModels(VaccineSchedule::query()->with('vaccineType')->when($after, fn (Builder $query) => $query->where('updated_at', '>', $after))->orderBy('updated_at')->get(), fn (VaccineSchedule $schedule): array => [
                'uuid' => (string) $schedule->getKey(),
                'vaccine_uuid' => (string) $schedule->vaccine_type_id,
                'dose_number' => $schedule->dose_number,
                'age_days' => $schedule->age_days,
                'age_weeks' => $schedule->age_weeks,
                'age_months' => $schedule->age_months,
                'age_years' => $schedule->age_years,
                'label' => $schedule->label,
                'indication' => $schedule->indication,
                'notes' => $schedule->notes,
                'active' => $schedule->active,
                'created_at' => $schedule->created_at?->toIso8601String(),
                'updated_at' => $schedule->updated_at?->toIso8601String(),
            ]),
            'announcements' => $this->serializeModels(ClinicAnnouncement::query()->with(['region', 'province', 'municipality', 'barangay'])->when($after, fn (Builder $query) => $query->where('updated_at', '>', $after))->orderBy('updated_at')->get(), fn (ClinicAnnouncement $announcement): array => [
                'uuid' => (string) ($announcement->sync_uuid ?: $announcement->getKey()),
                'title' => $announcement->title,
                'category' => $announcement->category,
                'audience' => $announcement->audience,
                'starts_on' => $announcement->starts_on?->toDateString(),
                'ends_on' => $announcement->ends_on?->toDateString(),
                'location' => $announcement->location,
                'message' => $announcement->message,
                'active' => $announcement->active,
                'region_code' => $announcement->region?->code,
                'province_code' => $announcement->province?->code,
                'municipality_code' => $announcement->municipality?->code,
                'barangay_name' => $announcement->barangay?->name,
                'updated_at' => $announcement->updated_at?->toIso8601String(),
            ]),
            'parent_change_requests' => $this->serializeModels(ParentChangeRequest::query()->where('facility_id', $connection->facility_id)->when($after, fn (Builder $query) => $query->where('updated_at', '>', $after))->orderBy('updated_at')->get(), fn (ParentChangeRequest $request): array => [
                'uuid' => (string) $request->request_uuid, 'child_uuid' => (string) $request->child_uuid, 'parent_uuid' => $request->parent_uuid,
                'request_type' => $request->request_type, 'requested_data' => $request->requested_data, 'status' => $request->status,
                'reviewer_name' => $request->reviewer_name, 'reviewer_note' => $request->reviewer_note, 'updated_at' => $request->updated_at?->toIso8601String(),
            ]),
        ];

        $newCursor = now()->toIso8601String();
        $connection->update(['last_synchronized_at' => now()]);

        return ['cursor' => $newCursor, 'server_time' => now()->toIso8601String(), 'data' => $data];
    }

    /** @param iterable<mixed> $models @return array<int, array<string, mixed>> */
    private function serializeModels(iterable $models, callable $serializer): array
    {
        return collect($models)->map($serializer)->values()->all();
    }
}
