<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'mysond' => [
        'codice_azienda' => env('MYSOND_AZIENDA'),
        'username' => env('MYSOND_USER'),
        'password' => env('MYSOND_PASS'),
        // Prima di emettere una fattura, allinea il contatore locale al massimo
        // progressivo già presente su MySond per l'anno corrente. Fail-soft:
        // qualsiasi errore (MySond down, parsing fallito) lascia il contatore
        // locale invariato e l'emissione prosegue.
        'sync_counter_on_issue' => env('MYSOND_SYNC_COUNTER_ON_ISSUE', true),
        // Quando true, prima di emettere una fattura controlla se ci sono
        // scartate SDI non ancora riconosciute sull'Azienda MySond (anche
        // provenienti da altri progetti che condividono le stesse credenziali)
        // e blocca l'emissione finché l'admin non le acka dal backoffice.
        'block_on_unack_rejections' => env('MYSOND_BLOCK_ON_UNACK_REJECTIONS', true),
    ],

];
