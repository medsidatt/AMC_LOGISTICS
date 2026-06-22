import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { CalendarOff, ChevronLeft, ChevronRight, Plus, Trash2, Gauge } from 'lucide-react';
import { clsx } from 'clsx';

interface TruckRow {
    truck_id: number;
    matricule: string;
    operational_days: number;
    lost_days: number;
    availability_pct: number | null;
    available_capacity: number;
    lost_capacity: number;
    source: 'windows' | 'factors';
}
interface WindowRow {
    id: number; truck: string | null; start_at: string; end_at: string;
    type: string; reason: string | null; source: string; created_by: string | null;
}
interface Props {
    period: { anchor: string; label: string; operational_days: number };
    fleet: {
        operational_capacity: number; available_capacity: number; lost_capacity: number;
        availability_pct: number | null; downtime_impact: Record<string, number>;
    };
    trucks: TruckRow[];
    windows: WindowRow[];
    truckOptions: { value: number; label: string }[];
    types: string[];
}

const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR');
const TYPE_LABEL: Record<string, string> = { REST: 'Repos', MAINTENANCE: 'Maintenance', INSPECTION: 'Inspection', BREAKDOWN: 'Panne', SHUTDOWN: 'Arrêt' };
const TYPE_VARIANT: Record<string, 'muted' | 'warning' | 'info' | 'danger'> = { REST: 'muted', MAINTENANCE: 'warning', INSPECTION: 'info', BREAKDOWN: 'danger', SHUTDOWN: 'danger' };

const pctColor = (pct: number | null) =>
    pct == null ? 'text-[var(--color-text-muted)]'
        : pct >= 90 ? 'text-emerald-600 dark:text-emerald-400'
            : pct >= 75 ? 'text-amber-600 dark:text-amber-400'
                : 'text-red-600 dark:text-red-400';

const shiftMonth = (iso: string, n: number) => {
    const d = new Date(iso + 'T00:00:00'); d.setDate(1); d.setMonth(d.getMonth() + n);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
};

