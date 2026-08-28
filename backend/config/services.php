<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],

    /*
    | Generic HTTP SMS aggregator (Nexah, SMSVas, Orange SMS API…).
    | Field names are configurable so switching provider needs no code change.
    */
    'sms_http' => [
        'name' => env('SMS_HTTP_NAME', 'http'),
        'endpoint' => env('SMS_HTTP_ENDPOINT'),
        'token' => env('SMS_HTTP_TOKEN'),
        'method' => env('SMS_HTTP_METHOD', 'post'),
        'to_field' => env('SMS_HTTP_TO_FIELD', 'to'),
        'message_field' => env('SMS_HTTP_MESSAGE_FIELD', 'message'),
        'from_field' => env('SMS_HTTP_FROM_FIELD', 'sender'),
        'id_field' => env('SMS_HTTP_ID_FIELD', 'id'),
        'params' => [],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
