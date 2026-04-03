<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SharePointDailyChecklistService
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

    public function syncDailyChecklist(array $payload): array
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
            Log::error('SharePoint daily token request failed', [
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
            return [
                'success' => false,
                'message' => 'SharePoint daily checklist sync failed.',
            ];
        }


        // Graph usually returns {"id": "...", "fields": {...}}
        $itemId = $response->json('id');

        return [
            'success' => true,
            'message' => 'Daily checklist synced to SharePoint.',
            'sharepoint_item_id' => ! empty($itemId) ? (string) $itemId : null,
        ];
    }

    /**
     * Placeholder for the next step: manager resolves issues and we update the same SharePoint item.
     * Implemented fully when the Logistics Manager UI is added.
     */
    public function updateIssueResolution(string $sharepointItemId, array $payload): array
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
            Log::error('SharePoint daily token request failed (resolution update)', [
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

        $updateItemUrl = sprintf(
            'https://graph.microsoft.com/v1.0/sites/%s/lists/%s/items/%s/fields',
            config('services.sharepoint.site_id'),
            config('services.sharepoint.list_id'),
            $sharepointItemId
        );

        $response = Http::withToken($accessToken)->patch($updateItemUrl, [
            'fields' => $payload,
        ]);

        if (! $response->successful()) {
            Log::error('SharePoint daily resolution update failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'sharepointItemId' => $sharepointItemId,
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'message' => 'SharePoint resolution update failed.',
            ];
        }

        return [
            'success' => true,
            'message' => 'SharePoint resolution updated.',
        ];
    }
}

