import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import FormTextarea from '@/components/ui/FormTextarea';
import Modal from '@/components/ui/Modal';
import KpiCard from '@/components/dashboard/KpiCard';
import KpiGrid from '@/components/dashboard/KpiGrid';
import { useForm } from '@inertiajs/react';
import { Wrench, AlertTriangle, CheckCircle2, Search, ShieldCheck } from 'lucide-react';
import MaintenanceTabs from '@/components/maintenance/MaintenanceTabs';
import { usePermission } from '@/hooks/usePermission';
import { clsx } from 'clsx';

interface Profile {
    type: string;
    interval_km: number;
    next_km: number;
    remaining: number;
    status: string;
}

interface InspectionIssue {
    id: number;
    category: string;
    severity: string;
    issue_notes: string | null;
    inspection_date: string | null;
}

interface TruckRow {
    id: number;
    matricule: string;
    total_kilometers: number;
    maintenance_type: string;
    profiles: Profile[];
    overall_status: string;
    open_issues: number;
    open_inspection_issues: number;
    inspection_issues: InspectionIssue[];
}

interface MaintenanceType {
    value: string;
    label: string;
}

interface Props {
    trucks: TruckRow[];
    counts: { overdue: number; warning: number; ok: number };
    maintenanceTypes: MaintenanceType[];
    oilTypes: Record<string, string>;
}

const SEVERITY_VARIANT: Record<string, 'default' | 'warning' | 'danger'> = {
    minor: 'default',
    major: 'warning',
    critical: 'danger',
};

