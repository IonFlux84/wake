<?php

// Wake runs without a database and without a worker, so every job runs
// immediately in the request that dispatched it.

return [

    'default' => env('QUEUE_CONNECTION', 'sync'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
    ],

    'failed' => [
        'driver' => 'null',
    ],

];
