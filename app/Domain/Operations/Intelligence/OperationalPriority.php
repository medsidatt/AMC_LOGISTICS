<?php

namespace App\Domain\Operations\Intelligence;

use App\Domain\Operations\Events\BusinessEventSeverity;

/**
 * The urgency ranking of a conclusion — the answer to "how urgent is it?". This is an
 * ORDERING policy over an already-decided severity (exception-first), never a business
 * calculation. Lower rank = more urgent, so a list sorts naturally with the most
 * pressing decisions first.
 *
 * @phpstan-consistent-constructor
 */
final readonly class OperationalPriority
{
    private function __construct(
        private BusinessEventSeverity $severity,
        private int $rank,
    ) {}

    public static function fromSeverity(BusinessEventSeverity $severity): self
    {
        return new self($severity, self::rankOf($severity));
    }

    public function severity(): BusinessEventSeverity
    {
        return $this->severity;
    }

    /** 1 (most urgent) … 5 (least). Pure mapping of the severity scale. */
    public function rank(): int
    {
        return $this->rank;
    }

    /** Critical or high — demands action now. */
    public function isImmediate(): bool
    {
        return $this->rank <= 2;
    }

    public function label(): string
    {
        return $this->severity->value;
    }

    private static function rankOf(BusinessEventSeverity $severity): int
    {
        return match ($severity) {
            BusinessEventSeverity::CRITICAL => 1,
            BusinessEventSeverity::HIGH => 2,
            BusinessEventSeverity::MEDIUM => 3,
            BusinessEventSeverity::LOW => 4,
            BusinessEventSeverity::INFORMATIONAL => 5,
        };
    }
}
