<?php

namespace App\Enums\Fuel;

/**
 * A reviewer's decision on a PENDING transaction. The outcome corrects FACTS (re-attribute a truck,
 * waive findings); ClassificationPolicy still computes the resulting KPI eligibility — the reviewer
 * never sets it directly.
 */
enum ReviewOutcome: string
{
    case RE_ATTRIBUTED = 'RE_ATTRIBUTED';                 // reviewer identifies the correct fleet truck
    case PROMOTED_TO_KPI = 'PROMOTED_TO_KPI';             // reviewer waives the business anomalies as legitimate
    case CONFIRMED_NON_OPERATIONAL = 'CONFIRMED_NON_OPERATIONAL'; // real financial movement, not fleet fuel
    case MARKED_FRAUD = 'MARKED_FRAUD';                   // flagged for investigation
    case DISMISSED = 'DISMISSED';                         // reviewed, no operational action

    public function requiresTruck(): bool
    {
        return $this === self::RE_ATTRIBUTED;
    }

    public function label(): string
    {
        return match ($this) {
            self::RE_ATTRIBUTED => 'Ré-attribuer à un camion',
            self::PROMOTED_TO_KPI => 'Valider pour les KPI',
            self::CONFIRMED_NON_OPERATIONAL => 'Confirmer non opérationnel',
            self::MARKED_FRAUD => 'Signaler une fraude',
            self::DISMISSED => 'Clore sans action',
        };
    }
}
