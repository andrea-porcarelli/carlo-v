<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    */

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        realpath(storage_path('framework/views'))
    ),

    /*
    |--------------------------------------------------------------------------
    | Frontoffice Assets Version
    |--------------------------------------------------------------------------
    |
    | Query string appended to /app/css and /app/js asset URLs to invalidate
    | the browser cache after a deploy. Bump this value (or set the
    | ASSETS_VERSION env var) whenever a static frontoffice asset changes.
    |
    */

    'assets_version' => env('ASSETS_VERSION', '2026071302'),

];
