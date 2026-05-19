import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import {
    ClipboardCheck, ShieldCheck, AlertTriangle, Wrench, Bell, Truck,
    Calendar, Route, FileEdit,
} from 'lucide-react';
import { humanizeMinutesInText } from '@/utils/theft-incident';

interface ChecklistRow {
    id: number;
    week_start_date: string | null;
    truck: string | null;
    driver: string | null;
    issues_count: number;
}
interface DriverIssueRow {
    id: number;
    category: string;
    severity: string | null;
    notes: string | null;
    positions: string | null;
    truck: string | null;
    driver: string | null;
    reported_at: string | null;
}
interface InspectionRow {
    id: number;
    inspection_date: string | null;
    truck: string | null;
    inspector: string | null;
    driver: string | null;
}
interface MaintenanceRow {
    id: number;
    matricule: string;
    counter: number;
    unit: string;
    level: string;
}
interface AlertRow {
    id: number;
    type: string;
    message: string;
    created_at: string;
}

interface Props {
    kpis: {
        my_inspections_week: number;
        inspections_this_month: number;
        trips_today: number;
        active_trucks: number;
        pending_checklists: number;
        unresolved_flagged: number;
        trucks_overdue_inspection: number;
        maintenance_overdue: number;
    };
    pendingChecklists: ChecklistRow[];
    driverFlaggedIssues: DriverIssueRow[];
    recentInspections: InspectionRow[];
    trucksNeedingInspection: { id: number; matricule: string }[];
    maintenanceOverdue: MaintenanceRow[];
    alerts: AlertRow[];
}

function SectionLabel({ children }: { children: React.ReactNode }) {
    return (
        <div className="text-xs uppercase tracking-wider font-semibold text-[var(--color-text-muted)] mt-2 mb-1">
            {children}
        </div>
    );
}

function KpiCard({ icon, label, value, sublabel, variant, href }: {
    icon: React.ReactNode; label: string; value: number | string; sublabel?: string;
    variant?: 'default' | 'success' | 'warning' | 'danger' | 'info';
    href?: string;
}) {
    const tone = {
        default: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        success: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400',
        warning: 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
        danger: 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
        info: 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
    }[variant ?? 'default'];
    const body = (
        <Card className="h-full">
            <div className="flex items-center gap-3">
                <div className={`p-2.5 rounded-lg ${tone}`}>{icon}</div>
                <div className="min-w-0">
                    <div className="text-xs text-[var(--color-text-muted)] uppercase tracking-wide">{label}</div>
                    <div className="text-2xl font-bold leading-tight">{value}</div>
                    {sublabel && <div className="text-xs text-[var(--color-text-muted)] mt-0.5">{sublabel}</div>}
                </div>
            </div>
        </Card>
    );
    return href ? <Link href={href} className="block">{body}</Link> : body;
}

const ISSUE_CATEGORY_LABEL: Record<string, string> = {
    tires: 'Pneus', brakes: 'Freins', lights: 'Feux',
    oil: 'Huile', fuel: 'Carburant', general: 'Général',
};
const SEVERITY_VARIANT: Record<string, 'default' | 'warning' | 'danger' | 'info'> = {
    minor: 'info', major: 'warning', critical: 'danger',
};
const SEVERITY_LABEL: Record<string, string> = {
    minor: 'Mineur', major: 'Majeur', critical: 'Critique',
};

const ALERT_TYPE_LABEL: Record<string, string> = {
    due_engine: 'Maintenance moteur due',
    missing_weekly: 'Checklist hebdo manquante',
    fuel_theft_suspected: 'Vol de carburant suspecté',
    weight_gap_detected: 'Écart de poids détecté',
    unauthorized_stop_detected: 'Arrêt non autorisé',
    route_deviation_detected: 'Déviation d\'itinéraire',
    off_hours_movement_detected: 'Mouvement hors heures',
    theft_incident: 'Incident de sécurité',
};

