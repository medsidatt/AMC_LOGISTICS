import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import PageHeader from '@/components/ui/PageHeader';
import Tabs from '@/components/ui/Tabs';
import Pagination from '@/components/ui/Pagination';
import EmptyState from '@/components/ui/EmptyState';
import FormSelect from '@/components/ui/FormSelect';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import KpiCard from '@/components/dashboard/KpiCard';
import KpiGrid from '@/components/dashboard/KpiGrid';
import MaintenanceRecordDrawer from './components/MaintenanceRecordDrawer';
import MaintenanceDetailsDrawer from './components/MaintenanceDetailsDrawer';
import TruckMaintenanceDrawer from './components/TruckMaintenanceDrawer';
import RuleDrawer from './components/RuleDrawer';
import { clsx } from 'clsx';
import { Wrench, AlertTriangle, CheckCircle2, Search, Eye, FileText, ShieldAlert, Plus, Ban, History as HistoryIcon } from 'lucide-react';
import { usePermission } from '@/hooks/usePermission';
import type { BoardTruck, MaintenanceRecord, RuleProfile, MaintenanceRefs, MaintenanceTypeOpt } from './types';

type Paginator<T> = { data: T[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };

interface Props {
    tab: 'board' | 'history' | 'rules';
    oilTypes: Record<string, string>;
    oilIntervals: Record<string, number>;
    componentStatuses: Record<string, string>;
    itemCategories: Record<string, string>;
    itemUnits: Record<string, string>;
    controlChecks: Record<string, string>;
    maintenanceTypes: MaintenanceTypeOpt[];
    trucks: any[]; // board: BoardTruck[]; history/rules: {id,matricule}[]
    counts?: { overdue: number; warning: number; ok: number };
    maintenances?: Paginator<MaintenanceRecord>;
    filters?: Record<string, string>;
    canApprove?: boolean;
    canEdit?: boolean;
    currentUserName?: string;
    profiles?: Paginator<RuleProfile>;
}

const TAB_URL: Record<string, string> = { board: '/maintenance', history: '/maintenance/history', rules: '/maintenance/rules' };

export default function MaintenanceWorkspace(props: Props) {
    const { tab, maintenanceTypes, trucks, counts, maintenances, filters = {}, canApprove = false, canEdit = false, currentUserName = '', profiles } = props;
    const refs: MaintenanceRefs = { oilTypes: props.oilTypes, oilIntervals: props.oilIntervals, componentStatuses: props.componentStatuses, itemCategories: props.itemCategories, itemUnits: props.itemUnits, controlChecks: props.controlChecks };
    const { can } = usePermission();
    const canRecord = can('maintenance-create');
    const canRule = can('maintenance-create');

    // board state
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<'all' | 'red' | 'yellow' | 'green'>('all');
    const [detailsTruck, setDetailsTruck] = useState<BoardTruck | null>(null);
    // history state
    const [detailsRecord, setDetailsRecord] = useState<MaintenanceRecord | null>(null);
    // shared record drawer
    const [recordState, setRecordState] = useState<{ mode: 'create'; truck: BoardTruck } | { mode: 'edit'; record: MaintenanceRecord } | null>(null);
    // rules state
    const [ruleOpen, setRuleOpen] = useState(false);
    const [deactivateId, setDeactivateId] = useState<number | null>(null);

    const goTab = (key: string) => router.get(TAB_URL[key], key === 'history' ? filters : {}, { preserveScroll: true });

    const statusBadge = (status?: string) => {
        const v = status === 'red' ? 'danger' : status === 'yellow' ? 'warning' : 'success';
        const l = status === 'red' ? 'Urgent' : status === 'yellow' ? 'Bientôt' : 'OK';
        return <Badge variant={v}>{l}</Badge>;
    };

    const applyHistoryFilter = (key: string, value: string | number | null) => {
        const next = { ...filters, [key]: value ? String(value) : '' };
        Object.keys(next).forEach((k) => { if (!next[k]) delete next[k]; });
        router.get('/maintenance/history', next, { preserveState: true, preserveScroll: true });
    };

    const headerActions = tab === 'rules' && canRule
        ? <Button icon={<Plus size={16} />} onClick={() => setRuleOpen(true)}>Nouvelle règle</Button>
        : undefined;

    return (
        <AuthenticatedLayout title="Maintenance">
            <Head title="Maintenance" />

            <PageHeader icon={<Wrench size={22} className="text-[var(--color-primary)]" />} title="Maintenance" actions={headerActions} />

            <Tabs
                active={tab}
                onChange={goTab}
                className="mb-4"
                tabs={[
                    { key: 'board', label: 'État du parc', icon: <Wrench size={15} /> },
                    { key: 'history', label: 'Historique', icon: <HistoryIcon size={15} /> },
                    { key: 'rules', label: 'Règles', icon: <ShieldAlert size={15} /> },
                ]}
            />

            {/* ───────── BOARD ───────── */}
            {tab === 'board' && (() => {
                const rows = (trucks as BoardTruck[]).filter((t) => {
                    if (statusFilter !== 'all' && t.overall_status !== statusFilter) return false;
                    if (search && !t.matricule.toLowerCase().includes(search.toLowerCase())) return false;
                    return true;
                });
                return (
                    <>
                        <KpiGrid>
                            <KpiCard label="Urgent" value={counts?.overdue ?? 0} icon={<AlertTriangle size={22} />} color="var(--color-danger)" />
                            <KpiCard label="À prévoir" value={counts?.warning ?? 0} icon={<Wrench size={22} />} color="var(--color-warning)" />
                            <KpiCard label="OK" value={counts?.ok ?? 0} icon={<CheckCircle2 size={22} />} color="var(--color-success)" />
                        </KpiGrid>

                        <Card className="mt-6" padding={false}>
                            <div className="p-5">
                                <div className="flex flex-wrap items-center gap-3 mb-4">
                                    <div className="relative flex-1 min-w-[200px]">
                                        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                                        <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Rechercher par matricule..."
                                            className="w-full pl-9 pr-4 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)] transition" />
                                    </div>
                                    <div className="flex gap-1">
                                        {(['all', 'red', 'yellow', 'green'] as const).map((f) => (
                                            <button key={f} onClick={() => setStatusFilter(f)} className={clsx('px-3 py-2 rounded-lg text-xs font-medium transition', statusFilter === f ? 'bg-[var(--color-primary)] text-white' : 'bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)] hover:bg-[var(--color-border)]')}>
                                                {f === 'all' ? `Tous (${(trucks as BoardTruck[]).length})` : f === 'red' ? `Urgent (${counts?.overdue ?? 0})` : f === 'yellow' ? `À prévoir (${counts?.warning ?? 0})` : `OK (${counts?.ok ?? 0})`}
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                {rows.length === 0 ? (
                                    <EmptyState icon={<Wrench size={28} />} title="Aucun camion trouvé" />
                                ) : (
                                    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                                        <table className="w-full text-sm">
                                            <thead><tr className="bg-[var(--color-surface-hover)]">
                                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Camion</th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Compteur</th>
                                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">État</th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Km restant</th>
                                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Inspection</th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Actions</th>
                                            </tr></thead>
                                            <tbody className="divide-y divide-[var(--color-border)]">
                                                {rows.map((truck) => {
                                                    const general = truck.profiles.find((p) => p.type === 'general');
                                                    return (
                                                        <tr key={truck.id} className="hover:bg-[var(--color-surface-hover)] transition-colors">
                                                            <td className="px-4 py-3 font-medium">{truck.matricule}</td>
                                                            <td className="px-4 py-3 text-right font-mono">{truck.total_kilometers?.toLocaleString('fr-FR')} km</td>
                                                            <td className="px-4 py-3 text-center">{general ? statusBadge(general.status) : <Badge variant="muted">N/A</Badge>}</td>
                                                            <td className="px-4 py-3 text-right">{general ? <span className={clsx('font-mono', general.status === 'red' ? 'text-red-600 font-bold' : general.status === 'yellow' ? 'text-amber-600' : '')}>{general.remaining?.toLocaleString('fr-FR')} km</span> : '-'}</td>
                                                            <td className="px-4 py-3 text-center">{truck.open_inspection_issues > 0 ? <Badge variant="danger">{truck.open_inspection_issues}</Badge> : <span className="text-[var(--color-text-muted)]">0</span>}</td>
                                                            <td className="px-4 py-3 text-right">
                                                                <div className="flex items-center justify-end gap-1">
                                                                    <Button size="sm" variant="secondary" icon={<Eye size={14} />} onClick={() => setDetailsTruck(truck)}>Détails</Button>
                                                                    {canRecord && <Button size="sm" icon={<Wrench size={14} />} onClick={() => setRecordState({ mode: 'create', truck })}>Maintenance</Button>}
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        </Card>
                    </>
                );
            })()}

            {/* ───────── HISTORY ───────── */}
            {tab === 'history' && maintenances && (
                <>
                    <Card className="mb-4">
                        <div className="grid sm:grid-cols-2 gap-4">
                            <FormSelect label="Camion" placeholder="Tous" options={(trucks as { id: number; matricule: string }[]).map((t) => ({ value: t.id, label: t.matricule }))} value={filters.truck_id ?? null} onChange={(v) => applyHistoryFilter('truck_id', v)} wrapperClass="mb-0" />
                            <FormSelect label="Type" placeholder="Tous" options={maintenanceTypes} value={filters.maintenance_type ?? null} onChange={(v) => applyHistoryFilter('maintenance_type', v)} wrapperClass="mb-0" />
                        </div>
                    </Card>
                    <Card padding={false}>
                        <div className="p-5">
                            {maintenances.data.length === 0 ? (
                                <EmptyState icon={<HistoryIcon size={28} />} title="Aucune maintenance enregistrée" />
                            ) : (
                                <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                                    <table className="w-full text-sm">
                                        <thead><tr className="bg-[var(--color-surface-hover)]">
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Date</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Camion</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Statut</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Signée par</th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Actions</th>
                                        </tr></thead>
                                        <tbody className="divide-y divide-[var(--color-border)]">
                                            {maintenances.data.map((m) => (
                                                <tr key={m.id} className="hover:bg-[var(--color-surface-hover)] transition-colors">
                                                    <td className="px-4 py-3 whitespace-nowrap">{m.maintenance_date}</td>
                                                    <td className="px-4 py-3 font-medium">{m.truck} <span className="text-xs text-[var(--color-text-muted)] font-mono">· {Number(m.kilometers_at_maintenance).toLocaleString('fr-FR')} km</span></td>
                                                    <td className="px-4 py-3">{m.status === 'approved' ? <Badge variant="success">Signée</Badge> : <Badge variant="warning">En attente</Badge>}</td>
                                                    <td className="px-4 py-3">{m.signed_by ?? <span className="text-[var(--color-text-muted)]">—</span>}</td>
                                                    <td className="px-4 py-3 text-right">
                                                        <div className="flex items-center justify-end gap-1">
                                                            <Button size="sm" variant="secondary" icon={<Eye size={14} />} onClick={() => setDetailsRecord(m)}>Voir</Button>
                                                            <a href={`/maintenance/${m.id}/pdf`} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-lg border border-[var(--color-border)] hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]"><FileText size={14} /> PDF</a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                        <div className="px-5 pb-5"><Pagination meta={maintenances} /></div>
                    </Card>
                </>
            )}

            {/* ───────── RULES ───────── */}
            {tab === 'rules' && profiles && (
                <Card padding={false}>
                    <div className="p-5">
                        {profiles.data.length === 0 ? (
                            <EmptyState icon={<ShieldAlert size={28} />} title="Aucune règle" description="Créez une règle d'intervalle de maintenance." />
                        ) : (
                            <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                                <table className="w-full text-sm">
                                    <thead><tr className="bg-[var(--color-surface-hover)]">
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Camion</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Type</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Intervalle</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Seuil</th>
                                        <th className="px-4 py-3 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Active</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Actions</th>
                                    </tr></thead>
                                    <tbody className="divide-y divide-[var(--color-border)]">
                                        {profiles.data.map((p) => (
                                            <tr key={p.id} className="hover:bg-[var(--color-surface-hover)] transition-colors">
                                                <td className="px-4 py-3 font-medium">{p.truck ?? '—'}</td>
                                                <td className="px-4 py-3 capitalize">{p.maintenance_type}</td>
                                                <td className="px-4 py-3 text-right font-mono">{p.interval_km?.toLocaleString('fr-FR')} km</td>
                                                <td className="px-4 py-3 text-right font-mono">{p.warning_threshold_km != null ? `${p.warning_threshold_km.toLocaleString('fr-FR')} km` : '—'}</td>
                                                <td className="px-4 py-3 text-center">{p.is_active ? <Badge variant="success">Oui</Badge> : <Badge variant="muted">Non</Badge>}</td>
                                                <td className="px-4 py-3 text-right">
                                                    {p.is_active && canRule && <Button size="sm" variant="ghost" icon={<Ban size={14} />} onClick={() => setDeactivateId(p.id)}>Désactiver</Button>}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                    <div className="px-5 pb-5"><Pagination meta={profiles} /></div>
                </Card>
            )}

            {/* ───────── DRAWERS ───────── */}
            {detailsTruck && (
                <TruckMaintenanceDrawer truck={detailsTruck} canRecord={canRecord} onRecord={() => { const t = detailsTruck; setDetailsTruck(null); setRecordState({ mode: 'create', truck: t }); }} onClose={() => setDetailsTruck(null)} />
            )}

            {detailsRecord && (
                <MaintenanceDetailsDrawer
                    record={detailsRecord} refs={refs} canEdit={canEdit} canApprove={canApprove} currentUserName={currentUserName}
                    onEdit={() => { const r = detailsRecord; setDetailsRecord(null); setRecordState({ mode: 'edit', record: r }); }}
                    onClose={() => setDetailsRecord(null)}
                />
            )}

            {recordState && (
                <MaintenanceRecordDrawer
                    key={recordState.mode === 'edit' ? `edit-${recordState.record.id}` : `create-${recordState.truck.id}`}
                    mode={recordState.mode} refs={refs}
                    truck={recordState.mode === 'create' ? recordState.truck : null}
                    record={recordState.mode === 'edit' ? recordState.record : null}
                    onClose={() => setRecordState(null)}
                />
            )}

            {ruleOpen && (
                <RuleDrawer trucks={(trucks as { id: number; matricule: string }[])} maintenanceTypes={maintenanceTypes} onClose={() => setRuleOpen(false)} />
            )}

            <ConfirmDialog
                open={deactivateId !== null}
                onClose={() => setDeactivateId(null)}
                title="Désactiver la règle ?"
                message="Cette règle d'intervalle ne sera plus utilisée pour calculer les échéances."
                confirmLabel="Désactiver"
                onConfirm={() => { if (deactivateId) router.post(`/maintenance/rules/${deactivateId}/deactivate`, {}, { preserveScroll: true, onFinish: () => setDeactivateId(null) }); }}
            />
        </AuthenticatedLayout>
    );
}
