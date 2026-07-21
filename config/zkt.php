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
    | Device clock sync
    |--------------------------------------------------------------------------
    |
    | How often attendance machines should sync their clock with the server.
    | Local TCP devices are polled by the scheduler; ADMS devices use SyncTime
    | in the Push options handshake so they request /iclock/cdata?type=time.
    |
    */

    'time_sync_interval_seconds' => (int) env('ZKT_TIME_SYNC_INTERVAL_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Attendance Sync
    |--------------------------------------------------------------------------
    */

    'attendance_max_retries' => (int) env('ZKT_ATTENDANCE_MAX_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Remote door unlock (TCP)
    |--------------------------------------------------------------------------
    |
    | Duration in seconds for CMD_UNLOCK on TCP/UDP access machines.
    |
    */

    'door_unlock_seconds' => (int) env('ZKT_DOOR_UNLOCK_SECONDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Remote door open cooldown (mobile punch)
    |--------------------------------------------------------------------------
    |
    | Minimum seconds between door open requests from the mobile punch page.
    |
    */

    'door_open_cooldown_seconds' => (int) env('ZKT_DOOR_OPEN_COOLDOWN_SECONDS', 10),

];
