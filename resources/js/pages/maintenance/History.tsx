import { Head, router, useForm } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import FormSelect from '@/components/ui/FormSelect';
import FormInput from '@/components/ui/FormInput';
import FormTextarea from '@/components/ui/FormTextarea';
import Pagination from '@/components/ui/Pagination';
import Button from '@/components/ui/Button';
import Modal from '@/components/ui/Modal';
import CameraCapture from '@/components/inspection/CameraCapture';
import {
    History as HistoryIcon,
    FileText,
    PenLine,
    Pencil,
    Eye,
    Truck as TruckIcon,
    CheckCircle2,
    Clock,
    Camera,
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
    oil_quantity_liters?: number | null;
    gearbox_status?: string | null;
    differential_status?: string | null;
    hydraulic_status?: string | null;
    greasing_status?: string | null;
    brake_status?: string | null;
    coolant_status?: string | null;
    battery_status?: string | null;
    filter_oil_changed?: boolean;
    filter_hydraulic_changed?: boolean;
    filter_air_changed?: boolean;
    filter_fuel_changed?: boolean;
    dashboard_photo_url?: string | null;
    status: MaintenanceStatus;
    signed_by: string | null;
    approved_at: string | null;
    truck_interval_km?: number | null;
}

interface Props {
    maintenances: { data: MaintenanceRecord[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    trucks: { id: number; matricule: string }[];
    maintenanceTypes: { value: string; label: string }[];
    filters: Record<string, string>;
    canApprove: boolean;
    canEdit: boolean;
    currentUserName: string;
    oilTypes: Record<string, string>;
    oilIntervals: Record<string, number>;
    componentStatuses: Record<string, string>;
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

function formatKm(value: number | null | undefined): string {
    if (value == null) return '—';
    return Number(value).toLocaleString('fr-FR') + ' km';
}

function ViewRow({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex justify-between gap-3 py-1.5 border-b border-[var(--color-border)] last:border-0 text-sm">
            <span className="text-[var(--color-text-muted)] text-xs uppercase tracking-wide font-medium">{label}</span>
            <span className="text-[var(--color-text)] font-medium text-right">{children}</span>
        </div>
    );
}

function ViewMaintenanceDetails({ m, oilTypes }: { m: MaintenanceRecord; oilTypes: Record<string, string> }) {
    const filters: Array<[string, boolean | undefined]> = [
        ['Huile', m.filter_oil_changed],
        ['Hydraulique', m.filter_hydraulic_changed],
        ['Air', m.filter_air_changed],
        ['Carburant', m.filter_fuel_changed],
    ];

    return (
        <div className="space-y-4 text-sm">
            {/* Summary banner */}
            <div className="rounded-lg bg-[var(--color-surface-hover)] border border-[var(--color-border)] p-3 flex items-center justify-between flex-wrap gap-2">
                <div className="flex items-center gap-2">
                    <TruckIcon size={16} className="text-[var(--color-text-muted)]" />
                    <span className="font-semibold text-[var(--color-text)]">{m.truck}</span>
                    <span className="text-[var(--color-text-muted)]">· {m.maintenance_date}</span>
                    <span className="text-[var(--color-text-muted)] font-mono">· {formatKm(m.kilometers_at_maintenance)}</span>
                </div>
                <StatusPill status={m.status} />
            </div>

            <div className="grid md:grid-cols-2 gap-4">
                {/* Oil */}
                <section>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-amber-500 pl-2">Huile moteur</h3>
                    <ViewRow label="Type d'huile">{m.oil_type ? (oilTypes[m.oil_type] ?? m.oil_type) : '—'}</ViewRow>
                    <ViewRow label="Quantité">{m.oil_quantity_liters != null ? `${Number(m.oil_quantity_liters).toLocaleString('fr-FR')} L` : '—'}</ViewRow>
                    <ViewRow label="Vidange effectuée à">{formatKm(m.oil_change_km)}</ViewRow>
                    <ViewRow label="Prochaine vidange à"><span className="text-red-600 font-semibold">{formatKm(m.next_oil_change_km)}</span></ViewRow>
                </section>

                {/* Organs */}
                <section>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-red-500 pl-2">État des organes</h3>
                    <ViewRow label="Boîte de vitesse">{m.gearbox_status ?? '—'}</ViewRow>
                    <ViewRow label="Différentiel">{m.differential_status ?? '—'}</ViewRow>
                    <ViewRow label="Hydraulique">{m.hydraulic_status ?? '—'}</ViewRow>
                    <ViewRow label="Graissage">{m.greasing_status ?? '—'}</ViewRow>
                    <ViewRow label="Freins">{m.brake_status ?? '—'}</ViewRow>
                    <ViewRow label="Refroidissement">{m.coolant_status ?? '—'}</ViewRow>
                    <ViewRow label="Batterie">{m.battery_status ?? '—'}</ViewRow>
                </section>
            </div>

            {/* Filters */}
            <section>
                <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-blue-500 pl-2">Filtres changés</h3>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
                    {filters.map(([label, on]) => (
                        <div key={label} className={`px-3 py-2 rounded-lg text-sm flex items-center justify-between ${on ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : 'bg-[var(--color-surface-hover)] text-[var(--color-text-muted)]'}`}>
                            <span>{label}</span>
                            <span className="font-bold">{on ? '✓' : '—'}</span>
                        </div>
                    ))}
                </div>
            </section>

            {/* Notes */}
            {m.notes && (
                <section>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-gray-400 pl-2">Notes</h3>
                    <div className="rounded-lg bg-[var(--color-surface-hover)] border border-[var(--color-border)] p-3 whitespace-pre-wrap text-[var(--color-text)]">
                        {m.notes}
                    </div>
                </section>
            )}

            {/* Dashboard photo */}
            {m.dashboard_photo_url && (
                <section>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-gray-400 pl-2">Photo du tableau de bord</h3>
                    <img src={m.dashboard_photo_url} alt="Tableau de bord" className="max-h-48 sm:max-h-60 w-auto rounded-lg border border-[var(--color-border)]" />
                </section>
            )}

            {/* Signature */}
            {m.status === 'approved' && m.signed_by && (
                <section className="rounded-lg border border-[var(--color-border)] border-l-4 border-l-red-600 bg-amber-50 p-3 sm:p-4">
                    <div className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)]">Signée par</div>
                    <div className="mt-1 text-2xl sm:text-3xl text-[var(--color-text)] break-words" style={{ fontFamily: '"Dancing Script", cursive', lineHeight: 1.1 }}>{m.signed_by}</div>
                    {m.approved_at && <div className="text-xs text-[var(--color-text-muted)] mt-2">Le {m.approved_at}</div>}
                </section>
            )}

            {/* Footer actions */}
            <div className="flex items-center justify-end gap-2 pt-2 border-t border-[var(--color-border)]">
                <a
                    href={`/maintenance/${m.id}/pdf`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1 text-xs px-3 py-2 rounded-lg border border-[var(--color-border)] hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]"
                >
                    <FileText size={14} /> Télécharger le PDF
                </a>
            </div>
        </div>
    );
}

export default function MaintenanceHistory({
    maintenances, trucks, maintenanceTypes, filters,
    canApprove, canEdit, currentUserName,
    oilTypes, oilIntervals, componentStatuses,
}: Props) {
    const truckOpts = trucks.map((t) => ({ value: t.id, label: t.matricule }));
    const statusOpts = Object.entries(componentStatuses ?? {}).map(([k, l]) => ({ value: k, label: l }));
    const oilTypeOpts = useMemo(() => [{ value: '', label: '—' }, ...Object.entries(oilTypes ?? {}).map(([k, l]) => ({ value: k, label: l }))], [oilTypes]);

    const applyFilter = (key: string, value: string | number | null) => {
        const newFilters = { ...filters, [key]: value ? String(value) : '' };
        Object.keys(newFilters).forEach((k) => { if (!newFilters[k]) delete newFilters[k]; });
        router.get('/maintenance/history', newFilters, { preserveState: true, preserveScroll: true });
    };

    /* ─── View modal ───────────────────────────────────────── */
    const [viewTarget, setViewTarget] = useState<MaintenanceRecord | null>(null);

    /* ─── Sign modal ───────────────────────────────────────── */
    const [signTarget, setSignTarget] = useState<MaintenanceRecord | null>(null);
    const [signatureName, setSignatureName] = useState('');
    const [signing, setSigning] = useState(false);

    const openSign = (m: MaintenanceRecord) => {
        setSignTarget(m);
        setSignatureName(currentUserName);
    };
    const closeSign = () => { setSignTarget(null); setSignatureName(''); };
    const submitSign = () => {
        if (!signTarget || !signatureName.trim()) return;
        setSigning(true);
        router.post(`/maintenance/${signTarget.id}/approve`, { signature_name: signatureName.trim() }, {
            preserveScroll: true,
            onFinish: () => { setSigning(false); closeSign(); },
        });
    };

    /* ─── Edit modal ───────────────────────────────────────── */
    const [editTarget, setEditTarget] = useState<MaintenanceRecord | null>(null);
    const editForm = useForm<Record<string, any>>({});

    const openEdit = (m: MaintenanceRecord) => {
        setEditTarget(m);
        editForm.setData({
            maintenance_date: m.maintenance_date.split('/').reverse().join('-'), // d/m/Y -> Y-m-d for <input type="date">
            kilometers_at_maintenance: m.kilometers_at_maintenance != null ? String(m.kilometers_at_maintenance) : '',
            notes: m.notes ?? '',
            oil_type: m.oil_type ?? '',
            oil_change_km: m.oil_change_km != null ? String(m.oil_change_km) : '',
            next_oil_change_km: m.next_oil_change_km != null ? String(m.next_oil_change_km) : '',
            oil_quantity_liters: m.oil_quantity_liters != null ? String(m.oil_quantity_liters) : '',
            gearbox_status: m.gearbox_status ?? 'NORMAL',
            differential_status: m.differential_status ?? 'NORMAL',
            hydraulic_status: m.hydraulic_status ?? 'NORMAL',
            greasing_status: m.greasing_status ?? 'NORMAL',
            brake_status: m.brake_status ?? 'NORMAL',
            coolant_status: m.coolant_status ?? 'NORMAL',
            battery_status: m.battery_status ?? 'NORMAL',
            filter_oil_changed: !!m.filter_oil_changed,
            filter_hydraulic_changed: !!m.filter_hydraulic_changed,
            filter_air_changed: !!m.filter_air_changed,
            filter_fuel_changed: !!m.filter_fuel_changed,
            dashboard_photo: null as File | null,
            _truck_interval_km: m.truck_interval_km ?? null,
        });
    };

    const closeEdit = () => { setEditTarget(null); editForm.reset(); editForm.clearErrors(); };

    const computeNextOilKm = (oilType: string, baseKm: string | number, truckInterval?: number | null): string => {
        const base = Number(baseKm);
        if (!Number.isFinite(base) || base <= 0) return '';
        const interval = truckInterval ?? oilIntervals?.[oilType] ?? 9000;
        return String(Math.round(base + interval));
    };

    const onEditKmChange = (val: string) => {
        editForm.setData((d) => {
            const next: Record<string, any> = { ...d, kilometers_at_maintenance: val };
            if (!d.oil_change_km || d.oil_change_km === d.kilometers_at_maintenance) {
                next.oil_change_km = val;
                next.next_oil_change_km = computeNextOilKm(d.oil_type, val, d._truck_interval_km);
            }
            return next;
        });
    };
    const onEditOilTypeChange = (val: string | number | null) => {
        const v = (val as string) ?? '';
        editForm.setData((d) => ({
            ...d,
            oil_type: v,
            next_oil_change_km: computeNextOilKm(v, d.oil_change_km || d.kilometers_at_maintenance, d._truck_interval_km),
        }));
    };
    const onEditOilChangeKmChange = (val: string) => {
        editForm.setData((d) => ({
            ...d,
            oil_change_km: val,
            next_oil_change_km: computeNextOilKm(d.oil_type, val, d._truck_interval_km),
        }));
    };
    const onEditDashboardCapture = (file: File) => { editForm.setData('dashboard_photo', file); };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editTarget) return;
        editForm.post(`/maintenance/${editTarget.id}/update`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => closeEdit(),
        });
    };

    const rows = maintenances.data;
    const oilFieldsRequired = !!editForm.data.oil_type;

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
                                <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Statut</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Signée par</th>
                                <th className="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--color-border)]">
                            {rows.length === 0 ? (
                                <tr><td colSpan={5} className="px-4 py-12 text-center text-[var(--color-text-muted)]">
                                    <HistoryIcon size={32} className="mx-auto mb-2 opacity-30" />
                                    Aucune maintenance enregistrée
                                </td></tr>
                            ) : rows.map((m, idx) => (
                                <tr key={m.id} className={`hover:bg-[var(--color-surface-hover)] transition-colors ${idx % 2 ? 'bg-[var(--color-surface-hover)]/30' : ''}`}>
                                    <td className="px-4 py-3 align-middle whitespace-nowrap text-[var(--color-text)]">{m.maintenance_date}</td>
                                    <td className="px-4 py-3 align-middle">
                                        <div className="flex items-center gap-2">
                                            <TruckIcon size={14} className="text-[var(--color-text-muted)]" />
                                            <span className="font-semibold text-[var(--color-text)]">{m.truck}</span>
                                            <span className="text-xs text-[var(--color-text-muted)] font-mono">· {formatKm(m.kilometers_at_maintenance)}</span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 align-middle"><StatusPill status={m.status} /></td>
                                    <td className="px-4 py-3 align-middle">
                                        {m.signed_by ? (
                                            <>
                                                <div className="text-[var(--color-text)] font-medium">{m.signed_by}</div>
                                                {m.approved_at && (
                                                    <div className="text-xs text-[var(--color-text-muted)]">{m.approved_at}</div>
                                                )}
                                            </>
                                        ) : <span className="text-[var(--color-text-muted)]">—</span>}
                                    </td>
                                    <td className="px-4 py-3 align-middle text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <Button size="sm" variant="secondary" icon={<Eye size={14} />} onClick={() => setViewTarget(m)}>
                                                Voir
                                            </Button>
                                            <a
                                                href={`/maintenance/${m.id}/pdf`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-lg border border-[var(--color-border)] hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]"
                                                title="Télécharger le PDF"
                                            >
                                                <FileText size={14} /> PDF
                                            </a>
                                            {canEdit && m.status !== 'approved' && (
                                                <Button size="sm" variant="secondary" icon={<Pencil size={14} />} onClick={() => openEdit(m)}>
                                                    Modifier
                                                </Button>
                                            )}
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
                                {m.signed_by && (
                                    <div className="col-span-2">
                                        <div className="text-xs text-[var(--color-text-muted)]">Signée par</div>
                                        <div className="text-[var(--color-text)]">{m.signed_by}</div>
                                        {m.approved_at && <div className="text-xs text-[var(--color-text-muted)]">{m.approved_at}</div>}
                                    </div>
                                )}
                            </div>
                            <div className="grid grid-cols-2 gap-2 pt-2 border-t border-[var(--color-border)]">
                                <Button size="sm" variant="secondary" icon={<Eye size={14} />} onClick={() => setViewTarget(m)} className="w-full justify-center">
                                    Voir
                                </Button>
                                <a
                                    href={`/maintenance/${m.id}/pdf`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center justify-center gap-1 text-xs px-2.5 py-1.5 rounded-lg border border-[var(--color-border)] text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)]"
                                >
                                    <FileText size={14} /> PDF
                                </a>
                                {canEdit && m.status !== 'approved' && (
                                    <Button size="sm" variant="secondary" icon={<Pencil size={14} />} onClick={() => openEdit(m)} className="w-full justify-center">
                                        Modifier
                                    </Button>
                                )}
                                {canApprove && m.status !== 'approved' && (
                                    <Button size="sm" variant="primary" icon={<PenLine size={14} />} onClick={() => openSign(m)} className="w-full justify-center">
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

            {/* View modal */}
            <Modal open={viewTarget !== null} onClose={() => setViewTarget(null)} title={viewTarget ? `Maintenance N° ${viewTarget.id} — ${viewTarget.truck}` : ''} size="xl">
                {viewTarget && <ViewMaintenanceDetails m={viewTarget} oilTypes={oilTypes} />}
            </Modal>

            {/* Sign modal */}
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
                            <span className="text-[24px] sm:text-[32px] break-words text-[var(--color-text)]" style={{ fontFamily: '"Dancing Script", cursive', lineHeight: 1.1 }}>
                                {signatureName.trim()}
                            </span>
                            <p className="text-xs text-[var(--color-text-muted)] mt-2">Aperçu de la signature</p>
                        </div>
                    )}
                    <div className="flex items-center justify-end gap-2 pt-2">
                        <Button variant="ghost" onClick={closeSign} disabled={signing}>Annuler</Button>
                        <Button variant="primary" onClick={submitSign} loading={signing} disabled={!signatureName.trim()} icon={<PenLine size={14} />}>
                            Signer électroniquement
                        </Button>
                    </div>
                </div>
            </Modal>

            {/* Edit modal */}
            <Modal open={editTarget !== null} onClose={closeEdit} title={`Modifier la maintenance — ${editTarget?.truck}`} size="xl">
                <form onSubmit={submitEdit} className="space-y-4">
                    <fieldset className="border border-[var(--color-border)] rounded-lg p-3 space-y-3">
                        <legend className="text-sm font-semibold px-1">Informations générales</legend>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <FormInput
                                label="Date"
                                type="date"
                                value={editForm.data.maintenance_date}
                                onChange={(e) => editForm.setData('maintenance_date', e.target.value)}
                                error={editForm.errors.maintenance_date as string}
                                required
                            />
                            <FormInput
                                label="Distance actuelle (Km au compteur)"
                                type="number"
                                value={editForm.data.kilometers_at_maintenance}
                                onChange={(e) => onEditKmChange(e.target.value)}
                                error={editForm.errors.kilometers_at_maintenance as string}
                                required
                            />
                        </div>
                        {editForm.data._truck_interval_km != null && (
                            <p className="text-xs text-[var(--color-text-muted)] -mt-2">
                                Intervalle du camion (BDD) :
                                <b> {Number(editForm.data._truck_interval_km).toLocaleString('fr-FR')} km</b>.
                                Prochaine vidange = distance actuelle + intervalle.
                            </p>
                        )}
                        <div>
                            <label className="block text-sm font-medium text-[var(--color-text-secondary)] mb-1 flex items-center gap-1">
                                <Camera size={14} /> Photo du tableau de bord (preuve du kilométrage)
                            </label>
                            <CameraCapture
                                onCapture={onEditDashboardCapture}
                                existingPhotoUrl={editTarget?.dashboard_photo_url ?? null}
                                error={editForm.errors.dashboard_photo as string | null | undefined}
                            />
                        </div>
                    </fieldset>

                    <fieldset className="border border-[var(--color-border)] rounded-lg p-3 space-y-3">
                        <legend className="text-sm font-semibold px-1">Huile moteur</legend>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <FormSelect
                                label="Type d'huile"
                                value={editForm.data.oil_type}
                                onChange={onEditOilTypeChange}
                                options={oilTypeOpts}
                            />
                            <FormInput
                                label={`Quantité (litres)${oilFieldsRequired ? ' *' : ''}`}
                                type="number"
                                step="0.1"
                                value={editForm.data.oil_quantity_liters}
                                onChange={(e) => editForm.setData('oil_quantity_liters', e.target.value)}
                                error={editForm.errors.oil_quantity_liters as string}
                            />
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <FormInput
                                label={`Vidange effectuée à (Km)${oilFieldsRequired ? ' *' : ''}`}
                                type="number"
                                value={editForm.data.oil_change_km}
                                onChange={(e) => onEditOilChangeKmChange(e.target.value)}
                                error={editForm.errors.oil_change_km as string}
                            />
                            <FormInput
                                label={`Prochaine vidange à (Km) — calculée${oilFieldsRequired ? ' *' : ''}`}
                                type="number"
                                value={editForm.data.next_oil_change_km}
                                onChange={(e) => editForm.setData('next_oil_change_km', e.target.value)}
                                error={editForm.errors.next_oil_change_km as string}
                            />
                        </div>
                    </fieldset>

                    <fieldset className="border border-[var(--color-border)] rounded-lg p-3 space-y-3">
                        <legend className="text-sm font-semibold px-1">État des organes mécaniques</legend>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                            <FormSelect label="Boîte de vitesse" value={editForm.data.gearbox_status} onChange={(v) => editForm.setData('gearbox_status', v)} options={statusOpts} />
                            <FormSelect label="Différentiel (pont)" value={editForm.data.differential_status} onChange={(v) => editForm.setData('differential_status', v)} options={statusOpts} />
                            <FormSelect label="Circuit hydraulique" value={editForm.data.hydraulic_status} onChange={(v) => editForm.setData('hydraulic_status', v)} options={statusOpts} />
                            <FormSelect label="Graissage" value={editForm.data.greasing_status} onChange={(v) => editForm.setData('greasing_status', v)} options={statusOpts} />
                            <FormSelect label="Freins" value={editForm.data.brake_status} onChange={(v) => editForm.setData('brake_status', v)} options={statusOpts} />
                            <FormSelect label="Liquide de refroidissement" value={editForm.data.coolant_status} onChange={(v) => editForm.setData('coolant_status', v)} options={statusOpts} />
                            <FormSelect label="Batterie" value={editForm.data.battery_status} onChange={(v) => editForm.setData('battery_status', v)} options={statusOpts} />
                        </div>
                    </fieldset>

                    <fieldset className="border border-[var(--color-border)] rounded-lg p-3">
                        <legend className="text-sm font-semibold px-1">Filtres changés</legend>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                            <label className="flex items-center gap-2"><input type="checkbox" checked={!!editForm.data.filter_oil_changed} onChange={(e) => editForm.setData('filter_oil_changed', e.target.checked)} /> Huile</label>
                            <label className="flex items-center gap-2"><input type="checkbox" checked={!!editForm.data.filter_hydraulic_changed} onChange={(e) => editForm.setData('filter_hydraulic_changed', e.target.checked)} /> Hydraulique</label>
                            <label className="flex items-center gap-2"><input type="checkbox" checked={!!editForm.data.filter_air_changed} onChange={(e) => editForm.setData('filter_air_changed', e.target.checked)} /> Air</label>
                            <label className="flex items-center gap-2"><input type="checkbox" checked={!!editForm.data.filter_fuel_changed} onChange={(e) => editForm.setData('filter_fuel_changed', e.target.checked)} /> Carburant</label>
                        </div>
                    </fieldset>

                    <FormTextarea
                        label="Notes / Observations"
                        value={editForm.data.notes ?? ''}
                        onChange={(e) => editForm.setData('notes', e.target.value)}
                        error={editForm.errors.notes as string}
                        rows={2}
                    />

                    <div className="flex justify-end gap-2 mt-2">
                        <Button variant="secondary" type="button" onClick={closeEdit}>Annuler</Button>
                        <Button type="submit" loading={editForm.processing} icon={<Pencil size={14} />}>
                            Enregistrer les modifications
                        </Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
