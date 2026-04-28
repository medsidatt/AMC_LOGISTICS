import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import { ClipboardCheck, ShieldCheck, AlertTriangle, Wrench, Bell } from 'lucide-react';

interface ChecklistRow {
    id: number;
    week_start_date: string | null;
    truck: string | null;
    driver: string | null;
    issues_count: number;
}
interface InspectionRow {
    id: number;
    inspection_date: string | null;
    truck: string | null;
    inspector: string | null;
    category: string;
    critical_count: number;
}
interface AlertRow {
    id: number;
    type: string;
    message: string;
    created_at: string;
}

interface Props {
    kpis: {
        pending_checklists: number;
        pending_inspections: number;
        unresolved_flagged: number;
        unresolved_inspection_flagged: number;
        due_engine_trucks: number;
    };
    nextChecklists: ChecklistRow[];
    nextInspections: InspectionRow[];
    alerts: AlertRow[];
}

function Kpi({ icon, label, value, href, variant }: { icon: React.ReactNode; label: string; value: number; href?: string; variant?: string }) {
    const inner = (
        <Card hoverable={!!href}>
            <div className="flex items-center gap-3">
                <div className={`p-2 rounded-lg ${variant === 'danger' ? 'bg-red-100 text-red-600' : variant === 'warning' ? 'bg-amber-100 text-amber-600' : 'bg-slate-100 text-slate-600'}`}>
                    {icon}
                </div>
                <div>
                    <div className="text-xs text-[var(--color-text-muted)] uppercase">{label}</div>
                    <div className="text-2xl font-bold">{value}</div>
                </div>
            </div>
        </Card>
    );
    return href ? <Link href={href}>{inner}</Link> : inner;
}

export default function LogisticsResponsibleDashboard({ kpis, nextChecklists, nextInspections, alerts }: Props) {
    return (
        <AuthenticatedLayout>
            <Head title="Tableau de bord Logistique" />
            <div className="space-y-4">
                <div className="flex items-center gap-2">
                    <ClipboardCheck size={22} className="text-amber-500" />
                    <h1 className="text-xl font-semibold">Tableau de bord — Responsable Logistique</h1>
                </div>

                <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <Kpi icon={<ClipboardCheck size={18} />} label="Checklists à valider" value={kpis.pending_checklists} href={'/logistics/validation/checklists'} variant="warning" />
                    <Kpi icon={<ShieldCheck size={18} />} label="Inspections à valider" value={kpis.pending_inspections} href={'/logistics/validation/inspections'} variant="warning" />
                    <Kpi icon={<AlertTriangle size={18} />} label="Issues hebdo non résolues" value={kpis.unresolved_flagged} variant="danger" />
                    <Kpi icon={<AlertTriangle size={18} />} label="Issues inspection non résolues" value={kpis.unresolved_inspection_flagged} variant="danger" />
                    <Kpi icon={<Wrench size={18} />} label="Maintenance moteur due" value={kpis.due_engine_trucks} variant="warning" />
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <Card>
                        <div className="flex items-center justify-between mb-3">
                            <h2 className="text-lg font-semibold">Prochaines checklists hebdo</h2>
                            <Link href={'/logistics/validation/checklists'} className="text-sm text-[var(--color-primary)] hover:underline">Voir tout →</Link>
                        </div>
                        {nextChecklists.length === 0 ? (
                            <p className="text-sm text-[var(--color-text-muted)]">Aucune checklist en attente.</p>
                        ) : (
                            <ul className="space-y-1 text-sm">
                                {nextChecklists.map((c) => (
                                    <li key={c.id} className="flex justify-between border-b border-[var(--color-border)] py-1">
                                        <span><strong>{c.truck ?? '—'}</strong> — {c.driver ?? '—'}</span>
                                        <span className="flex items-center gap-2 text-[var(--color-text-muted)]">
                                            {c.week_start_date}
                                            {c.issues_count > 0 && <Badge variant="warning">{c.issues_count}</Badge>}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    <Card>
                        <div className="flex items-center justify-between mb-3">
                            <h2 className="text-lg font-semibold">Prochaines inspections HSE</h2>
                            <Link href={'/logistics/validation/inspections'} className="text-sm text-[var(--color-primary)] hover:underline">Voir tout →</Link>
                        </div>
                        {nextInspections.length === 0 ? (
                            <p className="text-sm text-[var(--color-text-muted)]">Aucune inspection en attente.</p>
                        ) : (
                            <ul className="space-y-1 text-sm">
                                {nextInspections.map((i) => (
                                    <li key={i.id} className="flex justify-between border-b border-[var(--color-border)] py-1">
                                        <span><strong>{i.truck ?? '—'}</strong> — {i.inspector ?? '—'} ({i.category})</span>
                                        <span className="flex items-center gap-2 text-[var(--color-text-muted)]">
                                            {i.inspection_date}
                                            {i.critical_count > 0 && <Badge variant="danger">{i.critical_count} critique{i.critical_count > 1 ? 's' : ''}</Badge>}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>

                {alerts.length > 0 && (
                    <Card>
                        <div className="flex items-center gap-2 mb-3">
                            <Bell size={18} className="text-amber-500" />
                            <h2 className="text-lg font-semibold">Alertes récentes</h2>
                        </div>
                        <ul className="space-y-1 text-sm">
                            {alerts.map((a) => (
                                <li key={a.id} className="flex justify-between border-b border-[var(--color-border)] py-1">
                                    <span><Badge>{a.type}</Badge> {a.message}</span>
                                    <span className="text-[var(--color-text-muted)]">{a.created_at}</span>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
