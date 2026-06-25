<?php

namespace Tests\Feature;

use App\Jobs\SyncDocumentToSharePoint;
use App\Models\Auth\User;
use App\Models\Document;
use App\Models\Driver;
use App\Models\Truck;
use App\Services\SharePointStorageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Local-first → background SharePoint sync. The local copy is the source of
 * truth until SharePoint confirms; sync_status exposes the lifecycle.
 * DatabaseTransactions keeps the dev DB clean; Storage::fake isolates files.
 */
class SharePointDocumentSyncTest extends TestCase
{
    use DatabaseTransactions;

    private function pendingDocumentWithLocalFile(string $relative = 'transport_trackings/test.pdf'): Document
    {
        Storage::fake('public');
        Storage::disk('public')->put($relative, 'PDF-BYTES');

        return Document::create([
            'file_path' => $relative,
            'original_name' => 'bon.pdf',
            'mime_type' => 'application/pdf',
            'size' => 9,
            'type' => 'other',
            'sync_status' => Document::SYNC_PENDING,
        ]);
    }

    public function test_no_provider_configured_marks_local_as_synced(): void
    {
        config(['services.sharepoint' => []]); // not configured → local IS final
        $doc = $this->pendingDocumentWithLocalFile();

        (new SyncDocumentToSharePoint($doc->id))->handle(app(SharePointStorageService::class));

        $doc->refresh();
        $this->assertSame(Document::SYNC_SYNCED, $doc->sync_status);
        $this->assertNotNull($doc->synced_at);
        Storage::disk('public')->assertExists($doc->file_path); // local kept (it's the destination)
    }

    public function test_successful_sync_rewrites_path_and_removes_local_copy(): void
    {
        $doc = $this->pendingDocumentWithLocalFile();

        $sp = Mockery::mock(SharePointStorageService::class);
        $sp->shouldReceive('isConfigured')->andReturnTrue();
        $sp->shouldReceive('uploadContent')->once()->andReturn([
            'success' => true,
            'path' => 'sharepoint://transport_trackings/remote.pdf',
            'url' => 'https://share/remote',
            'sharepoint_id' => 'SP-123',
        ]);

        (new SyncDocumentToSharePoint($doc->id))->handle($sp);

        $doc->refresh();
        $this->assertSame(Document::SYNC_SYNCED, $doc->sync_status);
        $this->assertSame('sharepoint://transport_trackings/remote.pdf', $doc->file_path);
        $this->assertSame('SP-123', $doc->sharepoint_id);
        $this->assertNotNull($doc->synced_at);
        Storage::disk('public')->assertMissing('transport_trackings/test.pdf'); // local reclaimed
    }

    public function test_failed_sync_keeps_local_and_records_error(): void
    {
        $doc = $this->pendingDocumentWithLocalFile();

        $sp = Mockery::mock(SharePointStorageService::class);
        $sp->shouldReceive('isConfigured')->andReturnTrue();
        $sp->shouldReceive('uploadContent')->andReturn(['success' => false, 'message' => 'Graph 503']);

        try {
            (new SyncDocumentToSharePoint($doc->id))->handle($sp);
            $this->fail('expected the job to throw so the queue retries');
        } catch (\Throwable $e) {
            // expected — the queue would retry with backoff
        }

        $doc->refresh();
        $this->assertSame(Document::SYNC_FAILED, $doc->sync_status);
        $this->assertStringContainsString('Graph 503', (string) $doc->last_sync_error);
        $this->assertSame(1, (int) $doc->retry_count);
        Storage::disk('public')->assertExists('transport_trackings/test.pdf'); // never lose local data
    }

    public function test_already_synced_document_is_skipped(): void
    {
        $doc = $this->pendingDocumentWithLocalFile();
        $doc->update(['sync_status' => Document::SYNC_SYNCED]);

        $sp = Mockery::mock(SharePointStorageService::class);
        $sp->shouldReceive('isConfigured')->never();
        $sp->shouldReceive('uploadContent')->never(); // idempotent — no re-upload

        (new SyncDocumentToSharePoint($doc->id))->handle($sp);

        $this->assertSame(Document::SYNC_SYNCED, $doc->refresh()->sync_status);
    }

    public function test_storing_a_tracking_with_a_file_persists_locally_and_queues_sync(): void
    {
        Storage::fake('public');
        Queue::fake();
        $user = User::query()->permission('transport-tracking-create')->firstOrFail();
        $truck = Truck::where('is_active', true)->firstOrFail();
        $driver = Driver::firstOrFail();

        $this->actingAs($user)->post('/transport_tracking/store', [
            'truck_id' => (string) $truck->id,
            'driver_id' => (string) $driver->id,
            'product' => '0/3',
            'base' => 'mr',
            'files' => [UploadedFile::fake()->create('client_bon.pdf', 50, 'application/pdf')],
        ])->assertRedirect();

        $doc = Document::latest('id')->first();
        $this->assertNotNull($doc);
        $this->assertSame(Document::SYNC_PENDING, $doc->sync_status);          // local-first
        $this->assertFalse(str_starts_with((string) $doc->file_path, 'sharepoint://'));
        Storage::disk('public')->assertExists($doc->file_path);                // viewable immediately
        Queue::assertPushed(SyncDocumentToSharePoint::class, fn ($job) => $job->documentId === $doc->id);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
