<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default ZKT Device Settings
    |--------------------------------------------------------------------------
    */

    'default_port' => (int) env('ZKT_DEFAULT_PORT', 4370),

    'default_protocol' => env('ZKT_DEFAULT_PROTOCOL', 'tcp'),

    'connection_timeout' => (int) env('ZKT_CONNECTION_TIMEOUT', 25),

    'sync_interval_minutes' => (int) env('ZKT_SYNC_INTERVAL_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Attendance Sync
    |--------------------------------------------------------------------------
    */

    'attendance_max_retries' => (int) env('ZKT_ATTENDANCE_MAX_RETRIES', 3),

];
