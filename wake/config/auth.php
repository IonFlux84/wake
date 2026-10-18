<?php

// Team accounts are files in storage/accounts (see Wake\Accounts).

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
        'accounts' => [
            'provider' => 'accounts',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
