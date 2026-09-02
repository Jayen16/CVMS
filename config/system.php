<?php

return [
    'instance_type' => env('APP_INSTANCE_TYPE', 'facility'),
    'activation_code_ttl_hours' => (int) env('ACTIVATION_CODE_TTL_HOURS', 24),
    'sync_retry_base_seconds' => (int) env('SYNC_RETRY_BASE_SECONDS', 60),
    'sync_retry_max_seconds' => (int) env('SYNC_RETRY_MAX_SECONDS', 3600),
];
