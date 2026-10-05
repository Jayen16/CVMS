<?php

return [
    'source' => 'PIDSP 2026 Childhood Immunization Schedule',
    'source_url' => 'https://www.pidsphil.org/home/wp-content/uploads/2026/06/Revised-July-2026-PIDSP-Immunization-Calendar_ea6.pdf',
    // A dose is delayed after its due date and becomes overdue at this threshold.
    'overdue_threshold_days' => (int) env('IMMUNIZATION_OVERDUE_THRESHOLD_DAYS', 7),
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

    // Parent-facing copy is intentionally general. Clinical decisions remain with the health worker.
    'vaccine_information' => [
        'bcg' => ['summary' => 'BCG helps protect children against tuberculosis, especially serious forms of TB in young children. It is given by injection.'],
        'hepb' => ['summary' => 'Hepatitis B vaccine helps protect against hepatitis B infection, which can affect the liver. It is given by injection.'],
        'dtap' => ['summary' => 'DTaP / DTwP-containing vaccines help protect against diphtheria, tetanus, and pertussis (whooping cough). It is given by injection.'],
        'opv' => ['summary' => 'Oral Polio Vaccine helps protect against poliovirus and is given by mouth.'],
        'ipv' => ['summary' => 'Inactivated Polio Vaccine helps protect against poliovirus and is given by injection.'],
        'hib' => ['summary' => 'Haemophilus influenzae type b vaccine helps protect against serious Hib infections. It is given by injection.'],
        'pcv' => ['summary' => 'Pneumococcal conjugate vaccine helps protect against serious infections caused by pneumococcal bacteria. It is given by injection.'],
        'rv' => ['summary' => 'Rotavirus vaccine helps protect babies and young children from rotavirus illness, which can cause severe diarrhea and dehydration.'],
        'mmr' => ['summary' => 'MMR vaccine helps protect against measles, mumps, and rubella. It is given by injection.'],
        'var' => ['summary' => 'Varicella vaccine helps protect against chickenpox. It is given by injection.'],
        'hep_a' => ['summary' => 'Hepatitis A vaccine helps protect against hepatitis A infection, which affects the liver. It is given by injection.'],
        'influenza' => ['summary' => 'Influenza vaccine helps protect against influenza and is recommended according to the current local schedule. It is given by injection.'],
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
