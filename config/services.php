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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | SSO BPS (Keycloak accounts.bps.go.id, OIDC Authorization Code Flow) — sama dengan sandaran-hati.
    | redirect_uri kosong = route('sso.callback'). Keycloak menolak localhost, jadi lokal mengisi
    | SSO_REDIRECT_URI dengan callback production + SSO_STATE_PREFIX=local; callback production
    | meneruskan ke forward_targets[prefix]. Production: SSO_STATE_PREFIX kosong.
    */
    'sso' => [
        'keycloak_url' => env('SSO_KEYCLOAK_URL', 'https://accounts.bps.go.id'),
        'realm' => env('SSO_REALM', 'pegawai'),
        'client_id' => env('SSO_CLIENT_ID'),
        'client_secret' => env('SSO_CLIENT_SECRET'),
        'redirect_uri' => env('SSO_REDIRECT_URI'),
        // Tambah scope atribut pegawai (di SSO lama: "profile-pegawai") bila sudah ditambahkan ke client
        'scope' => env('SSO_SCOPE', 'openid profile email'),
        'state_prefix' => env('SSO_STATE_PREFIX'),
        'forward_targets' => [
            'local' => 'http://localhost/pubchecker',
        ],
    ],

    'bps_api' => [
        'key'    => env('BPS_API_KEY', ''),
        'domain' => env('BPS_DOMAIN', '0000'),
    ],

    // File perbaikan yang diunggah di SIPOTRET (pasca_rilis_history.pub_file_rev berisi path relatif,
    // mis. "upload/fallback/pasca_rilis/REV_x.pdf"). Dicari di folder akar SIPOTRET bila satu server
    // (bawaan: folder "sipotret" di sebelah pubchecker), lalu lewat URL akarnya.
    'sipotret' => [
        'file_path' => env('SIPOTRET_FILE_PATH', base_path('../sipotret')),
        'file_url'  => env('SIPOTRET_FILE_URL', 'https://kalsel.web.bps.go.id/sipotret'),
    ],

];
