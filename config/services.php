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

    'nutrition' => [
        'endpoint' => env('NUTRITION_API_URL', 'https://world.openfoodfacts.org/api/v2/product'),
        'search_endpoint' => env('NUTRITION_SEARCH_API_URL', 'https://world.openfoodfacts.org/api/v2/search'),
        'key' => env('NUTRITION_API_KEY'),
        'key_header' => env('NUTRITION_API_KEY_HEADER', 'Authorization'),
        'language' => env('NUTRITION_API_LANGUAGE', 'en'),
        'country' => env('NUTRITION_API_COUNTRY', 'us'),
    ],

];
