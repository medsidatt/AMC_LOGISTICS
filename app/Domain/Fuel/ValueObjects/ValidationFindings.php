<?php

namespace App\Domain\Fuel\ValueObjects;

use App\Enums\Fuel\BusinessFinding;
use App\Enums\Fuel\TechnicalFinding;
use InvalidArgumentException;

/**
 * Immutable collection of the anomalies detected on one row, keeping the two categories independent.
 * Owns the "collection" concept; the aggregate rules (has-any-reject / has-any-review) are exposed for
 * the ClassificationPolicy — this VO never decides persistence/KPI/review itself.
 */
final class ValidationFindings
{
    /** @var list<TechnicalFinding> */
    public readonly array $technical;

    /** @var list<BusinessFinding> */
    public readonly array $business;

    /**
     * @param  list<TechnicalFinding>  $technical
     * @param  list<BusinessFinding>  $business
     */
    public function __construct(array $technical = [], array $business = [])
    {
        $this->technical = self::dedupe($technical, TechnicalFinding::class);
        $this->business = self::dedupe($business, BusinessFinding::class);
    }

    public static function none(): self
    {
        return new self;
    }

    public function isEmpty(): bool
    {
        return $this->technical === [] && $this->business === [];
    }

    public function hasTechnical(): bool
    {
        return $this->technical !== [];
    }

    public function hasBusiness(): bool
    {
        return $this->business !== [];
    }

    /** Any technical finding that forces the record out of the ledger. */
    public function hasRejectingFinding(): bool
    {
        foreach ($this->technical as $f) {
            if ($f->forcesReject()) {
                return true;
            }
        }

        return false;
    }

    /** Any finding (technical or business) that requires human review. */
    public function hasReviewFinding(): bool
    {
        foreach ($this->technical as $f) {
            if ($f->forcesReview()) {
                return true;
            }
        }
        foreach ($this->business as $f) {
            if ($f->forcesReview()) {
                return true;
            }
        }

        return false;
    }

    public function withTechnical(TechnicalFinding $finding): self
    {
        return new self([...$this->technical, $finding], $this->business);
    }

    public function withBusiness(BusinessFinding $finding): self
    {
        return new self($this->technical, [...$this->business, $finding]);
    }

    /** @return list<string> */
    public function technicalCodes(): array
    {
        return array_map(fn (TechnicalFinding $f) => $f->value, $this->technical);
    }

    /** @return list<string> */
    public function businessCodes(): array
    {
        return array_map(fn (BusinessFinding $f) => $f->value, $this->business);
    }

    /**
     * @param  array<int,object>  $items
     * @return list<object>
     */
    private static function dedupe(array $items, string $enumClass): array
    {
        $seen = [];
        $out = [];
        foreach ($items as $item) {
            if (! $item instanceof $enumClass) {
                throw new InvalidArgumentException('Expected instance of '.$enumClass);
            }
            if (! isset($seen[$item->value])) {
                $seen[$item->value] = true;
                $out[] = $item;
            }
        }

        return $out;
    }
}
