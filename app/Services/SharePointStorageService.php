<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SharePointStorageService
{
    private ?string $accessToken = null;

    /** Last token-acquisition failure reason (real Azure error) + whether it is permanent. */
    private ?string $tokenError = null;
    private bool $tokenErrorPermanent = false;

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
        return $this->uploadContent(
            $file->getContent(),
            (string) $file->getClientOriginalExtension(),
            (string) $file->getMimeType(),
            $folder,
        );
    }

    /**
     * Upload raw content to SharePoint Drive. Provider seam reused by both the
     * direct UploadedFile path (above) and the background SyncDocumentToSharePoint
     * job (which uploads a previously stored local file). No logic is duplicated.
     * Returns ['success' => bool, 'path' => string, 'url' => string, 'sharepoint_id' => string].
     */
    public function uploadContent(string $content, string $extension, string $mime, string $folder = 'transport_trackings'): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'SharePoint not configured.'];
        }

        $token = $this->getAccessToken();
        if (!$token) {
            // Propagate the real Azure reason (AADSTS…) and whether it is permanent,
            // so the job can classify retry behaviour and store an actionable error.
            return [
                'success' => false,
                'message' => $this->tokenError ?? 'Could not get SharePoint token.',
                'permanent' => $this->tokenErrorPermanent,
            ];
        }

        $filename = Str::random(40) . ($extension !== '' ? '.' . $extension : '');
        $remotePath = $folder . '/' . $filename;

        $siteId = config('services.sharepoint.site_id');

        // Upload file content using PUT to the drive
        $response = Http::withToken($token)
            ->withBody($content, $mime !== '' ? $mime : 'application/octet-stream')
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

    /** OAuth errors that mean the request will never succeed without a config change. */
    private const PERMANENT_OAUTH_ERRORS = ['invalid_client', 'unauthorized_client', 'invalid_scope', 'invalid_grant', 'invalid_request'];

    private function getAccessToken(): ?string
    {
        if ($this->accessToken) return $this->accessToken;

        $tenantId = (string) config('services.sharepoint.tenant_id');
        $endpoint = 'https://login.microsoftonline.com/' . $tenantId . '/oauth2/v2.0/token';

        try {
            $response = Http::asForm()->timeout(15)->post($endpoint, [
                'client_id' => config('services.sharepoint.client_id'),
                'client_secret' => config('services.sharepoint.client_secret'),
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]);
        } catch (\Throwable $e) {
            // Transport error (DNS / TLS / timeout) — transient, safe to retry.
            $this->tokenErrorPermanent = false;
            $this->tokenError = 'SharePoint token request failed (network/timeout): ' . $e->getMessage();
            Log::error('SharePoint token transport error', [
                'endpoint' => $endpoint,
                'tenant' => $this->maskTenant($tenantId),
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if ($response->successful()) {
            $this->accessToken = $response->json('access_token');
            $this->tokenError = null;
            $this->tokenErrorPermanent = false;
            return $this->accessToken;
        }

        // Azure rejected the token request. Capture the real OAuth error WITHOUT
        // ever exposing the client_secret / tokens.
        $body = $response->json() ?? [];
        $error = data_get($body, 'error');                    // e.g. invalid_client
        $description = data_get($body, 'error_description');   // contains the AADSTS code + text
        $errorCodes = data_get($body, 'error_codes');          // e.g. [7000215]
        $traceId = data_get($body, 'trace_id');
        $correlationId = data_get($body, 'correlation_id');
        $aadsts = $this->extractAadsts($description);          // e.g. AADSTS7000215
        $status = $response->status();

        // Permanent = a 4xx (except 429) with a known credential/config OAuth error.
        $this->tokenErrorPermanent = in_array($error, self::PERMANENT_OAUTH_ERRORS, true)
            || ($status >= 400 && $status < 500 && $status !== 429);

        $this->tokenError = trim('SharePoint token request failed. '
            . ($aadsts ? $aadsts . ' ' : '')
            . ($this->firstLine($description) ?: ($error ?: 'HTTP ' . $status)));

        Log::error('SharePoint token request rejected', [
            'http_status' => $status,
            'error' => $error,
            'error_description' => $description,
            'error_codes' => $errorCodes,
            'aadsts' => $aadsts,
            'trace_id' => $traceId,
            'correlation_id' => $correlationId,
            'endpoint' => $endpoint,
            'tenant' => $this->maskTenant($tenantId),
            'permanent' => $this->tokenErrorPermanent,
        ]);

        return null;
    }

    /** Pull the AADSTS code out of an OAuth error_description, if present. */
    private function extractAadsts(?string $description): ?string
    {
        return $description && preg_match('/AADSTS\d+/', $description, $m) ? $m[0] : null;
    }

    /** First line only — keeps stored errors concise (full text is in the log). */
    private function firstLine(?string $description): ?string
    {
        if (!$description) return null;
        $line = strtok($description, "\r\n");
        return $line === false ? null : trim($line);
    }

    /** Tenant GUID is not a secret, but mask it in logs to be safe. */
    private function maskTenant(string $tenant): string
    {
        return strlen($tenant) <= 8 ? $tenant : substr($tenant, 0, 8) . '…' . substr($tenant, -4);
    }
}
