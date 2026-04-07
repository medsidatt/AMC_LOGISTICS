import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import Modal from '@/components/ui/Modal';
import KpiCard from '@/components/dashboard/KpiCard';
import KpiGrid from '@/components/dashboard/KpiGrid';
import { useForm } from '@inertiajs/react';
import { Wrench, AlertTriangle, CheckCircle2, Search, ChevronRight } from 'lucide-react';
import { clsx } from 'clsx';

interface Profile {
    type: string;
    interval_km: number;
    next_km: number;
    remaining: number;
    status: string;
}

interface TruckRow {
    id: number;
    matricule: string;
    total_kilometers: number;
    maintenance_type: string;
    profiles: Profile[];
    overall_status: string;
    open_issues: number;
}

interface MaintenanceType {
    value: string;
    label: string;
}

interface Props {
    trucks: TruckRow[];
    counts: { overdue: number; warning: number; ok: number };
    maintenanceTypes: MaintenanceType[];
}

export default function MaintenanceIndex({ trucks, counts, maintenanceTypes }: Props) {
    const [filter, setFilter] = useState<'all' | 'red' | 'yellow' | 'green'>('all');
    const [search, setSearch] = useState('');
    const [recordTruck, setRecordTruck] = useState<TruckRow | null>(null);

    const recordForm = useForm({
        maintenance_date: new Date().toISOString().split('T')[0],
        maintenance_type: 'general',
        notes: '',
        kilometers_at_maintenance: '',
    });

    const filtered = trucks.filter((t) => {
        if (filter !== 'all' && t.overall_status !== filter) return false;
        if (search && !t.matricule.toLowerCase().includes(search.toLowerCase())) return false;
        return true;
    });

    const openRecord = (truck: TruckRow) => {
        setRecordTruck(truck);
        recordForm.setData('kilometers_at_maintenance', String(truck.total_kilometers));
    };

    const submitRecord = (e: React.FormEvent) => {
        e.preventDefault();
        if (!recordTruck) return;
        recordForm.post(`/maintenance/${recordTruck.id}/record`, { onSuccess: () => setRecordTruck(null) });
    };

    const statusBadge = (status: string) => {
        const v = status === 'red' ? 'danger' : status === 'yellow' ? 'warning' : 'success';
        const l = status === 'red' ? 'Urgent' : status === 'yellow' ? 'Bientôt' : 'OK';
        return <Badge variant={v}>{l}</Badge>;
    };

    return (
        <AuthenticatedLayout title="Maintenance">
            <Head title="Maintenance" />

            <KpiGrid>
                <KpiCard label="Urgent" value={counts.overdue} icon={<AlertTriangle size={22} />} color="var(--color-danger)" />
                <KpiCard label="A prévoir" value={counts.warning} icon={<Wrench size={22} />} color="var(--color-warning)" />
                <KpiCard label="OK" value={counts.ok} icon={<CheckCircle2 size={22} />} color="var(--color-success)" />
            </KpiGrid>

            <Card className="mt-6">
                <div className="flex flex-wrap items-center gap-3 mb-4">
                    <div className="relative flex-1 min-w-[200px]">
                        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                        <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Rechercher par matricule..."
                            className="w-full pl-9 pr-4 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm" />
                    </div>
                    <div className="flex gap-1">
                        {(['all', 'red', 'yellow', 'green'] as const).map((f) => (
                            <button key={f} onClick={() => setFilter(f)}
                                className={clsx('px-3 py-1.5 rounded-lg text-xs font-medium transition', filter === f ? 'bg-[var(--color-primary)] text-white' : 'bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]')}>
                                {f === 'all' ? 'Tous' : f === 'red' ? 'Urgent' : f === 'yellow' ? 'A prévoir' : 'OK'}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-[var(--color-surface-hover)]">
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Camion</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Compteur</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">État maintenance</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Km restant</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Problèmes signalés</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--color-border)]">
                            {filtered.length === 0 ? (
                                <tr><td colSpan={6} className="px-4 py-8 text-center text-[var(--color-text-muted)]">Aucun camion</td></tr>
                            ) : filtered.map((truck) => {
                                const general = truck.profiles.find((p) => p.type === 'general');
                                return (
                                    <tr key={truck.id} className="hover:bg-[var(--color-surface-hover)]">
                                        <td className="px-4 py-3">
                                            <a href={`/trucks/${truck.id}/show`} className="text-[var(--color-primary)] hover:underline font-medium">{truck.matricule}</a>
                                        </td>
                                        <td className="px-4 py-3 text-[var(--color-text)]">{truck.total_kilometers?.toLocaleString('fr-FR')} km</td>
                                        <td className="px-4 py-3 text-center">
                                            {general ? statusBadge(general.status) : <span className="text-[var(--color-text-muted)]">-</span>}
                                        </td>
                                        <td className="px-4 py-3 text-center text-[var(--color-text)]">
                                            {general ? `${general.remaining?.toLocaleString('fr-FR')} km` : '-'}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {truck.open_issues > 0 ? (
                                                <Badge variant="danger">{truck.open_issues}</Badge>
                                            ) : (
                                                <Badge variant="success">0</Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <Button size="sm" variant="ghost" onClick={() => openRecord(truck)} title="Enregistrer maintenance">
                                                <Wrench size={14} />
                                            </Button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </Card>

            <div className="flex gap-3 mt-4">
                <a href="/maintenance/rules" className="text-sm text-[var(--color-primary)] hover:underline flex items-center gap-1">
                    Gérer les règles <ChevronRight size={14} />
                </a>
                <a href="/maintenance/history" className="text-sm text-[var(--color-primary)] hover:underline flex items-center gap-1">
                    Historique <ChevronRight size={14} />
                </a>
            </div>

            {/* Record general maintenance modal */}
            <Modal open={!!recordTruck} onClose={() => setRecordTruck(null)} title={`Maintenance générale — ${recordTruck?.matricule}`}>
                <form onSubmit={submitRecord}>
                    <FormInput label="Date" type="date" name="maintenance_date" value={recordForm.data.maintenance_date} onChange={(e) => recordForm.setData('maintenance_date', e.target.value)} required />
                    <FormInput label="Kilométrage au moment de la maintenance" type="number" name="kilometers_at_maintenance" value={recordForm.data.kilometers_at_maintenance} onChange={(e) => recordForm.setData('kilometers_at_maintenance', e.target.value)} />
                    <FormInput label="Notes (vidange, filtres, pneus, etc.)" name="notes" value={recordForm.data.notes} onChange={(e) => recordForm.setData('notes', e.target.value)} />
                    <p className="text-xs text-[var(--color-text-muted)] mt-2">La maintenance générale couvre : vidange huile, filtres, pneumatiques, inspection complète.</p>
                    <div className="flex justify-end gap-2 mt-6">
                        <Button variant="secondary" onClick={() => setRecordTruck(null)}>Annuler</Button>
                        <Button type="submit" loading={recordForm.processing}>Enregistrer</Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
