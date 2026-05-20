import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import FormSelect from '@/components/ui/FormSelect';
import FormInput from '@/components/ui/FormInput';
import Pagination from '@/components/ui/Pagination';
import Button from '@/components/ui/Button';
import Modal from '@/components/ui/Modal';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { History as HistoryIcon, FileText, UserPlus, CheckCircle2 } from 'lucide-react';
import MaintenanceTabs from '@/components/maintenance/MaintenanceTabs';

type MaintenanceStatus = 'pending' | 'assigned' | 'completed' | 'approved';

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
    status: MaintenanceStatus;
    assigned_by: string | null;
    assigned_at: string | null;
    approved_by: string | null;
    approved_at: string | null;
}

interface Props {
    maintenances: { data: MaintenanceRecord[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    trucks: { id: number; matricule: string }[];
    maintenanceTypes: { value: string; label: string }[];
    filters: Record<string, string>;
    canAssign: boolean;
    canApprove: boolean;
    currentUserName: string;
}

function FiltersSummary({ m }: { m: MaintenanceRecord }) {
    const flags: string[] = [];
    if (m.filter_oil_changed) flags.push('Huile');
    if (m.filter_hydraulic_changed) flags.push('Hyd.');
    if (m.filter_air_changed) flags.push('Air');
    if (m.filter_fuel_changed) flags.push('Carb.');
    return <>{flags.length === 0 ? '-' : flags.join(', ')}</>;
}

const STATUS_LABEL: Record<MaintenanceStatus, string> = {
    pending: 'En attente',
    assigned: 'Assignée',
    completed: 'Terminée',
    approved: 'Approuvée',
};

const STATUS_CLASS: Record<MaintenanceStatus, string> = {
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    assigned: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    completed: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
    approved: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
};

function StatusPill({ status }: { status: MaintenanceStatus }) {
    return (
        <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-semibold ${STATUS_CLASS[status]}`}>
            {STATUS_LABEL[status]}
        </span>
    );
}

export default function MaintenanceHistory({ maintenances, trucks, maintenanceTypes, filters, canAssign, canApprove, currentUserName }: Props) {
    const truckOpts = trucks.map((t) => ({ value: t.id, label: t.matricule }));

    const applyFilter = (key: string, value: string | number | null) => {
        const newFilters = { ...filters, [key]: value ? String(value) : '' };
        Object.keys(newFilters).forEach((k) => { if (!newFilters[k]) delete newFilters[k]; });
        router.get('/maintenance/history', newFilters, { preserveState: true, preserveScroll: true });
    };

    const [assignTarget, setAssignTarget] = useState<MaintenanceRecord | null>(null);
    const [signatureName, setSignatureName] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [approveTarget, setApproveTarget] = useState<MaintenanceRecord | null>(null);

    const openAssign = (m: MaintenanceRecord) => {
        setAssignTarget(m);
        setSignatureName(currentUserName);
    };

    const closeAssign = () => {
        setAssignTarget(null);
        setSignatureName('');
    };

    const submitAssign = () => {
        if (!assignTarget || !signatureName.trim()) return;
        setSubmitting(true);
        router.post(`/maintenance/${assignTarget.id}/assign`, { signature_name: signatureName.trim() }, {
            preserveScroll: true,
            onFinish: () => {
                setSubmitting(false);
                closeAssign();
            },
        });
    };

    const submitApprove = () => {
        if (!approveTarget) return;
        router.post(`/maintenance/${approveTarget.id}/approve`, {}, {
            preserveScroll: true,
            onFinish: () => setApproveTarget(null),
        });
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
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Statut</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Assignée par</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Signée par</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--color-border)]">
                            {maintenances.data.length === 0 ? (
                                <tr><td colSpan={11} className="px-4 py-8 text-center text-[var(--color-text-muted)]">
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
                                    <td className="px-4 py-3"><StatusPill status={m.status} /></td>
                                    <td className="px-4 py-3 text-[var(--color-text-secondary)]">
                                        {m.assigned_by ? (
                                            <span title={m.assigned_at ?? ''}>{m.assigned_by}</span>
                                        ) : '-'}
                                    </td>
                                    <td className="px-4 py-3 text-[var(--color-text-secondary)]">
                                        {m.approved_by ? (
                                            <span title={m.approved_at ?? ''}>{m.approved_by}</span>
                                        ) : '-'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <a
                                                href={`/maintenance/${m.id}/pdf`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1 text-xs px-2 py-1 rounded border border-[var(--color-border)] hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]"
                                                title="Télécharger le PDF"
                                            >
                                                <FileText size={14} /> PDF
                                            </a>
                                            {canAssign && m.status === 'pending' && (
                                                <Button size="sm" variant="secondary" icon={<UserPlus size={14} />} onClick={() => openAssign(m)}>
                                                    Assigner
                                                </Button>
                                            )}
                                            {canApprove && (m.status === 'assigned' || m.status === 'completed') && (
                                                <Button size="sm" variant="primary" icon={<CheckCircle2 size={14} />} onClick={() => setApproveTarget(m)}>
                                                    Approuver
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={maintenances} />
                </div>
            </Card>

            <Modal open={assignTarget !== null} onClose={closeAssign} title="Assigner la maintenance" size="md">
                <div className="space-y-4">
                    <p className="text-sm text-[var(--color-text-secondary)]">
                        Maintenance N° <b>{assignTarget?.id}</b> — Camion <b>{assignTarget?.truck}</b> du <b>{assignTarget?.maintenance_date}</b>
                    </p>
                    <FormInput
                        label="Nom préféré pour la signature"
                        value={signatureName}
                        onChange={(e) => setSignatureName(e.target.value)}
                        autoFocus
                        required
                    />
                    <p className="text-xs text-[var(--color-text-muted)] -mt-2">
                        Ce nom apparaîtra en signature manuscrite sur le PDF lorsque la maintenance sera approuvée.
                        Par défaut, votre nom de compte est proposé — modifiez-le si nécessaire.
                    </p>
                    {signatureName.trim() && (
                        <div className="text-center py-2 border border-dashed border-[var(--color-border)] rounded-lg">
                            <span style={{ fontFamily: '"Dancing Script", cursive', fontSize: '28px', color: '#111' }}>
                                {signatureName.trim()}
                            </span>
                            <p className="text-xs text-[var(--color-text-muted)] mt-1">Aperçu de la signature</p>
                        </div>
                    )}
                    <div className="flex items-center justify-end gap-2 pt-2">
                        <Button variant="ghost" onClick={closeAssign} disabled={submitting}>Annuler</Button>
                        <Button variant="primary" onClick={submitAssign} loading={submitting} disabled={!signatureName.trim()}>
                            Confirmer l'assignation
                        </Button>
                    </div>
                </div>
            </Modal>

            <ConfirmDialog
                open={approveTarget !== null}
                onClose={() => setApproveTarget(null)}
                title="Approuver et signer électroniquement"
                message="Vous allez signer électroniquement cette maintenance avec votre nom. Cette action est irréversible. Continuer ?"
                confirmLabel="Approuver et signer"
                onConfirm={submitApprove}
            />
        </AuthenticatedLayout>
    );
}
