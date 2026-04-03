<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SharePointChecklistService
{
    public function isConfigured(): bool
    {
        $config = config('services.sharepoint');

        return ! empty($config['tenant_id'])
            && ! empty($config['client_id'])
            && ! empty($config['client_secret'])
            && ! empty($config['site_id'])
            && ! empty($config['list_id']);
    }

    public function syncChecklist(array $payload): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'SharePoint is not configured.',
            ];
        }

        $tokenResponse = Http::asForm()->post(
            'https://login.microsoftonline.com/' . config('services.sharepoint.tenant_id') . '/oauth2/v2.0/token',
            [
                'client_id' => config('services.sharepoint.client_id'),
                'client_secret' => config('services.sharepoint.client_secret'),
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]
        );

        if (! $tokenResponse->successful()) {
            Log::error('SharePoint token request failed', [
                'status' => $tokenResponse->status(),
                'body' => $tokenResponse->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Unable to get SharePoint token.',
            ];
        }

        $accessToken = $tokenResponse->json('access_token');
        if (empty($accessToken)) {
            return [
                'success' => false,
                'message' => 'SharePoint access token is missing.',
            ];
        }

        $createItemUrl = sprintf(
            'https://graph.microsoft.com/v1.0/sites/%s/lists/%s/items',
            config('services.sharepoint.site_id'),
            config('services.sharepoint.list_id')
        );

        $response = Http::withToken($accessToken)->post($createItemUrl, [
            'fields' => $payload,
        ]);

        if (! $response->successful()) {
            Log::error('SharePoint checklist sync failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'message' => 'SharePoint checklist sync failed.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Checklist synced to SharePoint.',
        ];
    }
}