export default function MaintenanceIndex({ trucks, counts, oilTypes }: Props) {
    const [filter, setFilter] = useState<'all' | 'red' | 'yellow' | 'green'>('all');
    const [search, setSearch] = useState('');
    const [recordTruck, setRecordTruck] = useState<TruckRow | null>(null);
    const { can } = usePermission();
    const canRecord = can('maintenance-create');

    const recordForm = useForm<Record<string, any>>({
        maintenance_date: new Date().toISOString().split('T')[0],
        maintenance_type: 'general',
        notes: '',
        kilometers_at_maintenance: '',
        oil_type: '',
        oil_change_km: '',
        next_oil_change_km: '',
        gearbox_status: 'NORMAL',
        differential_status: 'NORMAL',
        hydraulic_status: 'NORMAL',
        greasing_status: 'NORMAL',
        filter_oil_changed: false,
        filter_hydraulic_changed: false,
        filter_air_changed: false,
        filter_fuel_changed: false,
        linked_inspection_issue_ids: [] as number[],
    });

    const filtered = trucks.filter((t) => {
        if (filter !== 'all' && t.overall_status !== filter) return false;
        if (search && !t.matricule.toLowerCase().includes(search.toLowerCase())) return false;
        return true;
    });

    const openRecord = (truck: TruckRow) => {
        setRecordTruck(truck);
        recordForm.reset();
        recordForm.setData({
            maintenance_date: new Date().toISOString().split('T')[0],
            maintenance_type: 'general',
            notes: '',
            kilometers_at_maintenance: String(truck.total_kilometers ?? ''),
            oil_type: '',
            oil_change_km: '',
            next_oil_change_km: '',
            gearbox_status: 'NORMAL',
            differential_status: 'NORMAL',
            hydraulic_status: 'NORMAL',
            greasing_status: 'NORMAL',
            filter_oil_changed: false,
            filter_hydraulic_changed: false,
            filter_air_changed: false,
            filter_fuel_changed: false,
            linked_inspection_issue_ids: [],
        });
    };

    const toggleIssueLink = (issueId: number) => {
        const list = recordForm.data.linked_inspection_issue_ids as number[];
        const next = list.includes(issueId) ? list.filter((id) => id !== issueId) : [...list, issueId];
        recordForm.setData('linked_inspection_issue_ids', next);
    };

    const submitRecord = (e: React.FormEvent) => {
        e.preventDefault();
        if (!recordTruck) return;
        recordForm.post(`/maintenance/${recordTruck.id}/record`, {
            forceFormData: true,
            onSuccess: () => setRecordTruck(null),
        });
    };

    const statusBadge = (status: string) => {
        const v = status === 'red' ? 'danger' : status === 'yellow' ? 'warning' : 'success';
        const l = status === 'red' ? 'Urgent' : status === 'yellow' ? 'Bientôt' : 'OK';
        return <Badge variant={v}>{l}</Badge>;
    };

    return (
        <AuthenticatedLayout title="Maintenance">
            <Head title="Maintenance" />

            <MaintenanceTabs />

            <KpiGrid>
                <KpiCard label="Urgent" value={counts.overdue} icon={<AlertTriangle size={22} />} color="var(--color-danger)" />
                <KpiCard label="A prévoir" value={counts.warning} icon={<Wrench size={22} />} color="var(--color-warning)" />
                <KpiCard label="OK" value={counts.ok} icon={<CheckCircle2 size={22} />} color="var(--color-success)" />
            </KpiGrid>

            <Card className="mt-6" padding={false}>
                <div className="p-5">
                    <div className="flex flex-wrap items-center gap-3 mb-4">
                        <div className="relative flex-1 min-w-[200px]">
                            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                            <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Rechercher par matricule..."
                                className="w-full pl-9 pr-4 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm text-[var(--color-text)] placeholder:text-[var(--color-text-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)] transition" />
                        </div>
                        <div className="flex gap-1">
                            {(['all', 'red', 'yellow', 'green'] as const).map((f) => (
                                <button key={f} onClick={() => setFilter(f)}
                                    className={clsx('px-3 py-2 rounded-lg text-xs font-medium transition', filter === f ? 'bg-[var(--color-primary)] text-white' : 'bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)] hover:bg-[var(--color-border)]')}>
                                    {f === 'all' ? `Tous (${trucks.length})` : f === 'red' ? `Urgent (${counts.overdue})` : f === 'yellow' ? `A prévoir (${counts.warning})` : `OK (${counts.ok})`}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="hidden md:block overflow-x-auto rounded-lg border border-[var(--color-border)]">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)]">
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Camion</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Compteur</th>
                                    <th className="px-4 py-3 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">État</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Km restant</th>
                                    <th className="px-4 py-3 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Checklist</th>
                                    <th className="px-4 py-3 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Inspection</th>
                                    <th className="px-4 py-3 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {filtered.length === 0 ? (
                                    <tr><td colSpan={7} className="px-4 py-12 text-center text-[var(--color-text-muted)]">
                                        <Wrench size={32} className="mx-auto mb-2 opacity-30" />
                                        Aucun camion trouvé
                                    </td></tr>
                                ) : filtered.map((truck) => {
                                    const general = truck.profiles.find((p) => p.type === 'general');
                                    return (
                                        <tr key={truck.id} className="hover:bg-[var(--color-surface-hover)] transition-colors">
                                            <td className="px-4 py-3">
                                                <a href={`/trucks/${truck.id}/show-page`} className="text-[var(--color-primary)] hover:underline font-medium">{truck.matricule}</a>
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono text-[var(--color-text)]">{truck.total_kilometers?.toLocaleString('fr-FR')} km</td>
                                            <td className="px-4 py-3 text-center">
                                                {general ? statusBadge(general.status) : <Badge variant="muted">N/A</Badge>}
                                            </td>
                                            <td className="px-4 py-3 text-right text-[var(--color-text)]">
                                                {general ? (
                                                    <span className={clsx('font-mono', general.status === 'red' ? 'text-red-600 font-bold' : general.status === 'yellow' ? 'text-amber-600' : '')}>
                                                        {general.remaining?.toLocaleString('fr-FR')} km
                                                    </span>
                                                ) : '-'}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {truck.open_issues > 0 ? <Badge variant="warning">{truck.open_issues}</Badge> : <span className="text-[var(--color-text-muted)]">0</span>}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {truck.open_inspection_issues > 0 ? <Badge variant="danger">{truck.open_inspection_issues}</Badge> : <span className="text-[var(--color-text-muted)]">0</span>}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {canRecord ? (
                                                    <Button size="sm" onClick={() => openRecord(truck)}>
                                                        <Wrench size={14} className="mr-1" /> Maintenance
                                                    </Button>
                                                ) : (
                                                    <span className="text-[var(--color-text-muted)]">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    <div className="md:hidden space-y-3">
                        {filtered.length === 0 ? (
                            <div className="text-center py-12 text-[var(--color-text-muted)]">
                                <Wrench size={32} className="mx-auto mb-2 opacity-30" />
                                Aucun camion trouvé
                            </div>
                        ) : filtered.map((truck) => {
                            const general = truck.profiles.find((p) => p.type === 'general');
                            return (
                                <div key={truck.id} className="rounded-xl border border-[var(--color-border)] p-4">
                                    <div className="flex items-center justify-between mb-3">
                                        <a href={`/trucks/${truck.id}/show-page`} className="text-[var(--color-primary)] font-semibold">{truck.matricule}</a>
                                        {general ? statusBadge(general.status) : <Badge variant="muted">N/A</Badge>}
                                    </div>
                                    <div className="grid grid-cols-2 gap-2 text-sm mb-3">
                                        <div>
                                            <span className="text-xs text-[var(--color-text-muted)]">Compteur</span>
                                            <p className="font-mono text-[var(--color-text)]">{truck.total_kilometers?.toLocaleString('fr-FR')} km</p>
                                        </div>
                                        <div>
                                            <span className="text-xs text-[var(--color-text-muted)]">Restant</span>
                                            <p className="font-mono text-[var(--color-text)]">{general ? `${general.remaining?.toLocaleString('fr-FR')} km` : '-'}</p>
                                        </div>
                                        {truck.open_inspection_issues > 0 && (
                                            <div className="col-span-2 flex items-center gap-2">
                                                <ShieldCheck size={14} className="text-red-500" />
                                                <span className="text-xs"><Badge variant="danger">{truck.open_inspection_issues}</Badge> findings d'inspection</span>
                                            </div>
                                        )}
                                    </div>
                                    {canRecord && (
                                        <Button size="sm" className="w-full" onClick={() => openRecord(truck)}>
                                            <Wrench size={14} className="mr-1" /> Enregistrer maintenance
                                        </Button>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </Card>

            <Modal open={!!recordTruck} onClose={() => setRecordTruck(null)} title={`Maintenance — ${recordTruck?.matricule}`}>
                <form onSubmit={submitRecord} className="space-y-3">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <FormInput
                            label="Date"
                            type="date"
                            name="maintenance_date"
                            value={recordForm.data.maintenance_date}
                            onChange={(e) => recordForm.setData('maintenance_date', e.target.value)}
                            required
                        />
                        <FormInput
                            label="Compteur au moment de la maintenance (Km)"
                            type="number"
                            name="kilometers_at_maintenance"
                            value={recordForm.data.kilometers_at_maintenance}
                            onChange={(e) => recordForm.setData('kilometers_at_maintenance', e.target.value)}
                        />
                    </div>

                    <fieldset className="border border-[var(--color-border)] rounded-lg p-3 space-y-2">
                        <legend className="text-sm font-semibold px-1">Huile moteur</legend>
                        <FormSelect
                            label="Type d'huile"
                            value={recordForm.data.oil_type}
                            onChange={(v) => recordForm.setData('oil_type', v)}
                            options={[{ value: '', label: '—' }, ...Object.entries(oilTypes).map(([k, l]) => ({ value: k, label: l }))]}
                        />
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <FormInput
                                label="Vidange moteur à (Km)"
                                type="number"
                                value={recordForm.data.oil_change_km}
                                onChange={(e) => recordForm.setData('oil_change_km', e.target.value)}
                            />
                            <FormInput
                                label="Prochaine vidange à (Km)"
                                type="number"
                                value={recordForm.data.next_oil_change_km}
                                onChange={(e) => recordForm.setData('next_oil_change_km', e.target.value)}
                            />
                        </div>
                    </fieldset>

                    <fieldset className="border border-[var(--color-border)] rounded-lg p-3 space-y-2">
                        <legend className="text-sm font-semibold px-1">Opérations</legend>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <FormInput
                                label="Boîte"
                                value={recordForm.data.gearbox_status}
                                onChange={(e) => recordForm.setData('gearbox_status', e.target.value)}
                            />
                            <FormInput
                                label="Pont"
                                value={recordForm.data.differential_status}
                                onChange={(e) => recordForm.setData('differential_status', e.target.value)}
                            />
                            <FormInput
                                label="Hydraulique"
                                value={recordForm.data.hydraulic_status}
                                onChange={(e) => recordForm.setData('hydraulic_status', e.target.value)}
                            />
                            <FormInput
                                label="Graissage"
                                value={recordForm.data.greasing_status}
                                onChange={(e) => recordForm.setData('greasing_status', e.target.value)}
                            />
                        </div>
                    </fieldset>

                    <fieldset className="border border-[var(--color-border)] rounded-lg p-3">
                        <legend className="text-sm font-semibold px-1">Filtres changés</legend>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                            <label className="flex items-center gap-2"><input type="checkbox" checked={recordForm.data.filter_oil_changed} onChange={(e) => recordForm.setData('filter_oil_changed', e.target.checked)} /> Huile</label>
                            <label className="flex items-center gap-2"><input type="checkbox" checked={recordForm.data.filter_hydraulic_changed} onChange={(e) => recordForm.setData('filter_hydraulic_changed', e.target.checked)} /> Hydraulique</label>
                            <label className="flex items-center gap-2"><input type="checkbox" checked={recordForm.data.filter_air_changed} onChange={(e) => recordForm.setData('filter_air_changed', e.target.checked)} /> Air</label>
                            <label className="flex items-center gap-2"><input type="checkbox" checked={recordForm.data.filter_fuel_changed} onChange={(e) => recordForm.setData('filter_fuel_changed', e.target.checked)} /> Carburant</label>
                        </div>
                    </fieldset>

                    {recordTruck && recordTruck.inspection_issues.length > 0 && (
                        <fieldset className="border border-[var(--color-border)] rounded-lg p-3">
                            <legend className="text-sm font-semibold px-1 flex items-center gap-2"><ShieldCheck size={14} /> Findings d'inspection résolus par cette maintenance</legend>
                            <div className="space-y-1 max-h-40 overflow-y-auto">
                                {recordTruck.inspection_issues.map((issue) => (
                                    <label key={issue.id} className="flex items-start gap-2 text-sm py-1 border-b border-[var(--color-border)] last:border-0">
                                        <input
                                            type="checkbox"
                                            checked={(recordForm.data.linked_inspection_issue_ids as number[]).includes(issue.id)}
                                            onChange={() => toggleIssueLink(issue.id)}
                                            className="mt-0.5"
                                        />
                                        <span className="flex-1">
                                            <span className="font-medium">{issue.category}</span>
                                            {' '}<Badge variant={SEVERITY_VARIANT[issue.severity] ?? 'default'}>{issue.severity}</Badge>
                                            {issue.issue_notes && <span className="block text-xs text-[var(--color-text-muted)]">{issue.issue_notes}</span>}
                                            <span className="block text-xs text-[var(--color-text-muted)]">Inspection du {issue.inspection_date}</span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </fieldset>
                    )}

                    <FormTextarea
                        label="Notes / Observations"
                        value={recordForm.data.notes}
                        onChange={(e) => recordForm.setData('notes', e.target.value)}
                        rows={2}
                    />

                    <div className="flex justify-end gap-2 mt-4">
                        <Button variant="secondary" type="button" onClick={() => setRecordTruck(null)}>Annuler</Button>
                        <Button type="submit" loading={recordForm.processing}>Enregistrer</Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