const ALERT_TYPE_VARIANT: Record<string, 'default' | 'success' | 'warning' | 'danger' | 'info' | 'muted'> = {
    due_engine: 'warning',
    missing_weekly: 'warning',
    fuel_theft_suspected: 'danger',
    weight_gap_detected: 'danger',
    unauthorized_stop_detected: 'danger',
    route_deviation_detected: 'warning',
    off_hours_movement_detected: 'warning',
    theft_incident: 'danger',
};

export default function LogisticsResponsibleDashboard({
    kpis, pendingChecklists, driverFlaggedIssues, recentInspections, trucksNeedingInspection, maintenanceOverdue, alerts,
}: Props) {
    const alertsCount = kpis.pending_checklists + kpis.unresolved_flagged + kpis.trucks_overdue_inspection + kpis.maintenance_overdue;

    return (
        <AuthenticatedLayout>
            <Head title="Tableau de bord — Logistique" />
            <div className="space-y-4">
                <div className="flex items-center gap-2">
                    <ClipboardCheck size={22} className="text-amber-500" />
                    <h1 className="text-xl font-semibold">Tableau de bord — Responsable Logistique</h1>
                    {alertsCount > 0 && (
                        <Badge variant="danger" className="ml-auto">{alertsCount} à traiter</Badge>
                    )}
                </div>

                {/* ── Activité ── */}
                <SectionLabel>Activité</SectionLabel>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <KpiCard
                        icon={<ShieldCheck size={18} />}
                        label="Cette semaine"
                        value={kpis.my_inspections_week}
                        sublabel="mes inspections"
                        variant="info"
                        href="/hse/inspections"
                    />
                    <KpiCard
                        icon={<FileEdit size={18} />}
                        label="Sur 30 jours"
                        value={kpis.inspections_this_month}
                        sublabel="inspections flotte"
                        variant="default"
                        href="/hse/inspections"
                    />
                    <KpiCard
                        icon={<Route size={18} />}
                        label="Aujourd'hui"
                        value={kpis.trips_today}
                        sublabel="rotations"
                        variant="default"
                        href="/transport_tracking"
                    />
                    <KpiCard
                        icon={<Truck size={18} />}
                        label="Camions actifs"
                        value={kpis.active_trucks}
                        variant="default"
                        href="/trucks"
                    />
                </div>

                {/* ── À traiter ── */}
                <SectionLabel>À traiter</SectionLabel>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <KpiCard
                        icon={<ClipboardCheck size={18} />}
                        label="Checklists à valider"
                        value={kpis.pending_checklists}
                        variant={kpis.pending_checklists > 0 ? 'warning' : 'success'}
                        href="/logistics/validation/checklists"
                    />
                    <KpiCard
                        icon={<AlertTriangle size={18} />}
                        label="Problèmes signalés"
                        value={kpis.unresolved_flagged}
                        sublabel="non résolus"
                        variant={kpis.unresolved_flagged > 0 ? 'danger' : 'success'}
                    />
                    <KpiCard
                        icon={<Calendar size={18} />}
                        label="Camions à inspecter"
                        value={kpis.trucks_overdue_inspection}
                        sublabel="> 30 jours"
                        variant={kpis.trucks_overdue_inspection > 0 ? 'warning' : 'success'}
                    />
                    <KpiCard
                        icon={<Wrench size={18} />}
                        label="Maintenance en retard"
                        value={kpis.maintenance_overdue}
                        variant={kpis.maintenance_overdue > 0 ? 'danger' : 'success'}
                        href="/maintenance"
                    />
                </div>

                {/* ── Checklists à valider ── */}
                <SectionLabel>Checklists hebdo à valider</SectionLabel>
                <Card>
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-base font-semibold flex items-center gap-2">
                            <ClipboardCheck size={16} className="text-amber-500" /> {pendingChecklists.length} checklist{pendingChecklists.length > 1 ? 's' : ''} en attente
                        </h2>
                        <Link href="/logistics/validation/checklists" className="text-sm text-[var(--color-primary)] hover:underline">Voir toutes →</Link>
                    </div>
                    {pendingChecklists.length === 0 ? (
                        <p className="text-sm text-[var(--color-text-muted)] py-3 text-center">Aucune checklist en attente.</p>
                    ) : (
                        <ul className="space-y-1">
                            {pendingChecklists.map((c) => (
                                <li key={c.id} className="flex items-center justify-between border-b border-[var(--color-border)] py-1.5 last:border-0">
                                    <div className="min-w-0">
                                        <div className="text-sm font-medium truncate">{c.truck ?? '—'} <span className="text-[var(--color-text-muted)] font-normal">· {c.driver ?? '—'}</span></div>
                                        <div className="text-xs text-[var(--color-text-muted)]">Semaine du {c.week_start_date ?? '—'}</div>
                                    </div>
                                    <div className="flex items-center gap-2 shrink-0">
                                        {c.issues_count > 0 && <Badge variant="warning">{c.issues_count} pb</Badge>}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                {/* ── Problèmes signalés par les chauffeurs ── */}
                <SectionLabel>Signalements chauffeurs</SectionLabel>
                <Card>
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-base font-semibold flex items-center gap-2">
                            <AlertTriangle size={16} className="text-red-500" /> {driverFlaggedIssues.length} problème{driverFlaggedIssues.length > 1 ? 's' : ''} à résoudre
                        </h2>
                    </div>
                    {driverFlaggedIssues.length === 0 ? (
                        <p className="text-sm text-[var(--color-text-muted)] py-3 text-center">Aucun problème en attente.</p>
                    ) : (
                        <ul className="space-y-1">
                            {driverFlaggedIssues.map((iss) => (
                                <li key={iss.id} className="flex items-center justify-between gap-3 border-b border-[var(--color-border)] py-2 last:border-0">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2 mb-0.5">
                                            <Badge variant="muted">{ISSUE_CATEGORY_LABEL[iss.category] ?? iss.category}</Badge>
                                            {iss.severity && (
                                                <Badge variant={SEVERITY_VARIANT[iss.severity] ?? 'default'}>{SEVERITY_LABEL[iss.severity] ?? iss.severity}</Badge>
                                            )}
                                            <span className="text-sm font-medium truncate">{iss.truck ?? '—'}</span>
                                            <span className="text-xs text-[var(--color-text-muted)] truncate">· {iss.driver ?? '—'}</span>
                                        </div>
                                        {iss.notes && <div className="text-xs text-[var(--color-text-secondary)] truncate" title={iss.notes}>{iss.notes}</div>}
                                    </div>
                                    <span className="text-xs text-[var(--color-text-muted)] shrink-0 whitespace-nowrap">{iss.reported_at ?? '—'}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                {/* ── Inspections récentes ── */}
                <SectionLabel>Inspections récentes</SectionLabel>
                <Card>
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-base font-semibold flex items-center gap-2">
                            <ShieldCheck size={16} className="text-emerald-500" /> Dernières inspections de la flotte
                        </h2>
                        <Link href="/hse/inspections" className="text-sm text-[var(--color-primary)] hover:underline">Voir toutes →</Link>
                    </div>
                    {recentInspections.length === 0 ? (
                        <p className="text-sm text-[var(--color-text-muted)] py-3 text-center">Aucune inspection enregistrée.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs uppercase text-[var(--color-text-muted)] border-b border-[var(--color-border)]">
                                        <th className="py-2 pr-2">Date</th>
                                        <th className="py-2 pr-2">Camion</th>
                                        <th className="py-2 pr-2">Conducteur</th>
                                        <th className="py-2 pr-2">Inspecteur</th>
                                        <th className="py-2 pr-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentInspections.map((i) => (
                                        <tr key={i.id} className="border-b border-[var(--color-border)] last:border-0">
                                            <td className="py-2 pr-2 whitespace-nowrap">{i.inspection_date}</td>
                                            <td className="py-2 pr-2 font-medium">{i.truck ?? '—'}</td>
                                            <td className="py-2 pr-2">{i.driver ?? '—'}</td>
                                            <td className="py-2 pr-2">{i.inspector ?? '—'}</td>
                                            <td className="py-2 pr-2 text-right">
                                                <Link href={`/hse/inspections/${i.id}`} className="text-[var(--color-primary)] hover:underline text-xs">Voir</Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                {/* ── Listes de suivi ── */}
                <SectionLabel>Listes de suivi</SectionLabel>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Card>
                        <div className="flex items-center justify-between mb-3">
                            <h2 className="text-sm font-semibold flex items-center gap-2">
                                <Calendar size={14} className="text-amber-500" /> Camions à inspecter
                            </h2>
                        </div>
                        {trucksNeedingInspection.length === 0 ? (
                            <p className="text-xs text-[var(--color-text-muted)] py-3 text-center">Tous inspectés sous 30 j.</p>
                        ) : (
                            <ul className="space-y-0.5">
                                {trucksNeedingInspection.map((t) => (
                                    <li key={t.id} className="flex items-center justify-between border-b border-[var(--color-border)] py-1.5 last:border-0">
                                        <span className="text-sm font-medium">{t.matricule}</span>
                                        <Link href={`/trucks/${t.id}/show-page`} className="text-xs text-[var(--color-primary)] hover:underline">Détails</Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    <Card>
                        <div className="flex items-center justify-between mb-3">
                            <h2 className="text-sm font-semibold flex items-center gap-2">
                                <Wrench size={14} className="text-amber-500" /> Maintenance en retard
                            </h2>
                            <Link href="/maintenance" className="text-xs text-[var(--color-primary)] hover:underline">Tout →</Link>
                        </div>
                        {maintenanceOverdue.length === 0 ? (
                            <p className="text-xs text-[var(--color-text-muted)] py-3 text-center">Aucun camion en retard.</p>
                        ) : (
                            <ul className="space-y-0.5">
                                {maintenanceOverdue.map((t) => (
                                    <li key={t.id} className="flex items-center justify-between border-b border-[var(--color-border)] py-1.5 last:border-0">
                                        <div className="min-w-0">
                                            <div className="text-sm font-medium truncate">{t.matricule}</div>
                                            <div className="text-xs text-[var(--color-text-muted)]">{Number(t.counter).toLocaleString('fr-FR')} {t.unit}</div>
                                        </div>
                                        <Badge variant={t.level === 'red' ? 'danger' : t.level === 'yellow' ? 'warning' : 'success'}>
                                            {t.level === 'red' ? 'Urgent' : t.level === 'yellow' ? 'Bientôt' : 'OK'}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>

                {/* ── Alertes système ── */}
                {alerts.length > 0 && (
                    <>
                        <SectionLabel>Alertes système</SectionLabel>
                        <Card>
                            <div className="flex items-center gap-2 mb-3">
                                <Bell size={16} className="text-amber-500" />
                                <h2 className="text-base font-semibold">{alerts.length} alerte{alerts.length > 1 ? 's' : ''} non lue{alerts.length > 1 ? 's' : ''}</h2>
                            </div>
                            <ul className="space-y-1">
                                {alerts.map((a) => {
                                    const niceMessage = humanizeMinutesInText(a.message);
                                    return (
                                        <li key={a.id} className="flex items-start justify-between border-b border-[var(--color-border)] py-2 last:border-0 gap-3">
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2 mb-0.5">
                                                    <Badge variant={ALERT_TYPE_VARIANT[a.type] ?? 'muted'}>
                                                        {ALERT_TYPE_LABEL[a.type] ?? a.type}
                                                    </Badge>
                                                </div>
                                                <p className="text-sm text-[var(--color-text)] truncate" title={niceMessage}>{niceMessage}</p>
                                            </div>
                                            <span className="text-xs text-[var(--color-text-muted)] shrink-0 whitespace-nowrap mt-0.5">{a.created_at}</span>
                                        </li>
                                    );
                                })}
                            </ul>
                        </Card>
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
