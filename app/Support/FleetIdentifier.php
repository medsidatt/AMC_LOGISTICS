<?php

namespace App\Support;

/**
 * Guards the auto-creation of fleet master data (trucks, drivers, transporters)
 * from free-text identifiers coming off manual entry or Excel imports.
 *
 * A blank cell, a stray dash, or a placeholder like "N/A" must never spawn a
 * permanent phantom truck or driver that then pollutes counts and KPIs.
 */
class FleetIdentifier
{
    /** Values that look like data but mean "no value". */
    private const PLACEHOLDERS = [
        'n/a', 'na', 'n.a', 'nd', 'n.d', 'n.d.', 's/n',
        '-', '--', '---', '—', '.', '..', '...', '?', '??',
        'inconnu', 'unknown', 'none', 'null', 'test',
        'x', 'xx', 'xxx', '0', '00', '000',
    ];

    /**
     * Whether a value is a plausible, real-world identifier worth persisting.
     */
    public static function isPlausible(?string $value): bool
    {
        $value = trim((string) $value);

        if (mb_strlen($value) < 2) {
            return false;
        }

        return ! in_array(mb_strtolower($value), self::PLACEHOLDERS, true);
    }
}
