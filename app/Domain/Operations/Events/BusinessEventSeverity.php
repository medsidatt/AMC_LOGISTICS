<?php

namespace App\Domain\Operations\Events;

/** Severity of a business event (exception-first ordering). */
enum BusinessEventSeverity: string
{
    case CRITICAL = 'critical';
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';
    case INFORMATIONAL = 'informational';
}
