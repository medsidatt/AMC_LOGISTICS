<?php

namespace App\Http\Analytics;

use App\Domain\Analytics\Exports\ExportResult;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wraps an {@see ExportResult} into an HTTP download response — content, mime type, filename,
 * and headers. Transport only: it builds no content and serializes nothing (the engine already
 * produced the bytes).
 */
final class ExportDownloadResponse
{
    public static function fromResult(ExportResult $result): Response
    {
        return new Response($result->content, Response::HTTP_OK, [
            'Content-Type' => $result->mimeType,
            'Content-Disposition' => 'attachment; filename="'.$result->filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
