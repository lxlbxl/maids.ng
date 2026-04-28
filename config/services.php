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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Paystack Payment Gateway
    |--------------------------------------------------------------------------
    */
    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Flutterwave Payment Gateway
    |--------------------------------------------------------------------------
    */
    'flutterwave' => [
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
        'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | QoreID Identity Verification
    |--------------------------------------------------------------------------
    */
    'qoreid' => [
        'token' => env('QOREID_TOKEN'),
        'base_url' => env('QOREID_BASE_URL', 'https://api.qoreid.com/v1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Termii SMS Gateway
    |--------------------------------------------------------------------------
    */
    'termii' => [
        'api_key' => env('TERMII_API_KEY'),
        'sender_id' => env('TERMII_SENDER_ID', 'MaidsNG'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Settings
    |--------------------------------------------------------------------------
    */
    'commission' => [
        'type' => env('COMMISSION_TYPE', 'percentage'),
        'percent' => env('COMMISSION_PERCENT', 10),
        'fixed_amount' => env('COMMISSION_FIXED_AMOUNT', 5000),
    ],

    'fees' => [
        'matching' => env('MATCHING_FEE_AMOUNT', 5000),
        'nin_verification' => env('NIN_VERIFICATION_FEE', 5000),
    ],

    'defaults' => [
        'payment_gateway' => env('DEFAULT_PAYMENT_GATEWAY', 'paystack'),
        'min_salary' => env('MINIMUM_SALARY', 15000),
        'max_salary' => env('MAXIMUM_SALARY', 200000),
    ],

];
