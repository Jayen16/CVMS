<?php

return [
    'enabled' => env('OFFLINE_ENABLED', env('DB_CONNECTION') === 'sqlite'),

    'local_connection' => env('OFFLINE_LOCAL_CONNECTION', env('DB_CONNECTION', 'sqlite')),

    'remote_connection' => env('OFFLINE_REMOTE_CONNECTION', 'mysql'),

    'auto_queue_models' => [
        App\Models\ChildProfile::class,
        App\Models\VaccinationRecord::class,
        App\Models\ClinicAnnouncement::class,
        App\Models\AdverseEventReport::class,
    ],
];
