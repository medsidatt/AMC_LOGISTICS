<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a calculator asks for an operational parameter that is not stored.
 *
 * The service never invents a business default (ADR-008): a missing parameter is
 * a seeding error to surface loudly, not a value to guess.
 */
class MissingOperationalParameterException extends RuntimeException
{
    public static function for(string $key): self
    {
        return new self("Operational parameter not found: {$key}. It must be seeded (see OperationalParameterSeeder / ADR-008).");
    }
}
