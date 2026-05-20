import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import Modal from '@/components/ui/Modal';
import FormInput from '@/components/ui/FormInput';
import {
    ShieldCheck, Plus, Wrench, Eye, FileText, Search,
    Truck as TruckIcon, Image as ImageIcon, Clock, CheckCircle2,
} from 'lucide-react';

interface InspectionRow {
    id: number;
    inspection_date: string | null;
    truck: { id: number; matricule: string } | null;
    inspector: string | null;
    category: string;
    status: string;
    issues_count: number;
    validator: string | null;
    validated_at: string | null;
    vehicle_photo_url: string | null;
}

interface MaintenanceRow {
    id: number;
    maintenance_date: string | null;
    truck: { id: number; matricule: string } | null;
    kilometers_at_maintenance: number | null;
    oil_type: string | null;
    oil_change_km: number | null;
    next_oil_change_km: number | null;
    oil_quantity_liters: number | null;
    hydraulic_status: string | null;
    gearbox_status: string | null;
    differential_status: string | null;
    greasing_status: string | null;
    brake_status: string | null;
    coolant_status: string | null;
    battery_status: string | null;
    filter_oil_changed: boolean;
    filter_hydraulic_changed: boolean;
    filter_air_changed: boolean;
    filter_fuel_changed: boolean;
    notes: string | null;
    status: 'pending' | 'assigned' | 'completed' | 'approved';
    signed_by: string | null;
    approved_at: string | null;
}

interface Props {
    inspectionsByCategory: Record<string, InspectionRow[]>;
    maintenance: MaintenanceRow[];
    cutoff: string;
    options: {
        categories: Record<string, string>;
        conditions: Record<string, string>;
        oilTypes: Record<string, string>;
    };
}

function formatKm(value: number | null | undefined): string {
    if (value == null) return '—';
    return Number(value).toLocaleString('fr-FR') + ' km';
}

