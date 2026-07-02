<?php

return [
    'source' => 'PIDSP 2026 Childhood Immunization Schedule',
    'source_url' => 'https://www.pidsphil.org/home/wp-content/uploads/2026/06/Revised-July-2026-PIDSP-Immunization-Calendar_ea6.pdf',
    'version' => [
        'name' => 'PIDSP 2026 Revised July',
        'version_code' => '2026.1',
        'effective_date' => '2026-07-01',
        'status' => 'active',
        'notes' => 'Seeded from the revised July 2026 PIDSP schedule reference.',
    ],

    'vaccines' => [
        ['code' => 'bcg', 'name' => 'BCG'],
        ['code' => 'hepb', 'name' => 'Hepatitis B'],
        ['code' => 'dtap', 'name' => 'DTaP / DTwP-containing vaccine'],
        ['code' => 'opv', 'name' => 'Oral Polio Vaccine'],
        ['code' => 'ipv', 'name' => 'Inactivated Polio Vaccine'],
        ['code' => 'hib', 'name' => 'Haemophilus influenzae type b'],
        ['code' => 'pcv', 'name' => 'Pneumococcal Conjugate Vaccine'],
        ['code' => 'rv', 'name' => 'Rotavirus'],
        ['code' => 'mmr', 'name' => 'Measles, Mumps, Rubella'],
        ['code' => 'var', 'name' => 'Varicella'],
        ['code' => 'hep_a', 'name' => 'Hepatitis A'],
        ['code' => 'influenza', 'name' => 'Influenza'],
    ],

    'routine_schedule' => [
        'bcg' => [
            ['dose' => 1, 'age' => ['days' => 0], 'label' => 'At birth'],
        ],
        'hepb' => [
            ['dose' => 1, 'age' => ['days' => 0], 'label' => 'At birth'],
            ['dose' => 2, 'age' => ['months' => 1], 'label' => '1 month'],
            ['dose' => 3, 'age' => ['months' => 6], 'label' => '6 months'],
        ],
        'dtap' => [
            ['dose' => 1, 'age' => ['weeks' => 6], 'label' => '6 weeks'],
            ['dose' => 2, 'age' => ['weeks' => 10], 'label' => '10 weeks'],
            ['dose' => 3, 'age' => ['weeks' => 14], 'label' => '14 weeks'],
            ['dose' => 4, 'age' => ['months' => 15], 'label' => '15 months'],
            ['dose' => 5, 'age' => ['years' => 4], 'label' => '4 years'],
        ],
        'opv' => [
            ['dose' => 1, 'age' => ['weeks' => 6], 'label' => '6 weeks'],
            ['dose' => 2, 'age' => ['weeks' => 10], 'label' => '10 weeks'],
            ['dose' => 3, 'age' => ['weeks' => 14], 'label' => '14 weeks'],
        ],
        'ipv' => [
            ['dose' => 1, 'age' => ['weeks' => 14], 'label' => '14 weeks'],
            ['dose' => 2, 'age' => ['months' => 9], 'label' => '9 months'],
        ],
        'hib' => [
            ['dose' => 1, 'age' => ['weeks' => 6], 'label' => '6 weeks'],
            ['dose' => 2, 'age' => ['weeks' => 10], 'label' => '10 weeks'],
            ['dose' => 3, 'age' => ['weeks' => 14], 'label' => '14 weeks'],
            ['dose' => 4, 'age' => ['months' => 12], 'label' => '12 months'],
        ],
        'pcv' => [
            ['dose' => 1, 'age' => ['weeks' => 6], 'label' => '6 weeks'],
            ['dose' => 2, 'age' => ['weeks' => 10], 'label' => '10 weeks'],
            ['dose' => 3, 'age' => ['weeks' => 14], 'label' => '14 weeks'],
            ['dose' => 4, 'age' => ['months' => 12], 'label' => '12 months'],
        ],
        'rv' => [
            ['dose' => 1, 'age' => ['weeks' => 6], 'label' => '6 weeks'],
            ['dose' => 2, 'age' => ['weeks' => 10], 'label' => '10 weeks'],
            ['dose' => 3, 'age' => ['weeks' => 14], 'label' => '14 weeks'],
        ],
        'mmr' => [
            ['dose' => 1, 'age' => ['months' => 9], 'label' => '9 months'],
            ['dose' => 2, 'age' => ['months' => 12], 'label' => '12 months'],
        ],
        'var' => [
            ['dose' => 1, 'age' => ['months' => 12], 'label' => '12 months'],
            ['dose' => 2, 'age' => ['years' => 4], 'label' => '4 years'],
        ],
        'hep_a' => [
            ['dose' => 1, 'age' => ['months' => 12], 'label' => '12 months'],
            ['dose' => 2, 'age' => ['months' => 18], 'label' => '18 months'],
        ],
        'influenza' => [
            ['dose' => 1, 'age' => ['months' => 6], 'label' => '6 months and yearly after'],
        ],
    ],
];
