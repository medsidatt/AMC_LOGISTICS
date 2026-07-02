<?php

namespace App\Domain\Analytics\Exports\Enums;

/**
 * A report export format. HTML, CSV, and JSON are implemented; PDF and EXCEL are RESERVED —
 * their identity exists here but they carry no registry definition and no engine until a
 * later phase (R5.x). Metadata only.
 */
enum ExportFormat: string
{
    case HTML = 'html';
    case CSV = 'csv';
    case JSON = 'json';

    // Reserved (not implemented in R5.0).
    case PDF = 'pdf';
    case EXCEL = 'excel';
}
