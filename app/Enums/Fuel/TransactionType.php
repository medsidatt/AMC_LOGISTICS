<?php

namespace App\Enums\Fuel;

/**
 * What business event a fuel-card row represents — independent of what is wrong with it (findings)
 * and where it came from (source). Only FUEL_RECHARGE can ever be KPI-eligible.
 */
enum TransactionType: string
{
    case FUEL_RECHARGE = 'FUEL_RECHARGE';
    case ACCOUNT_RECHARGE = 'ACCOUNT_RECHARGE';
    case ACCOUNT_TRANSFER = 'ACCOUNT_TRANSFER';
    case REVERSAL = 'REVERSAL';
    case UNKNOWN = 'UNKNOWN';

    /** Only a genuine truck fuel recharge may participate in operational Fuel KPIs. */
    public function isKpiCapable(): bool
    {
        return $this === self::FUEL_RECHARGE;
    }

    /** An unclassifiable event always needs a human to name it. */
    public function requiresReviewWhenClean(): bool
    {
        return $this === self::UNKNOWN;
    }

    public function label(): string
    {
        return match ($this) {
            self::FUEL_RECHARGE => 'Recharge carburant',
            self::ACCOUNT_RECHARGE => 'Recharge de compte',
            self::ACCOUNT_TRANSFER => 'Transfert de compte',
            self::REVERSAL => 'Annulation / remboursement',
            self::UNKNOWN => 'Type inconnu',
        };
    }
}
