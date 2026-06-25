<?php

namespace App\Models;

use App\Jobs\SyncDocumentToSharePoint;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    /** External-sync lifecycle (local-first → background provider sync). */
    public const SYNC_PENDING = 'pending';
    public const SYNC_SYNCING = 'syncing';
    public const SYNC_SYNCED  = 'synced';
    public const SYNC_FAILED  = 'failed';

    protected $guarded = [];

    protected $casts = [
        'synced_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    /**
     * The platform's local-first document persistence (shared by every module:
     * transport / driver / maintenance). Store the file locally so it is
     * immediately viewable, create the Document with a `pending` sync state, and
     * queue the background SharePoint migration. The SharePoint folder is derived
     * from the local folder by the job — pass the module's folder here.
     *
     * Reuses SyncDocumentToSharePoint + SharePointStorageService — no upload logic
     * is duplicated.
     *
     * @param  array<string,mixed>  $attributes  module-specific columns (FK + type)
     */
    public static function storeLocalAndQueueSync(UploadedFile $file, array $attributes, string $folder): self
    {
        $path = $file->store($folder, 'public');

        $document = static::create(array_merge($attributes, [
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'sync_status' => self::SYNC_PENDING,
        ]));

        SyncDocumentToSharePoint::dispatch($document->id);

        return $document;
    }

    public function transportTracking(): BelongsTo
    {
        return $this->belongsTo(TransportTracking::class);
    }

    public function inspectionChecklistIssue(): BelongsTo
    {
        return $this->belongsTo(InspectionChecklistIssue::class);
    }

    public function dailyChecklistIssue(): BelongsTo
    {
        return $this->belongsTo(DailyChecklistIssue::class);
    }
}
