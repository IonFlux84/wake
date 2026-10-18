<?php

// Team accounts are files in data/accounts (see Wake\Accounts).

return [

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'accounts',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'accounts',
        ],
    ],

    'providers' => [
        'accounts' => [
            'driver' => 'wake',
        ],
    ],

    'passwords' => [
        // Reset tokens live in the file cache, so no database is needed.
        'accounts' => [
            'driver' => 'cache',
            'store' => 'file',
            'provider' => 'accounts',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
