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

    'azure' => [
        'client_id' => env('SHAREPOINT_CLIENT_ID'),
        'client_secret' => env('SHAREPOINT_CLIENT_SECRET'),
        // OAuth tenant is separate from SharePoint so we can broaden the
        // sign-in audience (e.g. `organizations`, `common`) without
        // touching the tenant SharePoint file access is bound to.
        'tenant' => env('MICROSOFT_OAUTH_TENANT', env('SHAREPOINT_TENANT_ID')),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
    ],

    'fleeti' => [
        'base_url' => env('FLEETI_BASE_URL', 'https://api.fleeti.co'),
        'api_key' => env('FLEETI_API_KEY'),
        'bearer_token' => env('FLEETI_BEARER_TOKEN'),
    ],

    'sharepoint' => [
        'tenant_id' => env('SHAREPOINT_TENANT_ID'),
        'client_id' => env('SHAREPOINT_CLIENT_ID'),
        'client_secret' => env('SHAREPOINT_CLIENT_SECRET'),
        'site_id' => env('SHAREPOINT_SITE_ID'),
        'list_id' => env('SHAREPOINT_LIST_ID'),
    ],

    'whatsapp' => [
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'template_dispatch' => env('WHATSAPP_TEMPLATE_DISPATCH', 'amc_dispatch_v1'),
        'template_lang' => env('WHATSAPP_TEMPLATE_LANG', 'fr'),
        'default_country' => env('WHATSAPP_DEFAULT_COUNTRY', 'MR'),
    ],

];
