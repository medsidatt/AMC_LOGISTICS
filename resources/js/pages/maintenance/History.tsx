import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import FormSelect from '@/components/ui/FormSelect';
import FormInput from '@/components/ui/FormInput';
import Pagination from '@/components/ui/Pagination';
import Button from '@/components/ui/Button';
import Modal from '@/components/ui/Modal';
import {
    History as HistoryIcon,
    FileText,
    PenLine,
    Truck as TruckIcon,
    Droplet,
    CheckCircle2,
    Clock,
    Filter as FilterIcon,
} from 'lucide-react';
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
    signed_by: string | null;
    approved_at: string | null;
}

interface Props {
    maintenances: { data: MaintenanceRecord[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    trucks: { id: number; matricule: string }[];
    maintenanceTypes: { value: string; label: string }[];
    filters: Record<string, string>;
    canApprove: boolean;
    currentUserName: string;
}

const STATUS_META: Record<MaintenanceStatus, { label: string; pill: string; Icon: typeof Clock }> = {
    pending:   { label: 'En attente', pill: 'bg-amber-100 text-amber-800 ring-amber-200',  Icon: Clock },
    assigned:  { label: 'En attente', pill: 'bg-amber-100 text-amber-800 ring-amber-200',  Icon: Clock },
    completed: { label: 'En attente', pill: 'bg-amber-100 text-amber-800 ring-amber-200',  Icon: Clock },
    approved:  { label: 'Signée',     pill: 'bg-emerald-100 text-emerald-800 ring-emerald-200', Icon: CheckCircle2 },
};

function StatusPill({ status }: { status: MaintenanceStatus }) {
    const m = STATUS_META[status];
    return (
        <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold ring-1 ${m.pill}`}>
            <m.Icon size={12} /> {m.label}
        </span>
    );
}

function FiltersChips({ m }: { m: MaintenanceRecord }) {
    const items: Array<[string, boolean | undefined]> = [
        ['Huile',     m.filter_oil_changed],
        ['Hyd.',      m.filter_hydraulic_changed],
        ['Air',       m.filter_air_changed],
        ['Carb.',     m.filter_fuel_changed],
    ];
    const changed = items.filter(([, on]) => on);
    if (changed.length === 0) return <span className="text-[var(--color-text-muted)]">—</span>;
    return (
        <div className="flex flex-wrap gap-1">
            {changed.map(([label]) => (
                <span key={label} className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 ring-1 ring-blue-200">
                    {label}
                </span>
            ))}
        </div>
    );
}

function formatKm(value: number | null | undefined): string {
    if (value == null) return '—';
    return Number(value).toLocaleString('fr-FR') + ' km';
}

export default function MaintenanceHistory({ maintenances, trucks, maintenanceTypes, filters, canApprove, currentUserName }: Props) {
    const truckOpts = trucks.map((t) => ({ value: t.id, label: t.matricule }));

    const applyFilter = (key: string, value: string | number | null) => {
        const newFilters = { ...filters, [key]: value ? String(value) : '' };
        Object.keys(newFilters).forEach((k) => { if (!newFilters[k]) delete newFilters[k]; });
        router.get('/maintenance/history', newFilters, { preserveState: true, preserveScroll: true });
    };

    const [signTarget, setSignTarget] = useState<MaintenanceRecord | null>(null);
    const [signatureName, setSignatureName] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const openSign = (m: MaintenanceRecord) => {
        setSignTarget(m);
        setSignatureName(currentUserName);
    };

    const closeSign = () => {
        setSignTarget(null);
        setSignatureName('');
    };

    const submitSign = () => {
        if (!signTarget || !signatureName.trim()) return;
        setSubmitting(true);
        router.post(`/maintenance/${signTarget.id}/approve`, { signature_name: signatureName.trim() }, {
            preserveScroll: true,
            onFinish: () => {
                setSubmitting(false);
                closeSign();
            },
        });
    };

    const rows = maintenances.data;

    return (
        <AuthenticatedLayout title="Historique maintenance">
            <Head title="Historique maintenance" />

            <MaintenanceTabs />

            <Card className="mb-4">
                <div className="grid sm:grid-cols-2 gap-4">
                    <FormSelect label="Camion" placeholder="Tous" options={truckOpts} value={filters.truck_id ?? null} onChange={(v) => applyFilter('truck_id', v)} wrapperClass="mb-0" />
                    <FormSelect label="Type" placeholder="Tous" options={maintenanceTypes} value={filters.maintenance_type ?? null} onChange={(v) => applyFilter('maintenance_type', v)} wrapperClass="mb-0" />
                </div>
            </Card>

            <Card padding={false}>
                <div className="hidden lg:block overflow-x-auto rounded-xl border border-[var(--color-border)]">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-[var(--color-surface-hover)] border-b border-[var(--color-border)]">
                                <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Date</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Camion</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Huile &amp; Vidange</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Filtres</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Statut</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Signée par</th>
                                <th className="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--color-border)]">
                            {rows.length === 0 ? (
                                <tr><td colSpan={7} className="px-4 py-12 text-center text-[var(--color-text-muted)]">
                                    <HistoryIcon size={32} className="mx-auto mb-2 opacity-30" />
                                    Aucune maintenance enregistrée
                                </td></tr>
                            ) : rows.map((m, idx) => (
                                <tr key={m.id} className={`hover:bg-[var(--color-surface-hover)] transition-colors ${idx % 2 ? 'bg-[var(--color-surface-hover)]/30' : ''}`}>
                                    <td className="px-4 py-3 align-top whitespace-nowrap text-[var(--color-text)]">{m.maintenance_date}</td>
                                    <td className="px-4 py-3 align-top">
                                        <div className="flex items-center gap-2">
                                            <TruckIcon size={14} className="text-[var(--color-text-muted)]" />
                                            <span className="font-semibold text-[var(--color-text)]">{m.truck}</span>
                                        </div>
                                        <div className="text-xs text-[var(--color-text-muted)] mt-0.5 ml-6 font-mono">
                                            {formatKm(m.kilometers_at_maintenance)}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 align-top">
                                        {m.oil_type_label ? (
                                            <>
                                                <div className="flex items-center gap-1.5 text-[var(--color-text)]">
                                                    <Droplet size={12} className="text-amber-600" />
                                                    <span className="font-medium">{m.oil_type_label}</span>
                                                </div>
                                                <div className="text-xs text-[var(--color-text-muted)] mt-0.5 ml-5 font-mono">
                                                    {formatKm(m.oil_change_km)} → <span className="text-[var(--color-text)] font-semibold">{formatKm(m.next_oil_change_km)}</span>
                                                </div>
                                            </>
                                        ) : (
                                            <span className="text-[var(--color-text-muted)]">—</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 align-top">
                                        <FiltersChips m={m} />
                                    </td>
                                    <td className="px-4 py-3 align-top"><StatusPill status={m.status} /></td>
                                    <td className="px-4 py-3 align-top">
                                        {m.signed_by ? (
                                            <>
                                                <div className="text-[var(--color-text)] font-medium">{m.signed_by}</div>
                                                {m.approved_at && (
                                                    <div className="text-xs text-[var(--color-text-muted)]">{m.approved_at}</div>
                                                )}
                                            </>
                                        ) : <span className="text-[var(--color-text-muted)]">—</span>}
                                    </td>
                                    <td className="px-4 py-3 align-top text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <a
                                                href={`/maintenance/${m.id}/pdf`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-lg border border-[var(--color-border)] hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]"
                                                title="Télécharger le PDF"
                                            >
                                                <FileText size={14} /> PDF
                                            </a>
                                            {canApprove && m.status !== 'approved' && (
                                                <Button size="sm" variant="primary" icon={<PenLine size={14} />} onClick={() => openSign(m)}>
                                                    Signer
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="lg:hidden p-3 space-y-3">
                    {rows.length === 0 ? (
                        <div className="text-center py-12 text-[var(--color-text-muted)]">
                            <HistoryIcon size={32} className="mx-auto mb-2 opacity-30" />
                            Aucune maintenance enregistrée
                        </div>
                    ) : rows.map((m) => (
                        <div key={m.id} className="rounded-xl border border-[var(--color-border)] p-4 space-y-3 bg-[var(--color-surface)]">
                            <div className="flex items-center justify-between gap-2">
                                <div className="flex items-center gap-2">
                                    <TruckIcon size={16} className="text-[var(--color-text-muted)]" />
                                    <span className="font-semibold text-[var(--color-text)]">{m.truck}</span>
                                </div>
                                <StatusPill status={m.status} />
                            </div>
                            <div className="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <div className="text-xs text-[var(--color-text-muted)]">Date</div>
                                    <div className="text-[var(--color-text)]">{m.maintenance_date}</div>
                                </div>
                                <div>
                                    <div className="text-xs text-[var(--color-text-muted)]">Compteur</div>
                                    <div className="text-[var(--color-text)] font-mono">{formatKm(m.kilometers_at_maintenance)}</div>
                                </div>
                                {m.oil_type_label && (
                                    <div className="col-span-2">
                                        <div className="text-xs text-[var(--color-text-muted)] flex items-center gap-1"><Droplet size={11} /> Huile</div>
                                        <div className="text-[var(--color-text)]">{m.oil_type_label}</div>
                                        <div className="text-xs text-[var(--color-text-muted)] font-mono">
                                            {formatKm(m.oil_change_km)} → <span className="text-[var(--color-text)] font-semibold">{formatKm(m.next_oil_change_km)}</span>
                                        </div>
                                    </div>
                                )}
                                <div className="col-span-2">
                                    <div className="text-xs text-[var(--color-text-muted)] flex items-center gap-1"><FilterIcon size={11} /> Filtres</div>
                                    <div className="mt-1"><FiltersChips m={m} /></div>
                                </div>
                                {m.signed_by && (
                                    <div className="col-span-2">
                                        <div className="text-xs text-[var(--color-text-muted)]">Signée par</div>
                                        <div className="text-[var(--color-text)]">{m.signed_by}</div>
                                        {m.approved_at && <div className="text-xs text-[var(--color-text-muted)]">{m.approved_at}</div>}
                                    </div>
                                )}
                            </div>
                            <div className="flex items-center justify-end gap-2 pt-2 border-t border-[var(--color-border)]">
                                <a
                                    href={`/maintenance/${m.id}/pdf`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-lg border border-[var(--color-border)] text-[var(--color-text-secondary)]"
                                >
                                    <FileText size={14} /> PDF
                                </a>
                                {canApprove && m.status !== 'approved' && (
                                    <Button size="sm" variant="primary" icon={<PenLine size={14} />} onClick={() => openSign(m)}>
                                        Signer
                                    </Button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="px-5 pb-5 pt-3">
                    <Pagination meta={maintenances} />
                </div>
            </Card>

            <Modal open={signTarget !== null} onClose={closeSign} title="Signer la maintenance" size="md">
                <div className="space-y-4">
                    <div className="rounded-lg bg-[var(--color-surface-hover)] border border-[var(--color-border)] p-3 text-sm">
                        <div className="flex items-center gap-2 mb-1">
                            <TruckIcon size={14} className="text-[var(--color-text-muted)]" />
                            <span className="font-semibold text-[var(--color-text)]">{signTarget?.truck}</span>
                            <span className="text-[var(--color-text-muted)]">— Maintenance N° {signTarget?.id}</span>
                        </div>
                        <div className="text-xs text-[var(--color-text-muted)]">Date : {signTarget?.maintenance_date}</div>
                    </div>
                    <FormInput
                        label="Nom préféré pour la signature"
                        value={signatureName}
                        onChange={(e) => setSignatureName(e.target.value)}
                        autoFocus
                        required
                    />
                    <p className="text-xs text-[var(--color-text-muted)] -mt-2">
                        Ce nom apparaîtra en signature manuscrite sur le PDF.
                        Par défaut, votre nom de compte est proposé — modifiez-le si nécessaire.
                    </p>
                    {signatureName.trim() && (
                        <div className="text-center py-3 border border-dashed border-[var(--color-border)] rounded-lg bg-[var(--color-surface)]">
                            <span style={{ fontFamily: '"Dancing Script", cursive', fontSize: '32px', color: '#111', lineHeight: 1 }}>
                                {signatureName.trim()}
                            </span>
                            <p className="text-xs text-[var(--color-text-muted)] mt-2">Aperçu de la signature</p>
                        </div>
                    )}
                    <div className="flex items-center justify-end gap-2 pt-2">
                        <Button variant="ghost" onClick={closeSign} disabled={submitting}>Annuler</Button>
                        <Button variant="primary" onClick={submitSign} loading={submitting} disabled={!signatureName.trim()} icon={<PenLine size={14} />}>
                            Signer électroniquement
                        </Button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
