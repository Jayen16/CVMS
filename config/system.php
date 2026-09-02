<?php

return [
    'instance_type' => env('APP_INSTANCE_TYPE', 'facility'),
    'activation_code_ttl_hours' => (int) env('ACTIVATION_CODE_TTL_HOURS', 24),
];
