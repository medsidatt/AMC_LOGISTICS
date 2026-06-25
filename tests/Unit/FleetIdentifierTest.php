<?php

namespace Tests\Unit;

use App\Support\FleetIdentifier;
use PHPUnit\Framework\TestCase;

class FleetIdentifierTest extends TestCase
{
    public function test_rejects_blank_and_placeholder_values(): void
    {
        foreach (['', ' ', '   ', 'N/A', 'n/a', '-', '—', '?', 'unknown', 'NONE', 'TEST', 'x', '0', '00', null] as $value) {
            $this->assertFalse(
                FleetIdentifier::isPlausible($value),
                sprintf('Expected "%s" to be rejected', var_export($value, true))
            );
        }
    }

    public function test_accepts_real_identifiers(): void
    {
        foreach (['AA-123-BB', 'DK-4567', 'Mohamed Sidi', '12345', 'TH-0098'] as $value) {
            $this->assertTrue(
                FleetIdentifier::isPlausible($value),
                "Expected \"{$value}\" to be accepted"
            );
        }
    }
}
