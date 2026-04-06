import { useState, useCallback } from 'react';
import { router } from '@inertiajs/react';
import type { FilterState } from '@/types/models';

export function useFilters(initialFilters: FilterState, url: string) {
    const [filters, setFilters] = useState<FilterState>(initialFilters);
    const [loading, setLoading] = useState(false);

    const applyFilters = useCallback((newFilters?: FilterState) => {
        const f = newFilters ?? filters;
        setLoading(true);

        // Clean empty values
        const params: Record<string, string> = {};
        Object.entries(f).forEach(([k, v]) => {
            if (v !== undefined && v !== null && v !== '') {
                params[k] = String(v);
            }
        });

        router.get(url, params, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => setLoading(false),
        });
    }, [filters, url]);

    const updateFilter = useCallback((key: keyof FilterState, value: string | number | null) => {
        setFilters((prev) => ({ ...prev, [key]: value ?? undefined }));
    }, []);

    const resetFilters = useCallback(() => {
        setFilters({});
        setLoading(true);
        router.get(url, {}, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => setLoading(false),
        });
    }, [url]);

    return { filters, updateFilter, applyFilters, resetFilters, loading };
}