function MaintenanceStatusPill({ status }: { status: MaintenanceRow['status'] }) {
    const isSigned = status === 'approved';
    return (
        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold ring-1 ${
            isSigned
                ? 'bg-emerald-100 text-emerald-800 ring-emerald-200'
                : 'bg-amber-100 text-amber-800 ring-amber-200'
        }`}>
            {isSigned ? <CheckCircle2 size={11} /> : <Clock size={11} />}
            {isSigned ? 'Signée' : 'En attente'}
        </span>
    );
}

function ViewRow({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex justify-between gap-3 py-1.5 border-b border-[var(--color-border)] last:border-0 text-sm">
            <span className="text-[var(--color-text-muted)] text-xs uppercase tracking-wide font-medium">{label}</span>
            <span className="text-[var(--color-text)] font-medium text-right">{children}</span>
        </div>
    );
}

function ViewMaintenanceDetails({ m, oilTypes }: { m: MaintenanceRow; oilTypes: Record<string, string> }) {
    const filters: Array<[string, boolean]> = [
        ['Huile', m.filter_oil_changed],
        ['Hydraulique', m.filter_hydraulic_changed],
        ['Air', m.filter_air_changed],
        ['Carburant', m.filter_fuel_changed],
    ];

    return (
        <div className="space-y-4 text-sm">
            <div className="rounded-lg bg-[var(--color-surface-hover)] border border-[var(--color-border)] p-3 flex items-center justify-between flex-wrap gap-2">
                <div className="flex items-center gap-2">
                    <TruckIcon size={16} className="text-[var(--color-text-muted)]" />
                    <span className="font-semibold text-[var(--color-text)]">{m.truck?.matricule ?? '—'}</span>
                    <span className="text-[var(--color-text-muted)]">· {m.maintenance_date ?? '—'}</span>
                    <span className="text-[var(--color-text-muted)] font-mono">· {formatKm(m.kilometers_at_maintenance)}</span>
                </div>
                <MaintenanceStatusPill status={m.status} />
            </div>

            <div className="grid md:grid-cols-2 gap-4">
                <section>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-amber-500 pl-2">Huile moteur</h3>
                    <ViewRow label="Type d'huile">{m.oil_type ? (oilTypes[m.oil_type] ?? m.oil_type) : '—'}</ViewRow>
                    <ViewRow label="Quantité">{m.oil_quantity_liters != null ? `${Number(m.oil_quantity_liters).toLocaleString('fr-FR')} L` : '—'}</ViewRow>
                    <ViewRow label="Vidange effectuée à">{formatKm(m.oil_change_km)}</ViewRow>
                    <ViewRow label="Prochaine vidange à"><span className="text-red-600 font-semibold">{formatKm(m.next_oil_change_km)}</span></ViewRow>
                </section>

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

            {m.notes && (
                <section>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-gray-400 pl-2">Notes</h3>
                    <div className="rounded-lg bg-[var(--color-surface-hover)] border border-[var(--color-border)] p-3 whitespace-pre-wrap text-[var(--color-text)]">
                        {m.notes}
                    </div>
                </section>
            )}

            {m.status === 'approved' && m.signed_by && (
                <section className="rounded-lg border border-[var(--color-border)] border-l-4 border-l-red-600 bg-amber-50 p-3 sm:p-4">
                    <div className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)]">Signée par</div>
                    <div className="mt-1 text-2xl sm:text-3xl text-[var(--color-text)] break-words" style={{ fontFamily: '"Dancing Script", cursive', lineHeight: 1.1 }}>{m.signed_by}</div>
                    {m.approved_at && <div className="text-xs text-[var(--color-text-muted)] mt-2">Le {m.approved_at}</div>}
                </section>
            )}

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

export default function InspectionsIndex({ inspectionsByCategory, maintenance, options }: Props) {
    const { auth } = usePage().props as any;
    const canCreate = Array.isArray(auth?.permissions) && auth.permissions.includes('inspection-create');

    const categoryOrder = ['safety', 'compliance', 'mechanical', 'comprehensive'];
    const inspectionRows = categoryOrder.flatMap((key) => inspectionsByCategory[key] ?? []);
    const totalInspections = inspectionRows.length;

    const [inspectionSearch, setInspectionSearch] = useState('');
    const [maintenanceSearch, setMaintenanceSearch] = useState('');
    const [viewTarget, setViewTarget] = useState<MaintenanceRow | null>(null);

    const filteredInspections = inspectionRows.filter((r) => {
        const q = inspectionSearch.toLowerCase().trim();
        if (!q) return true;
        return (r.truck?.matricule ?? '').toLowerCase().includes(q)
            || (r.inspector ?? '').toLowerCase().includes(q)
            || (r.inspection_date ?? '').toLowerCase().includes(q);
    });

    const filteredMaintenance = maintenance.filter((r) => {
        const q = maintenanceSearch.toLowerCase().trim();
        if (!q) return true;
        const oilLabel = r.oil_type ? (options.oilTypes[r.oil_type] ?? r.oil_type) : '';
        return (r.truck?.matricule ?? '').toLowerCase().includes(q)
            || (r.maintenance_date ?? '').toLowerCase().includes(q)
            || oilLabel.toLowerCase().includes(q)
            || (r.notes ?? '').toLowerCase().includes(q);
    });

    return (
        <AuthenticatedLayout>
            <Head title="Inspections & Maintenance" />
            <div className="space-y-4">
                <div className="flex items-center justify-between flex-wrap gap-3">
                    <div className="flex items-center gap-2">
                        <ShieldCheck size={22} className="text-emerald-500" />
                        <h1 className="text-xl font-semibold">Inspections &amp; Maintenance</h1>
                    </div>
                    {canCreate && (
                        <Link href="/logistics/inspections/create">
                            <Button>
                                <Plus size={16} className="mr-1" />
                                Nouvelle inspection
                            </Button>
                        </Link>
                    )}
                </div>

                <Card>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-xs uppercase text-[var(--color-text-muted)] font-medium mr-2">Aperçu</span>
                        <Badge variant={maintenance.length === 0 ? 'muted' : 'warning'}>
                            Maintenance: {maintenance.length}
                        </Badge>
                        <Badge variant="muted">Total inspections: {totalInspections}</Badge>
                    </div>
                </Card>

                {/* Inspections */}
                <Card padding={false}>
                    <div className="p-4 sm:p-5 pb-2 flex items-center justify-between gap-2 flex-wrap">
                        <div className="flex items-center gap-2">
                            <ShieldCheck size={18} className="text-emerald-500" />
                            <h2 className="text-base font-semibold">Inspections</h2>
                            <Badge variant={totalInspections === 0 ? 'muted' : 'primary'}>{totalInspections}</Badge>
                        </div>
                        <div className="relative w-full sm:w-64">
                            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                            <input
                                type="text"
                                value={inspectionSearch}
                                onChange={(e) => setInspectionSearch(e.target.value)}
                                placeholder="Rechercher matricule, inspecteur…"
                                className="w-full pl-8 pr-3 py-1.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm text-[var(--color-text)] placeholder:text-[var(--color-text-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)]"
                            />
                        </div>
                    </div>

                    <div className="hidden md:block overflow-x-auto px-4 sm:px-5 pb-4 sm:pb-5">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)] border-b border-[var(--color-border)]">
                                    <th className="px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Photo</th>
                                    <th className="px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Date</th>
                                    <th className="px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Camion</th>
                                    <th className="px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Inspecteur</th>
                                    <th className="px-3 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {filteredInspections.length === 0 ? (
                                    <tr><td colSpan={5} className="px-4 py-10 text-center text-[var(--color-text-muted)]">
                                        <ShieldCheck size={28} className="mx-auto mb-2 opacity-30" />
                                        Aucune inspection
                                    </td></tr>
                                ) : filteredInspections.map((r, idx) => (
                                    <tr key={r.id} className={`hover:bg-[var(--color-surface-hover)] transition-colors ${idx % 2 ? 'bg-[var(--color-surface-hover)]/30' : ''}`}>
                                        <td className="px-3 py-2 align-middle">
                                            {r.vehicle_photo_url ? (
                                                <a href={r.vehicle_photo_url} target="_blank" rel="noopener noreferrer" title="Ouvrir la photo">
                                                    <img src={r.vehicle_photo_url} alt="Véhicule" className="w-14 h-10 object-cover rounded border border-[var(--color-border)] cursor-zoom-in hover:opacity-90 transition" />
                                                </a>
                                            ) : (
                                                <div className="w-14 h-10 rounded border border-dashed border-[var(--color-border)] flex items-center justify-center text-[var(--color-text-muted)]">
                                                    <ImageIcon size={14} />
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 align-middle whitespace-nowrap">{r.inspection_date ?? '—'}</td>
                                        <td className="px-3 py-2 align-middle font-medium">{r.truck?.matricule ?? '—'}</td>
                                        <td className="px-3 py-2 align-middle text-[var(--color-text-secondary)]">{r.inspector ?? '—'}</td>
                                        <td className="px-3 py-2 align-middle text-right">
                                            <Link href={`/hse/inspections/${r.id}`} className="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-lg border border-[var(--color-border)] hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]">
                                                <Eye size={14} /> Voir
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="md:hidden px-3 pb-4 space-y-2">
                        {filteredInspections.length === 0 ? (
                            <div className="text-center py-10 text-[var(--color-text-muted)]">
                                <ShieldCheck size={28} className="mx-auto mb-2 opacity-30" />
                                Aucune inspection
                            </div>
                        ) : filteredInspections.map((r) => (
                            <div key={r.id} className="rounded-xl border border-[var(--color-border)] p-3 bg-[var(--color-surface)] flex gap-3">
                                {r.vehicle_photo_url ? (
                                    <a href={r.vehicle_photo_url} target="_blank" rel="noopener noreferrer" className="shrink-0">
                                        <img src={r.vehicle_photo_url} alt="Véhicule" className="w-20 h-16 object-cover rounded border border-[var(--color-border)]" />
                                    </a>
                                ) : (
                                    <div className="w-20 h-16 shrink-0 rounded border border-dashed border-[var(--color-border)] flex items-center justify-center text-[var(--color-text-muted)]">
                                        <ImageIcon size={16} />
                                    </div>
                                )}
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="font-semibold text-[var(--color-text)] truncate">{r.truck?.matricule ?? '—'}</span>
                                        <span className="text-xs text-[var(--color-text-muted)] whitespace-nowrap">{r.inspection_date ?? '—'}</span>
                                    </div>
                                    <div className="text-xs text-[var(--color-text-secondary)] mt-0.5 truncate">{r.inspector ?? '—'}</div>
                                    <div className="mt-2">
                                        <Link href={`/hse/inspections/${r.id}`} className="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-lg border border-[var(--color-border)] text-[var(--color-text-secondary)]">
                                            <Eye size={14} /> Voir
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>

                {/* Maintenance */}
                <Card padding={false}>
                    <div className="p-4 sm:p-5 pb-2 flex items-center justify-between gap-2 flex-wrap">
                        <div className="flex items-center gap-2">
                            <Wrench size={18} className="text-amber-500" />
                            <h2 className="text-base font-semibold">Maintenance</h2>
                            <Badge variant={maintenance.length === 0 ? 'muted' : 'warning'}>{maintenance.length}</Badge>
                        </div>
                        <div className="relative w-full sm:w-64">
                            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                            <input
                                type="text"
                                value={maintenanceSearch}
                                onChange={(e) => setMaintenanceSearch(e.target.value)}
                                placeholder="Rechercher matricule, date…"
                                className="w-full pl-8 pr-3 py-1.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm text-[var(--color-text)] placeholder:text-[var(--color-text-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)]"
                            />
                        </div>
                    </div>

                    <div className="hidden md:block overflow-x-auto px-4 sm:px-5 pb-4 sm:pb-5">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)] border-b border-[var(--color-border)]">
                                    <th className="px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Date</th>
                                    <th className="px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Camion</th>
                                    <th className="px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Statut</th>
                                    <th className="px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Signée par</th>
                                    <th className="px-3 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {filteredMaintenance.length === 0 ? (
                                    <tr><td colSpan={5} className="px-4 py-10 text-center text-[var(--color-text-muted)]">
                                        <Wrench size={28} className="mx-auto mb-2 opacity-30" />
                                        Aucune maintenance enregistrée sur la période
                                    </td></tr>
                                ) : filteredMaintenance.map((r, idx) => (
                                    <tr key={r.id} className={`hover:bg-[var(--color-surface-hover)] transition-colors ${idx % 2 ? 'bg-[var(--color-surface-hover)]/30' : ''}`}>
                                        <td className="px-3 py-2 align-middle whitespace-nowrap">{r.maintenance_date ?? '—'}</td>
                                        <td className="px-3 py-2 align-middle">
                                            <div className="flex items-center gap-2">
                                                <TruckIcon size={14} className="text-[var(--color-text-muted)]" />
                                                <span className="font-semibold">{r.truck?.matricule ?? '—'}</span>
                                                <span className="text-xs text-[var(--color-text-muted)] font-mono">· {formatKm(r.kilometers_at_maintenance)}</span>
                                            </div>
                                        </td>
                                        <td className="px-3 py-2 align-middle"><MaintenanceStatusPill status={r.status} /></td>
                                        <td className="px-3 py-2 align-middle">
                                            {r.signed_by ? (
                                                <>
                                                    <div className="text-[var(--color-text)] font-medium">{r.signed_by}</div>
                                                    {r.approved_at && <div className="text-xs text-[var(--color-text-muted)]">{r.approved_at}</div>}
                                                </>
                                            ) : <span className="text-[var(--color-text-muted)]">—</span>}
                                        </td>
                                        <td className="px-3 py-2 align-middle text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <Button size="sm" variant="secondary" icon={<Eye size={14} />} onClick={() => setViewTarget(r)}>
                                                    Voir
                                                </Button>
                                                <a
                                                    href={`/maintenance/${r.id}/pdf`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-lg border border-[var(--color-border)] hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]"
                                                >
                                                    <FileText size={14} /> PDF
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="md:hidden px-3 pb-4 space-y-2">
                        {filteredMaintenance.length === 0 ? (
                            <div className="text-center py-10 text-[var(--color-text-muted)]">
                                <Wrench size={28} className="mx-auto mb-2 opacity-30" />
                                Aucune maintenance enregistrée
                            </div>
                        ) : filteredMaintenance.map((r) => (
                            <div key={r.id} className="rounded-xl border border-[var(--color-border)] p-3 bg-[var(--color-surface)] space-y-2">
                                <div className="flex items-center justify-between gap-2">
                                    <div className="flex items-center gap-2 min-w-0">
                                        <TruckIcon size={14} className="text-[var(--color-text-muted)] shrink-0" />
                                        <span className="font-semibold text-[var(--color-text)] truncate">{r.truck?.matricule ?? '—'}</span>
                                    </div>
                                    <MaintenanceStatusPill status={r.status} />
                                </div>
                                <div className="grid grid-cols-2 gap-2 text-xs">
                                    <div><span className="text-[var(--color-text-muted)]">Date :</span> {r.maintenance_date ?? '—'}</div>
                                    <div><span className="text-[var(--color-text-muted)]">Km :</span> <span className="font-mono">{formatKm(r.kilometers_at_maintenance)}</span></div>
                                    {r.signed_by && (
                                        <div className="col-span-2"><span className="text-[var(--color-text-muted)]">Signée par :</span> {r.signed_by}</div>
                                    )}
                                </div>
                                <div className="grid grid-cols-2 gap-2 pt-2 border-t border-[var(--color-border)]">
                                    <Button size="sm" variant="secondary" icon={<Eye size={14} />} onClick={() => setViewTarget(r)} className="w-full justify-center">
                                        Voir
                                    </Button>
                                    <a
                                        href={`/maintenance/${r.id}/pdf`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center justify-center gap-1 text-xs px-2.5 py-1.5 rounded-lg border border-[var(--color-border)] text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)]"
                                    >
                                        <FileText size={14} /> PDF
                                    </a>
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>
            </div>

            <Modal open={viewTarget !== null} onClose={() => setViewTarget(null)} title={viewTarget ? `Maintenance N° ${viewTarget.id} — ${viewTarget.truck?.matricule ?? ''}` : ''} size="xl">
                {viewTarget && <ViewMaintenanceDetails m={viewTarget} oilTypes={options.oilTypes} />}
            </Modal>
        </AuthenticatedLayout>
    );
}
