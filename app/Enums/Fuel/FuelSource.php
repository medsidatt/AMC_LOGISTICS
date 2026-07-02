<?php

namespace App\Enums\Fuel;

/**
 * Provenance of a fuel record — independent of what it is (type) and what is wrong with it (findings).
 * Drives lineage, dedup strategy, trust, and multi-provider growth; never the business decision.
 */
enum FuelSource: string
{
    case EDK_CARD = 'EDK_CARD';         // histo_rechaerge_compte_carte*
    case EDK_ACCOUNT = 'EDK_ACCOUNT';   // histo_rechaerge_compte*
    case FLEETI_VOLUME = 'FLEETI_VOLUME'; // Volume de carburant 2.0
    case FLEETI_TANK = 'FLEETI_TANK';   // Carburant
    case API = 'API';                   // live telemetry / future ERP push
    case MANUAL = 'MANUAL';             // human / reviewer-created
    case CSV = 'CSV';                   // generic import

    public function label(): string
    {
        return match ($this) {
            self::EDK_CARD => 'EDK — carte',
            self::EDK_ACCOUNT => 'EDK — compte',
            self::FLEETI_VOLUME => 'Fleeti — Volume 2.0',
            self::FLEETI_TANK => 'Fleeti — Carburant',
            self::API => 'API',
            self::MANUAL => 'Saisie manuelle',
            self::CSV => 'CSV',
        };
    }
}
