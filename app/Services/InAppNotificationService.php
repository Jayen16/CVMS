<?php

namespace App\Services;

use App\Models\User;
// use App\Models\ClinicAnnouncement;
use App\Models\VaccinationRecord;
use App\Notifications\InAppNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class InAppNotificationService
{
    public function vaccinationSubmitted(VaccinationRecord $record): void
    {
        $record->loadMissing(['child', 'vaccineType', 'submitter']);

        if ($record->submitted_by === null) {
            return;
        }

        $this->staffForBarangay($record->child->barangay_id)
            ->each(fn (User $nurse) => $nurse->notify(new InAppNotification(
                key: "vaccination-submitted:{$record->id}",
                title: 'New vaccination submission',
                body: "{$record->submitter?->name} submitted {$record->vaccineType?->name} for {$record->child->full_name}.",
                actionUrl: route('verification-queue.index'),
                icon: 'clipboard-document-check',
            )));
    }

    public function vaccinationVerified(VaccinationRecord $record): void
    {
        $this->verificationResult($record, 'verified');
    }

    public function vaccinationRejected(VaccinationRecord $record): void
    {
        $this->verificationResult($record, 'rejected');
    }

    public function vaccinationDue(User $parent, string $key, string $body, string $actionUrl): void
    {
        $this->notifyOnce($parent, $key, new InAppNotification(
            key: $key,
            title: 'Vaccination reminder',
            body: $body,
            actionUrl: $actionUrl,
            icon: 'bell-alert',
        ));
    }

    /* Announcement notifications disabled temporarily. Keep this implementation for reactivation.
    public function announcementPublished(ClinicAnnouncement $announcement): void
    {
        $announcement->loadMissing(['barangay', 'region', 'province', 'municipality']);

        $this->audienceUsers($announcement)->each(function (User $user) use ($announcement): void {
            $this->notifyOnce($user, "announcement:{$announcement->id}", new InAppNotification(
                key: "announcement:{$announcement->id}",
                title: $announcement->title,
                body: $announcement->message,
                actionUrl: route('announcements.index'),
                icon: 'megaphone',
            ));
        });
    }
    */

    private function verificationResult(VaccinationRecord $record, string $status): void
    {
        $record->loadMissing(['child', 'vaccineType', 'submitter']);
        $parent = $record->submitter;

        if (! $parent) {
            return;
        }

        $label = $status === 'verified' ? 'approved' : 'rejected';
        $this->notifyOnce($parent, "vaccination-{$status}:{$record->id}", new InAppNotification(
            key: "vaccination-{$status}:{$record->id}",
            title: "Vaccination submission {$label}",
            body: "The {$record->vaccineType?->name} record for {$record->child->full_name} was {$label}.",
            actionUrl: route('children.show', $record->child),
            icon: $status === 'verified' ? 'check-circle' : 'x-circle',
        ));
    }

    // @return Collection<int, User>
    private function staffForBarangay(?string $barangayId)
    {
        return User::query()
            ->where('is_active', true)
            ->where('barangay_id', $barangayId)
            ->where(function ($query): void {
                $query->where('role', 'nurse')->orWhereJsonContains('roles', 'nurse');
            })
            ->get();
    }

    /* Kept disabled together with announcementPublished().
    // @return Collection<int, User>
    private function audienceUsers(ClinicAnnouncement $announcement)
    {
        return User::query()
            ->where('is_active', true)
            ->when($announcement->audience === 'parents', fn ($query) => $query->where(function ($query): void {
                $query->where('role', 'parent')->orWhereJsonContains('roles', 'parent');
            }))
            ->when($announcement->audience === 'staff', fn ($query) => $query->where(function ($query): void {
                $query->whereIn('role', ['admin', 'barangay_admin', 'nurse'])
                    ->orWhereJsonContains('roles', 'superadmin')
                    ->orWhereJsonContains('roles', 'barangay_admin')
                    ->orWhereJsonContains('roles', 'nurse');
            }))
            ->where(function ($query) use ($announcement): void {
                if (! $announcement->region_id && ! $announcement->province_id && ! $announcement->municipality_id && ! $announcement->barangay_id) {
                    $query->whereNotNull('users.id');
                } elseif ($announcement->barangay_id) {
                    $query->where(function ($users) use ($announcement): void {
                        $users->whereNull('users.barangay_id')->orWhere('users.barangay_id', $announcement->barangay_id);
                    });
                } else {
                    $query->where(function ($users) use ($announcement): void {
                        $users->whereNull('users.barangay_id')
                            ->when($announcement->region_id, fn ($scope) => $scope->orWhereHas('barangay.municipalityRelation.province', fn ($location) => $location->whereKey($announcement->region_id)))
                            ->when($announcement->province_id, fn ($scope) => $scope->orWhereHas('barangay.municipalityRelation', fn ($location) => $location->where('province_id', $announcement->province_id)))
                            ->when($announcement->municipality_id, fn ($scope) => $scope->orWhereHas('barangay', fn ($location) => $location->where('municipality_id', $announcement->municipality_id)));
                    });
                }
            })
            ->get();
    }
    */

    private function notifyOnce(User $user, string $key, InAppNotification $notification): void
    {
        $alreadyExists = $user->notifications()
            ->where('type', InAppNotification::class)
            ->get()
            ->contains(fn (DatabaseNotification $stored) => ($stored->data['key'] ?? null) === $key);

        if (! $alreadyExists) {
            $user->notify($notification);
            app(\App\Services\OfflineSyncService::class)->queueNotification($user, $notification->toArray($user));
        }
    }
}
