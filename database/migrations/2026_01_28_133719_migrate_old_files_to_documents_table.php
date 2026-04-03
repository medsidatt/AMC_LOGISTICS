<?php

use App\Models\Document;
use App\Models\TransportTracking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all transport trackings that have files
        $trackings = TransportTracking::where(function($query) {
                $query->whereNotNull('provider_file')
                      ->orWhereNotNull('client_file');
            })
            ->get();

        $migrated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($trackings as $tracking) {
            // Migrate provider_file
            if (!empty($tracking->provider_file)) {
                $result = $this->migrateFile(
                    $tracking->id,
                    $tracking->provider_file,
                    'provider'
                );
                
                if ($result === 'migrated') {
                    $migrated++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                } else {
                    $errors++;
                }
            }

            // Migrate client_file
            if (!empty($tracking->client_file)) {
                $result = $this->migrateFile(
                    $tracking->id,
                    $tracking->client_file,
                    'client'
                );
                
                if ($result === 'migrated') {
                    $migrated++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                } else {
                    $errors++;
                }
            }
        }

        // Output results
        echo "\nMigration completed:\n";
        echo "  - Migrated: {$migrated} files\n";
        echo "  - Skipped (already exists): {$skipped} files\n";
        echo "  - Errors: {$errors} files\n";
    }

    /**
     * Migrate a single file to the documents table
     *
     * @param int $trackingId
     * @param string $filePath
     * @param string $type
     * @return string 'migrated', 'skipped', or 'error'
     */
    private function migrateFile(int $trackingId, string $filePath, string $type): string
    {
        // Check if file already exists in documents table
        $exists = Document::where('transport_tracking_id', $trackingId)
            ->where('file_path', $filePath)
            ->where('type', $type)
            ->exists();

        if ($exists) {
            return 'skipped';
        }

        // Check if file exists in storage
        if (!Storage::disk('public')->exists($filePath)) {
            echo "Warning: File not found in storage: {$filePath}\n";
            // Still create the record but mark it as missing
        }

        // Get file information
        $fullPath = storage_path('app/public/' . $filePath);
        $fileSize = file_exists($fullPath) ? filesize($fullPath) : null;
        $mimeType = null;
        $originalName = basename($filePath);

        if (file_exists($fullPath)) {
            // Try to get mime type
            if (function_exists('mime_content_type')) {
                $mimeType = mime_content_type($fullPath);
            } elseif (function_exists('finfo_file')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $fullPath);
                finfo_close($finfo);
            }

            // Fallback to extension-based mime type
            if (!$mimeType) {
                $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                $mimeTypes = [
                    'pdf' => 'application/pdf',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                ];
                $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
            }
        }

        try {
            // Create document record
            Document::create([
                'transport_tracking_id' => $trackingId,
                'file_path' => $filePath,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'size' => $fileSize,
                'type' => $type,
            ]);

            return 'migrated';
        } catch (\Exception $e) {
            echo "Error migrating file {$filePath}: " . $e->getMessage() . "\n";
            return 'error';
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally delete migrated documents
        // Uncomment if you want to rollback
        // Document::whereIn('type', ['provider', 'client'])->delete();
    }
};
