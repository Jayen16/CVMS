<?php

use App\Models\ClinicAnnouncement;
use App\Models\VaccineSchedule;
use App\Models\VaccineType;

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
    ],
];
