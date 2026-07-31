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
    | the browser cache after a deploy. Se ASSETS_VERSION non è settato
    | nell'env, il valore è calcolato dal filemtime più recente dei principali
    | asset frontoffice: così ogni modifica ai file bumpa automaticamente
    | il ?v=... senza intervento manuale.
    |
    | In produzione, dopo un git pull ricordarsi comunque di rigenerare
    | la cache di config con `php artisan config:cache` (o non cacharla).
    |
    */

    'assets_version' => env('ASSETS_VERSION') ?: (function () {
        $files = [
            public_path('app/js/table-orders.js'),
            public_path('app/js/operator-auth.js'),
            public_path('app/js/app.js'),
            public_path('app/css/app.css'),
            public_path('app/css/mobile.css'),
        ];
        $latest = 0;
        foreach ($files as $f) {
            if (is_file($f)) {
                $latest = max($latest, (int) filemtime($f));
            }
        }
        return $latest > 0 ? (string) $latest : '1';
    })(),

];
