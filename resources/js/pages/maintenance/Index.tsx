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
import { Wrench, AlertTriangle, CheckCircle2, Search, ShieldCheck, Camera, Receipt, FileText } from 'lucide-react';
import MaintenanceTabs from '@/components/maintenance/MaintenanceTabs';
import CameraCapture from '@/components/inspection/CameraCapture';
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
    parts_cost: string | null;
    labor_cost: string | null;
    total_cost: string | null;
    devis_url: string | null;
    devis_name: string | null;
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
    oilIntervals: Record<string, number>;
    componentStatuses: Record<string, string>;
}

const SEVERITY_VARIANT: Record<string, 'default' | 'warning' | 'danger'> = {
    minor: 'default',
    major: 'warning',
    critical: 'danger',
};

export default function MaintenanceIndex({ trucks, counts, oilTypes, oilIntervals, componentStatuses }: Props) {
    const [filter, setFilter] = useState<'all' | 'red' | 'yellow' | 'green'>('all');
    const [search, setSearch] = useState('');
    const [recordTruck, setRecordTruck] = useState<TruckRow | null>(null);
    const [issuesTruck, setIssuesTruck] = useState<TruckRow | null>(null);
    const [costIssue, setCostIssue] = useState<InspectionIssue | null>(null);
    const { can } = usePermission();
    const canRecord = can('maintenance-create');

    const costForm = useForm<Record<string, any>>({
        parts_cost: '',
        labor_cost: '',
        devis: null as File | null,
    });

    const fcfa = (v: string | null) =>
        v == null || v === '' ? null : `${Number(v).toLocaleString('fr-FR')} FCFA`;

    const openCost = (issue: InspectionIssue) => {
        setCostIssue(issue);
        costForm.setData({
            parts_cost: issue.parts_cost ?? '',
            labor_cost: issue.labor_cost ?? '',
            devis: null,
        });
        costForm.clearErrors();
    };

    const costTotal = () => {
        const p = Number(costForm.data.parts_cost) || 0;
        const l = Number(costForm.data.labor_cost) || 0;
        return p + l;
    };

    const submitCost = (e: React.FormEvent) => {
        e.preventDefault();
        if (!costIssue) return;
        costForm.post(`/maintenance/issues/${costIssue.id}/cost`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setCostIssue(null);
                setIssuesTruck(null);
            },
        });
    };

    const statusOpts = Object.entries(componentStatuses ?? {}).map(([k, l]) => ({ value: k, label: l }));
    const oilTypeOpts = [{ value: '', label: '—' }, ...Object.entries(oilTypes).map(([k, l]) => ({ value: k, label: l }))];

    const blankForm = {
        maintenance_date: new Date().toISOString().split('T')[0],
        maintenance_type: 'general',
        notes: '',
        kilometers_at_maintenance: '',
        oil_type: '',
        oil_change_km: '',
        next_oil_change_km: '',
        oil_quantity_liters: '',
        gearbox_status: 'NORMAL',
        differential_status: 'NORMAL',
        hydraulic_status: 'NORMAL',
        greasing_status: 'NORMAL',
        brake_status: 'NORMAL',
        coolant_status: 'NORMAL',
        battery_status: 'NORMAL',
        filter_oil_changed: false,
        filter_hydraulic_changed: false,
        filter_air_changed: false,
        filter_fuel_changed: false,
        dashboard_photo: null as File | null,
        linked_inspection_issue_ids: [] as number[],
    };

    const recordForm = useForm<Record<string, any>>(blankForm);

    const filtered = trucks.filter((t) => {
        if (filter !== 'all' && t.overall_status !== filter) return false;
        if (search && !t.matricule.toLowerCase().includes(search.toLowerCase())) return false;
        return true;
    });

    const openRecord = (truck: TruckRow) => {
        setRecordTruck(truck);
        recordForm.reset();
        const currentKm = truck.total_kilometers ?? 0;
        const general = truck.profiles.find((p) => p.type === 'general') ?? truck.profiles[0];
        const interval = general?.interval_km ?? 9000;
        recordForm.setData({
            ...blankForm,
            kilometers_at_maintenance: String(currentKm),
            oil_change_km: String(currentKm),
            next_oil_change_km: currentKm > 0 ? String(Math.round(currentKm + interval)) : '',
        });
    };

    // Truck-specific interval comes from truck_maintenance_profiles.interval_km
    // (immutable per profile, defined per truck — typically 9000 km).
    // Fall back to the oil-type table only when the truck has no profile.
    const truckInterval = (truck: TruckRow | null): number | null => {
        if (!truck) return null;
        const general = truck.profiles.find((p) => p.type === 'general') ?? truck.profiles[0];
        return general?.interval_km ?? null;
    };

    const computeNextOilKm = (oilType: string, baseKm: string | number): string => {
        const base = Number(baseKm);
        if (!Number.isFinite(base) || base <= 0) return '';
        const interval = truckInterval(recordTruck) ?? oilIntervals?.[oilType] ?? 9000;
        return String(Math.round(base + interval));
    };

    const onKmChange = (val: string) => {
        recordForm.setData((data) => {
            const next: Record<string, any> = { ...data, kilometers_at_maintenance: val };
            // Mirror the maintenance km into oil_change_km when the user hasn't customised it.
            if (!data.oil_change_km || data.oil_change_km === data.kilometers_at_maintenance) {
                next.oil_change_km = val;
                next.next_oil_change_km = computeNextOilKm(data.oil_type, val);
            }
            return next;
        });
    };

    const onOilTypeChange = (val: string | number | null) => {
        const v = (val as string) ?? '';
        recordForm.setData((data) => ({
            ...data,
            oil_type: v,
            next_oil_change_km: computeNextOilKm(v, data.oil_change_km || data.kilometers_at_maintenance),
        }));
    };

    const onOilChangeKmChange = (val: string) => {
        recordForm.setData((data) => ({
            ...data,
            oil_change_km: val,
            next_oil_change_km: computeNextOilKm(data.oil_type, val),
        }));
    };

    const onDashboardCapture = (file: File) => {
        recordForm.setData('dashboard_photo', file);
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
                                                {truck.open_inspection_issues > 0 ? (
                                                    <button type="button" onClick={() => setIssuesTruck(truck)} title="Coûts / devis des findings">
                                                        <Badge variant="danger">{truck.open_inspection_issues}</Badge>
                                                    </button>
                                                ) : <span className="text-[var(--color-text-muted)]">0</span>}
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
                                            <button type="button" onClick={() => setIssuesTruck(truck)} className="col-span-2 flex items-center gap-2 text-left">
                                                <ShieldCheck size={14} className="text-red-500" />
                                                <span className="text-xs"><Badge variant="danger">{truck.open_inspection_issues}</Badge> findings d'inspection — coûts / devis</span>
                                            </button>
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

            <Modal open={!!recordTruck} onClose={() => setRecordTruck(null)} title={`Maintenance — ${recordTruck?.matricule}`} size="xl">
                <form onSubmit={submitRecord} className="space-y-4">
                    <fieldset className="border border-[var(--color-border)] rounded-lg p-3 space-y-3">
                        <legend className="text-sm font-semibold px-1">Informations générales</legend>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <FormInput
                                label="Date"
                                type="date"
                                name="maintenance_date"
                                value={recordForm.data.maintenance_date}
                                onChange={(e) => recordForm.setData('maintenance_date', e.target.value)}
                                error={recordForm.errors.maintenance_date as string | undefined}
                                required
                            />
                            <FormInput
                                label="Distance actuelle (Km au compteur)"
                                type="number"
                                name="kilometers_at_maintenance"
                                value={recordForm.data.kilometers_at_maintenance}
                                onChange={(e) => onKmChange(e.target.value)}
                                error={recordForm.errors.kilometers_at_maintenance as string | undefined}
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-[var(--color-text-secondary)] mb-1 flex items-center gap-1">
                                <Camera size={14} /> Photo du tableau de bord (preuve du kilométrage)
                            </label>
                            <CameraCapture onCapture={onDashboardCapture} error={(recordForm.errors as any)?.dashboard_photo} />
                        </div>
                    </fieldset>

                    <fieldset className="border border-[var(--color-border)] rounded-lg p-3 space-y-3">
                        <legend className="text-sm font-semibold px-1">Huile moteur</legend>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <FormSelect
                                label="Type d'huile"
                                value={recordForm.data.oil_type}
                                onChange={onOilTypeChange}
                                options={oilTypeOpts}
                            />
                            <FormInput
                                label={`Quantité (litres)${recordForm.data.oil_type ? ' *' : ''}`}
                                type="number"
                                step="0.1"
                                value={recordForm.data.oil_quantity_liters}
                                onChange={(e) => recordForm.setData('oil_quantity_liters', e.target.value)}
                                error={recordForm.errors.oil_quantity_liters as string | undefined}
                            />
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <FormInput
                                label={`Vidange effectuée à (Km)${recordForm.data.oil_type ? ' *' : ''}`}
                                type="number"
                                value={recordForm.data.oil_change_km}
                                onChange={(e) => onOilChangeKmChange(e.target.value)}
                                error={recordForm.errors.oil_change_km as string | undefined}
                            />
                            <FormInput
                                label={`Prochaine vidange à (Km) — calculée${recordForm.data.oil_type ? ' *' : ''}`}
                                type="number"
                                value={recordForm.data.next_oil_change_km}
                                onChange={(e) => recordForm.setData('next_oil_change_km', e.target.value)}
                                error={recordForm.errors.next_oil_change_km as string | undefined}
                            />
                        </div>
                    </fieldset>

                    <fieldset className="border border-[var(--color-border)] rounded-lg p-3 space-y-3">
                        <legend className="text-sm font-semibold px-1">État des organes mécaniques</legend>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                            <FormSelect label="Boîte de vitesse" value={recordForm.data.gearbox_status} onChange={(v) => recordForm.setData('gearbox_status', v)} options={statusOpts} />
                            <FormSelect label="Différentiel (pont)" value={recordForm.data.differential_status} onChange={(v) => recordForm.setData('differential_status', v)} options={statusOpts} />
                            <FormSelect label="Circuit hydraulique" value={recordForm.data.hydraulic_status} onChange={(v) => recordForm.setData('hydraulic_status', v)} options={statusOpts} />
                            <FormSelect label="Graissage" value={recordForm.data.greasing_status} onChange={(v) => recordForm.setData('greasing_status', v)} options={statusOpts} />
                            <FormSelect label="Freins" value={recordForm.data.brake_status} onChange={(v) => recordForm.setData('brake_status', v)} options={statusOpts} />
                            <FormSelect label="Liquide de refroidissement" value={recordForm.data.coolant_status} onChange={(v) => recordForm.setData('coolant_status', v)} options={statusOpts} />
                            <FormSelect label="Batterie" value={recordForm.data.battery_status} onChange={(v) => recordForm.setData('battery_status', v)} options={statusOpts} />
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

            <Modal open={!!issuesTruck} onClose={() => setIssuesTruck(null)} title={`Findings d'inspection — ${issuesTruck?.matricule ?? ''}`}>
                <div className="space-y-2 max-h-[60vh] overflow-y-auto">
                    {issuesTruck?.inspection_issues.length ? issuesTruck.inspection_issues.map((issue) => (
                        <div key={issue.id} className="rounded-lg border border-[var(--color-border)] p-3">
                            <div className="flex items-start justify-between gap-2">
                                <div className="flex-1">
                                    <span className="font-medium">{issue.category}</span>
                                    {' '}<Badge variant={SEVERITY_VARIANT[issue.severity] ?? 'default'}>{issue.severity}</Badge>
                                    {issue.issue_notes && <span className="block text-xs text-[var(--color-text-muted)]">{issue.issue_notes}</span>}
                                    <span className="block text-xs text-[var(--color-text-muted)]">Inspection du {issue.inspection_date}</span>
                                    <div className="mt-1 flex flex-wrap items-center gap-3 text-xs">
                                        {fcfa(issue.total_cost) ? (
                                            <span className="font-semibold text-[var(--color-text)]">Coût : {fcfa(issue.total_cost)}</span>
                                        ) : (
                                            <span className="text-[var(--color-text-muted)]">Aucun coût enregistré</span>
                                        )}
                                        {issue.devis_url && (
                                            <a href={issue.devis_url} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 text-[var(--color-primary)] hover:underline">
                                                <FileText size={12} /> Devis
                                            </a>
                                        )}
                                    </div>
                                </div>
                                {canRecord && (
                                    <Button size="sm" variant="secondary" type="button" onClick={() => openCost(issue)}>
                                        <Receipt size={14} className="mr-1" /> Coût / Devis
                                    </Button>
                                )}
                            </div>
                        </div>
                    )) : (
                        <p className="text-center py-8 text-[var(--color-text-muted)]">Aucun finding ouvert.</p>
                    )}
                </div>
            </Modal>

            <Modal open={!!costIssue} onClose={() => setCostIssue(null)} title={`Coût du finding — ${costIssue?.category ?? ''}`}>
                <form onSubmit={submitCost} className="space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                        <FormInput
                            label="Pièces (FCFA)"
                            type="number"
                            min="0"
                            step="0.01"
                            value={costForm.data.parts_cost}
                            onChange={(e) => costForm.setData('parts_cost', e.target.value)}
                            error={costForm.errors.parts_cost}
                        />
                        <FormInput
                            label="Main d'œuvre (FCFA)"
                            type="number"
                            min="0"
                            step="0.01"
                            value={costForm.data.labor_cost}
                            onChange={(e) => costForm.setData('labor_cost', e.target.value)}
                            error={costForm.errors.labor_cost}
                        />
                    </div>

                    <div className="text-sm font-semibold text-[var(--color-text)]">
                        Total : {costTotal().toLocaleString('fr-FR')} FCFA
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-[var(--color-text-secondary)] mb-1">Devis (PDF ou image, optionnel)</label>
                        <input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                            onChange={(e) => costForm.setData('devis', e.target.files?.[0] ?? null)}
                            className="block w-full text-sm text-[var(--color-text)] file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-[var(--color-surface-hover)] file:text-[var(--color-text-secondary)]"
                        />
                        {costForm.errors.devis && <p className="mt-1 text-xs text-red-600">{costForm.errors.devis}</p>}
                        {costIssue?.devis_url && !costForm.data.devis && (
                            <a href={costIssue.devis_url} target="_blank" rel="noopener noreferrer" className="mt-1 inline-flex items-center gap-1 text-xs text-[var(--color-primary)] hover:underline">
                                <FileText size={12} /> Devis actuel{costIssue.devis_name ? ` — ${costIssue.devis_name}` : ''}
                            </a>
                        )}
                    </div>

                    <div className="flex justify-end gap-2 mt-4">
                        <Button variant="secondary" type="button" onClick={() => setCostIssue(null)}>Annuler</Button>
                        <Button type="submit" loading={costForm.processing}>Enregistrer</Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
