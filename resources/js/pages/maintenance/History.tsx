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
}

interface Props {
    maintenances: { data: MaintenanceRecord[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    trucks: { id: number; matricule: string }[];
    maintenanceTypes: { value: string; label: string }[];
    filters: Record<string, string>;
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
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Type</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Km</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Seuil prévu</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Intervalle</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Notes</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--color-border)]">
                            {maintenances.data.length === 0 ? (
                                <tr><td colSpan={7} className="px-4 py-8 text-center text-[var(--color-text-muted)]">
                                    <HistoryIcon size={32} className="mx-auto mb-2 opacity-30" />
                                    Aucune maintenance enregistrée
                                </td></tr>
                            ) : maintenances.data.map((m) => (
                                <tr key={m.id} className="hover:bg-[var(--color-surface-hover)]">
                                    <td className="px-4 py-3 text-[var(--color-text)]">{m.maintenance_date}</td>
                                    <td className="px-4 py-3 text-[var(--color-text)] font-medium">{m.truck}</td>
                                    <td className="px-4 py-3 text-[var(--color-text)]">{maintenanceTypes.find((t) => t.value === m.maintenance_type)?.label ?? m.maintenance_type}</td>
                                    <td className="px-4 py-3 text-[var(--color-text)]">{m.kilometers_at_maintenance?.toLocaleString('fr-FR')}</td>
                                    <td className="px-4 py-3 text-[var(--color-text-secondary)]">{m.trigger_km?.toLocaleString('fr-FR') ?? '-'}</td>
                                    <td className="px-4 py-3 text-[var(--color-text-secondary)]">{m.interval_km?.toLocaleString('fr-FR') ?? '-'}</td>
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
