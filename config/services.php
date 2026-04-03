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
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
        'tenant' => env('MICROSOFT_TENANT_ID'),
    ],

    'microsoft' => [
        'client_id' => env('AZURE_CLIENT_ID'),
        'client_secret' => env('AZURE_CLIENT_SECRET'),
        'redirect' => env('AZURE_REDIRECT_URI'),
        'tenant' => env('AZURE_TENANT_ID'),
        'url_authorize' => 'https://login.microsoftonline.com/' . env('AZURE_TENANT_ID') . '/oauth2/v2.0/authorize',
        'url_access_token' => 'https://login.microsoftonline.com/' . env('AZURE_TENANT_ID') . '/oauth2/v2.0/token',
        'url_resource_owner_details' => 'https://graph.microsoft.com/v1.0/me',
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

];
