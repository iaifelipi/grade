<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        /*
        |--------------------------------------------------------------------------
        | Local (Laravel padrão)
        |--------------------------------------------------------------------------
        */
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | 🔥 PRIVATE (PIXIP CANON)
        |--------------------------------------------------------------------------
        | storage/app/private/tenants/{uuid}/...
        |--------------------------------------------------------------------------
        */
        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private/tenants'),
            'visibility' => 'private',
            'throw' => false,
            'permissions' => [
                'file' => [
                    'public' => 0664,
                    'private' => 0664,
                ],
                'dir' => [
                    'public' => 0775,
                    'private' => 0775,
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Public
        |--------------------------------------------------------------------------
        */
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