export default function AvailabilityPage({ period, fleet, trucks, windows, truckOptions, types }: Props) {
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);
    const form = useForm({ truck_id: String(truckOptions[0]?.value ?? ''), start_at: period.anchor, end_at: period.anchor, type: 'MAINTENANCE', reason: '' });

    const goMonth = (n: number) => router.get('/logistics/availability', { month: shiftMonth(period.anchor, n) }, { preserveScroll: true });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.post('/logistics/availability/windows', { preserveScroll: true, onSuccess: () => form.reset('reason') }); };

    return (
        <AuthenticatedLayout title="Disponibilité flotte">
            <Head title="Disponibilité de la flotte" />
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-3 flex-wrap">
                    <div className="flex items-center gap-2">
                        <CalendarOff size={22} className="text-[var(--color-primary)]" />
                        <h1 className="text-xl font-semibold">Disponibilité de la flotte</h1>
                    </div>
                    <div className="flex items-center gap-2">
                        <button type="button" onClick={() => goMonth(-1)} aria-label="Mois précédent" className="p-2 rounded-lg hover:bg-[var(--color-surface-hover)] cursor-pointer"><ChevronLeft size={18} /></button>
                        <span className="text-sm font-medium capitalize min-w-[9rem] text-center">{period.label}</span>
                        <button type="button" onClick={() => goMonth(1)} aria-label="Mois suivant" className="p-2 rounded-lg hover:bg-[var(--color-surface-hover)] cursor-pointer"><ChevronRight size={18} /></button>
                    </div>
                </div>

                {/* Fleet summary */}
                <Card>
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                        <Kpi label="Capacité opérationnelle" value={`${fmt(fleet.operational_capacity)} t`} sub={`${period.operational_days} jours ouvrés`} />
                        <Kpi label="Disponible" value={`${fmt(fleet.available_capacity)} t`} subClass="text-emerald-600 dark:text-emerald-400" sub="capacité réelle" />
                        <Kpi label="Perdue" value={`${fmt(fleet.lost_capacity)} t`} subClass="text-red-600 dark:text-red-400" sub="indisponibilité" />
                        <Kpi icon={<Gauge size={14} className={pctColor(fleet.availability_pct)} />} label="Disponibilité" value={`${fleet.availability_pct ?? '—'}%`} />
                    </div>
                    {Object.keys(fleet.downtime_impact).length > 0 && (
                        <div className="flex items-center gap-2 flex-wrap mt-4 pt-4 border-t border-[var(--color-border)]">
                            <span className="text-xs uppercase tracking-wide text-[var(--color-text-muted)]">Impact par cause :</span>
                            {Object.entries(fleet.downtime_impact).map(([type, tons]) => (
                                <Badge key={type} variant={TYPE_VARIANT[type]}>{TYPE_LABEL[type]} : {fmt(tons)} t</Badge>
                            ))}
                        </div>
                    )}
                </Card>

                {/* Per-truck table */}
                <Card padding={false}>
                    <div className="px-4 pt-4 pb-2 font-semibold">Disponibilité par camion</div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)] text-[11px] uppercase tracking-wide text-[var(--color-text-secondary)]">
                                    <th className="px-4 py-3 text-left font-semibold">Camion</th>
                                    <th className="px-4 py-3 text-right font-semibold">Jours ouvrés</th>
                                    <th className="px-4 py-3 text-right font-semibold">Jours perdus</th>
                                    <th className="px-4 py-3 text-right font-semibold">Disponibilité</th>
                                    <th className="px-4 py-3 text-right font-semibold">Capacité dispo.</th>
                                    <th className="px-4 py-3 text-right font-semibold">Capacité perdue</th>
                                    <th className="px-4 py-3 text-left font-semibold">Source</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {trucks.map((t) => (
                                    <tr key={t.truck_id} className="hover:bg-[var(--color-surface-hover)]/40">
                                        <td className="px-4 py-3 font-medium">{t.matricule}</td>
                                        <td className="px-4 py-3 text-right font-mono">{t.operational_days}</td>
                                        <td className="px-4 py-3 text-right font-mono">{t.lost_days > 0 ? <span className="text-red-600 dark:text-red-400">{t.lost_days}</span> : '0'}</td>
                                        <td className={clsx('px-4 py-3 text-right font-mono font-semibold', pctColor(t.availability_pct))}>{t.availability_pct ?? '—'}%</td>
                                        <td className="px-4 py-3 text-right font-mono">{fmt(t.available_capacity)} t</td>
                                        <td className="px-4 py-3 text-right font-mono text-red-600 dark:text-red-400">{fmt(t.lost_capacity)} t</td>
                                        <td className="px-4 py-3"><Badge variant={t.source === 'windows' ? 'success' : 'muted'}>{t.source === 'windows' ? 'Réel' : 'Estimé'}</Badge></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                {/* Windows management */}
                <Card header={<span className="text-sm font-semibold">Fenêtres d'indisponibilité</span>}>
                    <form onSubmit={submit} className="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end mb-5">
                        <FormSelect label="Camion" options={truckOptions} value={form.data.truck_id} onChange={(v) => form.setData('truck_id', String(v ?? ''))} error={form.errors.truck_id} wrapperClass="mb-0" />
                        <FormInput label="Début" type="date" value={form.data.start_at} onChange={(e) => form.setData('start_at', e.target.value)} error={form.errors.start_at} wrapperClass="mb-0" required />
                        <FormInput label="Fin" type="date" value={form.data.end_at} onChange={(e) => form.setData('end_at', e.target.value)} error={form.errors.end_at} wrapperClass="mb-0" required />
                        <FormSelect label="Type" options={types.map((t) => ({ value: t, label: TYPE_LABEL[t] }))} value={form.data.type} onChange={(v) => form.setData('type', String(v ?? 'MAINTENANCE'))} error={form.errors.type} wrapperClass="mb-0" />
                        <Button type="submit" icon={<Plus size={16} />} loading={form.processing}>Ajouter</Button>
                    </form>

                    {windows.length === 0 ? (
                        <p className="text-sm text-[var(--color-text-muted)]">Aucune fenêtre sur la période.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-[var(--color-surface-hover)] text-[11px] uppercase tracking-wide text-[var(--color-text-secondary)]">
                                        <th className="px-4 py-2.5 text-left font-semibold">Camion</th>
                                        <th className="px-4 py-2.5 text-left font-semibold">Période</th>
                                        <th className="px-4 py-2.5 text-left font-semibold">Type</th>
                                        <th className="px-4 py-2.5 text-left font-semibold">Raison</th>
                                        <th className="px-4 py-2.5 text-right font-semibold w-16"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[var(--color-border)]">
                                    {windows.map((w) => (
                                        <tr key={w.id}>
                                            <td className="px-4 py-2.5 font-medium">{w.truck ?? '—'}</td>
                                            <td className="px-4 py-2.5 font-mono whitespace-nowrap">{w.start_at} → {w.end_at}</td>
                                            <td className="px-4 py-2.5"><Badge variant={TYPE_VARIANT[w.type]}>{TYPE_LABEL[w.type]}</Badge></td>
                                            <td className="px-4 py-2.5 text-[var(--color-text-secondary)]">{w.reason ?? '—'}</td>
                                            <td className="px-4 py-2.5 text-right">
                                                <button onClick={() => setDeleteUrl(`/logistics/availability/windows/${w.id}`)} title="Supprimer" className="p-1.5 rounded-lg text-[var(--color-danger)] hover:bg-[var(--color-danger)]/10 cursor-pointer"><Trash2 size={15} /></button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
            </div>

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </AuthenticatedLayout>
    );
}

function Kpi({ icon, label, value, sub, subClass }: { icon?: React.ReactNode; label: string; value: string; sub?: string; subClass?: string }) {
    return (
        <div className="rounded-xl border border-[var(--color-border)] p-4">
            <div className="flex items-center gap-1.5 text-xs uppercase tracking-wide text-[var(--color-text-muted)]">{icon}{label}</div>
            <div className="text-2xl font-bold text-[var(--color-text)] mt-1.5 tabular-nums">{value}</div>
            {sub && <div className={clsx('text-xs mt-0.5', subClass ?? 'text-[var(--color-text-muted)]')}>{sub}</div>}
        </div>
    );
}
