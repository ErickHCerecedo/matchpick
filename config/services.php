<?php

return [
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'apifootball' => [
        'key'      => env('APIFOOTBALL_KEY', ''),
        'base_url' => 'https://v3.football.api-sports.io',
    ],

    'footballdata' => [
        'token'    => env('FOOTBALLDATA_TOKEN', 'ad9402607a8e4681aac936e56dcb4c93'),
        'base_url' => 'https://api.football-data.org/v4',
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME', 'dr0klvutj'),
        'api_key'    => env('CLOUDINARY_API_KEY', '683293141245113'),
        'api_secret' => env('CLOUDINARY_API_SECRET', 'hGpHxDIaLfo6Hv4QWk3yItpWS1o'),
    ],
];
