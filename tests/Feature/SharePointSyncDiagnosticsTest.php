<?php

namespace Tests\Feature;

use App\Jobs\SyncDocumentToSharePoint;
use App\Models\Document;
use App\Services\SharePointStorageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verifies the SharePoint token diagnostics + retry classification without ever
 * touching real Azure credentials — the Azure OAuth response is faked.
 */
class SharePointSyncDiagnosticsTest extends TestCase
{
    use DatabaseTransactions;

    private function configureSharePoint(): void
    {
        // Make isConfigured() pass with throwaway values (the HTTP call is faked).
        config(['services.sharepoint' => [
            'tenant_id' => '12345678-aaaa-bbbb-cccc-1234567890ab',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-secret',
            'site_id' => 'test-site-id',
            'list_id' => null,
        ]]);
    }

    private function makeLocalDocument(): Document
    {
        Storage::fake('public');
        Storage::disk('public')->put('transport_trackings/test.pdf', 'PDF');

        return Document::create([
            'type' => 'other',
            'file_path' => 'transport_trackings/test.pdf',
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 3,
            'sync_status' => Document::SYNC_PENDING,
        ]);
    }

    public function test_permanent_oauth_error_is_captured_and_does_not_retry(): void
    {
        $this->configureSharePoint();
        $doc = $this->makeLocalDocument();

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'error' => 'invalid_client',
                'error_description' => "AADSTS7000215: Invalid client secret provided.\r\nTrace ID: abc",
                'error_codes' => [7000215],
                'trace_id' => 'abc',
                'correlation_id' => 'def',
            ], 401),
        ]);

        // Permanent failure → handle() must NOT throw (it fails fast instead of retrying).
        (new SyncDocumentToSharePoint($doc->id))->handle(app(SharePointStorageService::class));

        $doc->refresh();
        $this->assertSame(Document::SYNC_FAILED, $doc->sync_status);
        // The real Azure reason is stored on the document — not the generic message.
        $this->assertStringContainsString('AADSTS7000215', (string) $doc->last_sync_error);
        $this->assertStringNotContainsString('Could not get SharePoint token', (string) $doc->last_sync_error);
    }

    public function test_transient_error_is_retryable(): void
    {
        $this->configureSharePoint();
        $doc = $this->makeLocalDocument();

        // 503 from the token endpoint = transient → the job throws so the queue retries.
        Http::fake([
            'login.microsoftonline.com/*' => Http::response('', 503),
        ]);

        $this->expectException(\RuntimeException::class);
        (new SyncDocumentToSharePoint($doc->id))->handle(app(SharePointStorageService::class));
    }
}
