import { router } from '@inertiajs/react';
import { clsx } from 'clsx';
import FilterBar from '@/components/ui/FilterBar';
import FormSelect from '@/components/ui/FormSelect';

interface DropdownItem { id: number | string; name?: string; matricule?: string }

interface Props {
    filters: Record<string, string>;
    sort: { by: string; dir: string };
    trucks: DropdownItem[];
    drivers: DropdownItem[];
    providers: DropdownItem[];
    transporters: DropdownItem[];
    products: DropdownItem[];
}

type PresetKey = 'today' | 'week' | 'month' | 'year' | '7d' | '30d';

const toIsoDate = (d: Date): string =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

function computeRange(kind: PresetKey): { start: string; end: string } {
    const now = new Date();
    let start: Date;
    if (kind === 'today') start = new Date(now);
    else if (kind === 'week') { start = new Date(now); start.setDate(start.getDate() - ((start.getDay() + 6) % 7)); }
    else if (kind === 'month') start = new Date(now.getFullYear(), now.getMonth(), 1);
    else if (kind === 'year') start = new Date(now.getFullYear(), 0, 1);
    else if (kind === '7d') { start = new Date(now); start.setDate(now.getDate() - 6); }
    else { start = new Date(now); start.setDate(now.getDate() - 29); }
    return { start: toIsoDate(start), end: toIsoDate(now) };
}

/**
 * Transport Tracking server-side filters. Composes the generic <FilterBar> shell;
 * each change submits via router.get (preserving sort), keeping the list server-paginated.
 */
export default function TransportFilters({ filters, sort, trucks, drivers, providers, transporters, products }: Props) {
    const activeCount = Object.keys(filters).length;

    const buildParams = (overrides: Record<string, string | null> = {}) => {
        const params: Record<string, string> = { ...filters };
        if (sort.by !== 'client_date' || sort.dir !== 'desc') {
            params.sort_by = sort.by;
            params.sort_dir = sort.dir;
        }
        Object.entries(overrides).forEach(([k, v]) => { if (v) params[k] = v; else delete params[k]; });
        Object.keys(params).forEach((k) => { if (!params[k]) delete params[k]; });
        return params;
    };

    const applyFilter = (key: string, value: string | number | null) => {
        const overrides: Record<string, string | null> = { [key]: value ? String(value) : null };
        if (key === 'truck_id') overrides.driver_id = null; // driver list is scoped to the truck
        router.get('/transport_tracking', buildParams(overrides), { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => router.get('/transport_tracking', {}, { preserveState: true, preserveScroll: true });

    const applyPreset = (kind: PresetKey) => {
        const { start, end } = computeRange(kind);
        router.get('/transport_tracking', buildParams({ start_date: start, end_date: end }), { preserveState: true, preserveScroll: true });
    };

    const activePreset: PresetKey | null = (() => {
        const f = filters.start_date ?? ''; const t = filters.end_date ?? '';
        if (!f || !t) return null;
        for (const p of ['today', 'week', 'month', 'year', '7d', '30d'] as PresetKey[]) {
            const { start, end } = computeRange(p);
            if (start === f && end === t) return p;
        }
        return null;
    })();

    const toOptions = (items: DropdownItem[], key: 'name' | 'matricule' = 'name') =>
        items.map((i) => ({ value: i.id, label: (key === 'matricule' ? i.matricule : i.name) ?? String(i.id) }));

    const presets = (
        <div className="pt-4 border-t border-[var(--color-border)]">
            <label className="block text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-2">Période rapide</label>
            <div className="flex flex-wrap gap-2">
                {([
                    { key: 'today', label: "Aujourd'hui" }, { key: 'week', label: 'Cette semaine' }, { key: 'month', label: 'Ce mois' },
                    { key: 'year', label: 'Cette année' }, { key: '7d', label: '7 jours' }, { key: '30d', label: '30 jours' },
                ] as { key: PresetKey; label: string }[]).map((p) => (
                    <button
                        key={p.key} type="button" onClick={() => applyPreset(p.key)}
                        className={clsx(
                            'px-3 py-1 text-xs rounded-full border transition-colors',
                            activePreset === p.key
                                ? 'bg-[var(--color-primary)] border-[var(--color-primary)] text-white'
                                : 'bg-[var(--color-surface)] border-[var(--color-border)] hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]',
                        )}
                    >
                        {p.label}
                    </button>
                ))}
            </div>
        </div>
    );

    return (
        <FilterBar activeCount={activeCount} onReset={clearFilters} footer={presets}>
            <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <FormSelect label="Camion" placeholder="Tous" options={toOptions(trucks, 'matricule')} value={filters.truck_id ?? null} onChange={(v) => applyFilter('truck_id', v)} wrapperClass="mb-0" />
                <FormSelect
                    label={filters.truck_id ? 'Conducteur (camion sélectionné)' : 'Conducteur'}
                    placeholder={filters.truck_id ? (drivers.length ? 'Tous les conducteurs de ce camion' : 'Aucun conducteur') : 'Tous'}
                    options={toOptions(drivers)} value={filters.driver_id ?? null} onChange={(v) => applyFilter('driver_id', v)} wrapperClass="mb-0"
                />
                <FormSelect label="Fournisseur" placeholder="Tous" options={toOptions(providers)} value={filters.provider_id ?? null} onChange={(v) => applyFilter('provider_id', v)} wrapperClass="mb-0" />
                <FormSelect label="Produit" placeholder="Tous" options={toOptions(products)} value={filters.product ?? null} onChange={(v) => applyFilter('product', v)} wrapperClass="mb-0" />
            </div>
            <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <FormSelect label="Transporteur" placeholder="Tous" options={toOptions(transporters)} value={filters.transporter_id ?? null} onChange={(v) => applyFilter('transporter_id', v)} wrapperClass="mb-0" />
                <div>
                    <label className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">Date du</label>
                    <input type="date" value={filters.start_date ?? ''} onChange={(e) => applyFilter('start_date', e.target.value || null)}
                        className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm" />
                </div>
                <div>
                    <label className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">Date au</label>
                    <input type="date" value={filters.end_date ?? ''} onChange={(e) => applyFilter('end_date', e.target.value || null)}
                        className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm" />
                </div>
            </div>
        </FilterBar>
    );
}
