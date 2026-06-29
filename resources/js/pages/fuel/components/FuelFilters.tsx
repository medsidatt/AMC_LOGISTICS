import { router } from '@inertiajs/react';
import FilterBar from '@/components/ui/FilterBar';
import FormSelect from '@/components/ui/FormSelect';

interface Opt { id: number | string; name: string }

interface Props {
    tab: string;
    filters: Record<string, string>;
    trucks: Opt[];
    drivers: Opt[];
}

/**
 * Fuel workspace filters (shared FilterBar). Submits via router.get and preserves
 * the active tab; the driver filter only applies to the EDK tab (Fleeti has no driver).
 */
export default function FuelFilters({ tab, filters, trucks, drivers }: Props) {
    const activeCount = Object.keys(filters).length;

    const apply = (key: string, value: string | number | null) => {
        const params: Record<string, string> = { tab, ...filters };
        if (value) params[key] = String(value); else delete params[key];
        Object.keys(params).forEach((k) => { if (!params[k]) delete params[k]; });
        router.get('/fuel', params, { preserveState: true, preserveScroll: true });
    };

    const reset = () => router.get('/fuel', { tab }, { preserveState: true, preserveScroll: true });
    const toOpts = (items: Opt[]) => items.map((i) => ({ value: i.id, label: i.name }));

    return (
        <FilterBar activeCount={activeCount} onReset={reset}>
            <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <FormSelect label="Camion" placeholder="Tous" options={toOpts(trucks)} value={filters.truck_id ?? null} onChange={(v) => apply('truck_id', v)} wrapperClass="mb-0" />
                {tab === 'edk' && (
                    <FormSelect label="Chauffeur" placeholder="Tous" options={toOpts(drivers)} value={filters.driver_id ?? null} onChange={(v) => apply('driver_id', v)} wrapperClass="mb-0" />
                )}
                <div>
                    <label className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">Date du</label>
                    <input type="date" value={filters.start_date ?? ''} onChange={(e) => apply('start_date', e.target.value || null)} className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm" />
                </div>
                <div>
                    <label className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">Date au</label>
                    <input type="date" value={filters.end_date ?? ''} onChange={(e) => apply('end_date', e.target.value || null)} className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm" />
                </div>
            </div>
        </FilterBar>
    );
}
