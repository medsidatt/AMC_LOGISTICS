<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SharePointStorageService
{
    private ?string $accessToken = null;

    public function isConfigured(): bool
    {
        $config = config('services.sharepoint');
        return !empty($config['tenant_id'])
            && !empty($config['client_id'])
            && !empty($config['client_secret'])
            && !empty($config['site_id']);
    }

    /**
     * Upload a file to SharePoint Drive.
     * Returns ['success' => bool, 'path' => string, 'url' => string, 'sharepoint_id' => string]
     */
    public function upload(UploadedFile $file, string $folder = 'transport_trackings'): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'SharePoint not configured.'];
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Could not get SharePoint token.'];
        }

        $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
        $remotePath = $folder . '/' . $filename;

        $siteId = config('services.sharepoint.site_id');

        // Upload file content using PUT to the drive
        $response = Http::withToken($token)
            ->withBody($file->getContent(), $file->getMimeType())
            ->put("https://graph.microsoft.com/v1.0/sites/{$siteId}/drive/root:/{$remotePath}:/content");

        if (!$response->successful()) {
            Log::error('SharePoint upload failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'path' => $remotePath,
            ]);
            return ['success' => false, 'message' => 'Upload to SharePoint failed: ' . $response->status()];
        }

        $data = $response->json();
        $itemId = data_get($data, 'id');
        $webUrl = data_get($data, 'webUrl');

        // Create a sharing link for anonymous viewing
        $shareUrl = $this->createShareLink($siteId, $itemId);

        return [
            'success' => true,
            'path' => 'sharepoint://' . $remotePath,
            'url' => $shareUrl ?? $webUrl,
            'sharepoint_id' => $itemId,
            'web_url' => $webUrl,
        ];
    }

    /**
     * Delete a file from SharePoint Drive.
     */
    public function delete(string $path): bool
    {
        if (!$this->isConfigured() || !Str::startsWith($path, 'sharepoint://')) {
            return false;
        }

        $token = $this->getAccessToken();
        if (!$token) return false;

        $remotePath = Str::after($path, 'sharepoint://');
        $siteId = config('services.sharepoint.site_id');

        $response = Http::withToken($token)
            ->delete("https://graph.microsoft.com/v1.0/sites/{$siteId}/drive/root:/{$remotePath}");

        return $response->successful();
    }

    /**
     * Get a download/view URL for a SharePoint file.
     */
    public function getUrl(string $path): ?string
    {
        if (!Str::startsWith($path, 'sharepoint://')) {
            return null;
        }

        $token = $this->getAccessToken();
        if (!$token) return null;

        $remotePath = Str::after($path, 'sharepoint://');
        $siteId = config('services.sharepoint.site_id');

        $response = Http::withToken($token)
            ->get("https://graph.microsoft.com/v1.0/sites/{$siteId}/drive/root:/{$remotePath}");

        if (!$response->successful()) return null;

        $itemId = data_get($response->json(), 'id');
        return $this->createShareLink($siteId, $itemId);
    }

    /**
     * Create an anonymous view link for a file.
     */
    private function createShareLink(string $siteId, string $itemId): ?string
    {
        $token = $this->getAccessToken();
        if (!$token || !$itemId) return null;

        $response = Http::withToken($token)
            ->post("https://graph.microsoft.com/v1.0/sites/{$siteId}/drive/items/{$itemId}/createLink", [
                'type' => 'view',
                'scope' => 'organization',
            ]);

        if ($response->successful()) {
            return data_get($response->json(), 'link.webUrl');
        }

        return null;
    }

    private function getAccessToken(): ?string
    {
        if ($this->accessToken) return $this->accessToken;

        $response = Http::asForm()->post(
            'https://login.microsoftonline.com/' . config('services.sharepoint.tenant_id') . '/oauth2/v2.0/token',
            [
                'client_id' => config('services.sharepoint.client_id'),
                'client_secret' => config('services.sharepoint.client_secret'),
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]
        );

        if ($response->successful()) {
            $this->accessToken = $response->json('access_token');
            return $this->accessToken;
        }

        Log::error('SharePoint token failed', ['status' => $response->status()]);
        return null;
    }
}
