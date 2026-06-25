<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\SharePointStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Migrates a locally-stored Document to SharePoint in the background.
 *
 * The platform integration pattern: persist locally first → mark sync_status →
 * queue the external sync → retry automatically → never lose local data when the
 * provider is unavailable → expose the sync state for operations. The Document's
 * local copy is the source of truth until SharePoint confirms; only then is it
 * rewritten to the sharepoint:// path and the local copy removed.
 *
 * Idempotent: a Document already synced (or already on a sharepoint:// path) is
 * skipped, so retries / double-dispatch never produce a duplicate remote file.
 */
class SyncDocumentToSharePoint implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300]; // Graph throttling wants a longer cool-off
    public int $timeout = 60;               // < retry_after (90); files are capped at 4 MB

    /** Local document disk (matches $file->store(..., 'public')). */
    private const DISK = 'public';

    public function __construct(public int $documentId) {}

    public function handle(SharePointStorageService $sharepoint): void
    {
        $document = Document::find($this->documentId);
        if (! $document) {
            return;
        }

        // Idempotency — already done, or already a remote path (legacy/retry).
        if ($document->sync_status === Document::SYNC_SYNCED) {
            return;
        }
        if (str_starts_with((string) $document->file_path, 'sharepoint://')) {
            $document->update(['sync_status' => Document::SYNC_SYNCED, 'synced_at' => now()]);
            return;
        }

        // No provider configured → local storage IS the final destination.
        if (! $sharepoint->isConfigured()) {
            $document->update(['sync_status' => Document::SYNC_SYNCED, 'synced_at' => now()]);
            return;
        }

        $relativePath = (string) $document->file_path;
        if (! Storage::disk(self::DISK)->exists($relativePath)) {
            // Non-retryable: the local source is gone. Record it and stop.
            $this->markFailed($document, 'Local file missing: ' . $relativePath);
            return;
        }

        $document->update(['sync_status' => Document::SYNC_SYNCING]);

        try {
            $result = $sharepoint->uploadContent(
                Storage::disk(self::DISK)->get($relativePath),
                (string) pathinfo($relativePath, PATHINFO_EXTENSION),
                (string) ($document->mime_type ?? ''),
                'transport_trackings',
            );
        } catch (Throwable $e) {
            $this->markFailed($document, $e->getMessage());
            throw $e; // let the queue retry with backoff
        }

        if (! ($result['success'] ?? false)) {
            $message = $result['message'] ?? 'Unknown SharePoint error';
            $this->markFailed($document, $message);
            throw new \RuntimeException('SharePoint sync failed for document ' . $document->id . ': ' . $message);
        }

        $document->update([
            'file_path' => $result['path'],
            'sharepoint_id' => $result['sharepoint_id'] ?? null,
            'sharepoint_url' => $result['url'] ?? null,
            'sync_status' => Document::SYNC_SYNCED,
            'synced_at' => now(),
            'last_sync_error' => null,
        ]);

        // Local copy migrated — reclaim the space.
        Storage::disk(self::DISK)->delete($relativePath);

        Log::info('Document synced to SharePoint', [
            'document_id' => $document->id,
            'sharepoint_id' => $result['sharepoint_id'] ?? null,
        ]);
    }

    public function failed(Throwable $e): void
    {
        $document = Document::find($this->documentId);
        if ($document && $document->sync_status !== Document::SYNC_SYNCED) {
            $document->update(['sync_status' => Document::SYNC_FAILED]);
        }
    }

    private function markFailed(Document $document, string $message): void
    {
        $document->update([
            'sync_status' => Document::SYNC_FAILED,
            'last_sync_error' => $message,
            'retry_count' => (int) $document->retry_count + 1,
        ]);

        Log::warning('Document SharePoint sync failed', [
            'document_id' => $document->id,
            'attempt' => $this->attempts(),
            'retry_count' => (int) $document->retry_count,
            'error' => $message,
        ]);
    }
}
