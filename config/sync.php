<?php

use App\Models\ClinicAnnouncement;
use App\Models\VaccineSchedule;
use App\Models\VaccineType;
use App\Models\ChildAppointment;
use App\Models\ParentChangeRequest;

return [
    'entities' => [
        'vaccines' => [
            'model' => VaccineType::class,
            'owner' => 'central',
            'direction' => 'central_to_facility',
            'order' => 10,
        ],
        'schedule_rules' => [
            'model' => VaccineSchedule::class,
            'owner' => 'central',
            'direction' => 'central_to_facility',
            'order' => 20,
        ],
        'announcements' => [
            'model' => ClinicAnnouncement::class,
            'owner' => 'central',
            'direction' => 'central_to_facility',
            'order' => 30,
        ],
        'guardians' => ['model' => \App\Models\FacilityGuardian::class, 'owner' => 'facility', 'direction' => 'facility_to_central', 'order' => 40],
        'child_guardian_relationships' => ['model' => \App\Models\FacilityChildGuardian::class, 'owner' => 'facility', 'direction' => 'facility_to_central', 'order' => 50],
        'appointments' => ['model' => ChildAppointment::class, 'owner' => 'facility', 'direction' => 'facility_to_central', 'order' => 60],
        'inventory_transactions' => ['model' => \App\Models\FacilityInventoryTransaction::class, 'owner' => 'facility', 'direction' => 'facility_to_central', 'order' => 70],
        'parent_change_requests' => ['model' => ParentChangeRequest::class, 'owner' => 'central', 'direction' => 'central_to_facility', 'order' => 80],
        'notification_requests' => ['model' => null, 'owner' => 'facility', 'direction' => 'facility_to_central', 'order' => 90],
        'audit_events' => ['model' => \App\Models\AuditLog::class, 'owner' => 'facility', 'direction' => 'facility_to_central', 'order' => 100],
    ],
];
