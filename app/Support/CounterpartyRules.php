<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class CounterpartyRules
{
    /**
     * Shared validation rules for contact "counterparty" master-data
     * (Providers, Transporters). The contact fields are identical across these
     * modules. The unique-name constraint is entity-specific — Providers enforce
     * it, Transporters do not — so it is opt-in via $uniqueTable, preserving each
     * module's existing behaviour exactly.
     */
    public static function base(?string $uniqueTable = null, ?int $ignoreId = null): array
    {
        $name = ['required', 'string', 'max:255'];

        if ($uniqueTable !== null) {
            $unique = Rule::unique($uniqueTable, 'name');
            if ($ignoreId !== null) {
                $unique->ignore($ignoreId);
            }
            $name[] = $unique;
        }

        return [
            'name' => $name,
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|max:255',
        ];
    }
}
