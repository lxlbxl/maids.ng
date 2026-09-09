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
        'client_id' => env('QOREID_CLIENT_ID'),
        'client_secret' => env('QOREID_CLIENT_SECRET'),
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
        'matching' => env('MATCHING_FEE_AMOUNT', 20000),
        'nin_verification' => env('NIN_VERIFICATION_FEE', 5000),
    ],

    'defaults' => [
        'payment_gateway' => env('DEFAULT_PAYMENT_GATEWAY', 'paystack'),
        'min_salary' => env('MINIMUM_SALARY', 15000),
        'max_salary' => env('MAXIMUM_SALARY', 200000),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business API (Meta)
    |--------------------------------------------------------------------------
    */
    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        // Public wa.me number for site CTAs (digits only, intl format, no +)
        'number' => env('WHATSAPP_NUMBER', '2348173070000'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Facebook Messenger & Instagram Graph API
    |--------------------------------------------------------------------------
    */
    'facebook' => [
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'page_access_token' => env('FACEBOOK_PAGE_ACCESS_TOKEN'),
        'webhook_verify_token' => env('FACEBOOK_WEBHOOK_VERIFY_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Zernio Social Media Bridge
    |--------------------------------------------------------------------------
    */
    'zernio' => [
        'bridge_key' => env('ZERNIO_BRIDGE_KEY'),
        'api_key'    => env('ZERNIO_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Instagram Graph API
    |--------------------------------------------------------------------------
    */
    'instagram' => [
        'app_id' => env('INSTAGRAM_APP_ID'),
        'app_secret' => env('INSTAGRAM_APP_SECRET'),
        'ig_access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
        'ig_business_id' => env('INSTAGRAM_BUSINESS_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta Pixel + Conversions API (server-side)
    |--------------------------------------------------------------------------
    | pixel_id = "Maids.ng Pixel" (Maids.ng Business Manager). capi_token is a
    | System User token with events access. capi_test_code (optional) routes
    | events to Events Manager > Test Events instead of production.
    */
    'meta' => [
        'pixel_id'       => env('META_PIXEL_ID', '1533038361829535'),
        'capi_token'     => env('META_CAPI_TOKEN'),
        'capi_test_code' => env('META_CAPI_TEST_CODE'),
        'graph_version'  => env('META_GRAPH_VERSION', 'v21.0'),
        // Click-to-WhatsApp attribution: business_messaging events must carry
        // the WhatsApp page / WABA that owns the number Peace answers on.
        'page_id'        => env('META_PAGE_ID', '776632412198520'),
        'waba_id'        => env('META_WABA_ID', '2164452014330344'),
        'ctwa_secret'    => env('META_CTWA_SECRET'),
    ],

    'posthog' => [
        'key'  => env('POSTHOG_KEY'),
        'host' => env('POSTHOG_HOST', 'https://us.posthog.com'),
        'dashboard_embed_url' => env('POSTHOG_DASHBOARD_EMBED_URL'),
    ],

    'attribution' => [
        'bridge_secret' => env('ATTRIBUTION_BRIDGE_SECRET'),
    ],

];
