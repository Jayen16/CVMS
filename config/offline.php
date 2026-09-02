<?php

use App\Models\AdverseEventReport;
use App\Models\ChildProfile;
use App\Models\ClinicAnnouncement;
use App\Models\VaccinationRecord;

return [
    'enabled' => env('OFFLINE_ENABLED', env('DB_CONNECTION') === 'sqlite'),

    'auto_queue_models' => [
        ChildProfile::class,
        VaccinationRecord::class,
        ClinicAnnouncement::class,
        AdverseEventReport::class,
    ],
];
