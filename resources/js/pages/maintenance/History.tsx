import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import FormSelect from '@/components/ui/FormSelect';
import Pagination from '@/components/ui/Pagination';
import { History as HistoryIcon } from 'lucide-react';
import MaintenanceTabs from '@/components/maintenance/MaintenanceTabs';

interface MaintenanceRecord {
    id: number;
    truck: string;
    maintenance_type: string;
    maintenance_date: string;
    kilometers_at_maintenance: number;
    trigger_km: number | null;
    interval_km: number | null;
    notes: string | null;
    oil_type?: string | null;
    oil_type_label?: string | null;
    oil_change_km?: number | null;
    next_oil_change_km?: number | null;
    gearbox_status?: string | null;
    differential_status?: string | null;
    hydraulic_status?: string | null;
    greasing_status?: string | null;
    filter_oil_changed?: boolean;
    filter_hydraulic_changed?: boolean;
    filter_air_changed?: boolean;
    filter_fuel_changed?: boolean;
}

interface Props {
    maintenances: { data: MaintenanceRecord[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    trucks: { id: number; matricule: string }[];
    maintenanceTypes: { value: string; label: string }[];
    filters: Record<string, string>;
}

function FiltersSummary({ m }: { m: MaintenanceRecord }) {
    const flags: string[] = [];
    if (m.filter_oil_changed) flags.push('Huile');
    if (m.filter_hydraulic_changed) flags.push('Hyd.');
    if (m.filter_air_changed) flags.push('Air');
    if (m.filter_fuel_changed) flags.push('Carb.');
    return <>{flags.length === 0 ? '-' : flags.join(', ')}</>;
}

export default function MaintenanceHistory({ maintenances, trucks, maintenanceTypes, filters }: Props) {
    const truckOpts = trucks.map((t) => ({ value: t.id, label: t.matricule }));

    const applyFilter = (key: string, value: string | number | null) => {
        const newFilters = { ...filters, [key]: value ? String(value) : '' };
        Object.keys(newFilters).forEach((k) => { if (!newFilters[k]) delete newFilters[k]; });
        router.get('/maintenance/history', newFilters, { preserveState: true, preserveScroll: true });
    };

    return (
        <AuthenticatedLayout title="Historique maintenance">
            <Head title="Historique maintenance" />

            <MaintenanceTabs />

            <Card className="mb-4">
                <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <FormSelect label="Camion" placeholder="Tous" options={truckOpts} value={filters.truck_id ?? null} onChange={(v) => applyFilter('truck_id', v)} wrapperClass="mb-0" />
                    <FormSelect label="Type" placeholder="Tous" options={maintenanceTypes} value={filters.maintenance_type ?? null} onChange={(v) => applyFilter('maintenance_type', v)} wrapperClass="mb-0" />
                </div>
            </Card>

            <Card padding={false}>
                <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-[var(--color-surface-hover)]">
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Date</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Camion</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Km</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Huile</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Vidange à</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Prochaine</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Filtres</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Notes</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--color-border)]">
                            {maintenances.data.length === 0 ? (
                                <tr><td colSpan={8} className="px-4 py-8 text-center text-[var(--color-text-muted)]">
                                    <HistoryIcon size={32} className="mx-auto mb-2 opacity-30" />
                                    Aucune maintenance enregistrée
                                </td></tr>
                            ) : maintenances.data.map((m) => (
                                <tr key={m.id} className="hover:bg-[var(--color-surface-hover)]">
                                    <td className="px-4 py-3 text-[var(--color-text)]">{m.maintenance_date}</td>
                                    <td className="px-4 py-3 text-[var(--color-text)] font-medium">{m.truck}</td>
                                    <td className="px-4 py-3 text-[var(--color-text)]">{m.kilometers_at_maintenance?.toLocaleString('fr-FR')}</td>
                                    <td className="px-4 py-3 text-[var(--color-text-secondary)]">{m.oil_type_label ?? '-'}</td>
                                    <td className="px-4 py-3 text-[var(--color-text-secondary)]">{m.oil_change_km != null ? Number(m.oil_change_km).toLocaleString('fr-FR') : '-'}</td>
                                    <td className="px-4 py-3 text-[var(--color-text-secondary)]">{m.next_oil_change_km != null ? Number(m.next_oil_change_km).toLocaleString('fr-FR') : '-'}</td>
                                    <td className="px-4 py-3 text-[var(--color-text-secondary)]"><FiltersSummary m={m} /></td>
                                    <td className="px-4 py-3 text-[var(--color-text-secondary)] max-w-[200px] truncate">{m.notes ?? '-'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={maintenances} />
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
