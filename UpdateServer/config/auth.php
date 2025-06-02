<?php // config/auth.php

return [
    'defaults' => [
        'guard' => 'web', // Default guard remains 'web' for users
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [ // For regular users
            'driver' => 'session',
            'provider' => 'users',
        ],
        'admin' => [ // <<< NEW GUARD FOR ADMINS >>>
            'driver' => 'session',
            'provider' => 'admins', // Use the 'admins' provider defined below
        ],
    ],

    'providers' => [
        'users' => [ // For regular users
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
        'admins' => [ // <<< NEW PROVIDER FOR ADMINS >>>
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class, // Assuming your Admin model is here
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
         'admins' => [ // <<< NEW PASSWORD BROKER FOR ADMINS (Optional but recommended) >>>
             'provider' => 'admins',
             'table' => 'password_reset_tokens', // Can use the same table or a different one
             'expire' => 60,
             'throttle' => 60,
         ],
    ],

    'password_timeout' => 10800,
];