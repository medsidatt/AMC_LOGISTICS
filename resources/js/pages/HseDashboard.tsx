import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import { ShieldCheck, ClipboardList, Calendar, AlertTriangle, Wrench, Truck } from 'lucide-react';

interface RecentInspection {
    id: number;
    inspection_date: string | null;
    truck: string | null;
    inspector: string | null;
    driver: string | null;
    project: string | null;
    vehicle_photo_url: string | null;
}

interface MaintenanceOverdueTruck {
    id: number;
    matricule: string;
    counter: number;
    unit: string;
    level: string;
}

interface Props {
    kpis: {
        inspections_this_week: number;
        inspections_this_month: number;
        trucks_overdue_inspection: number;
        maintenance_overdue: number;
        active_trucks: number;
    };
    recentInspections: RecentInspection[];
    trucksNeedingInspection: { id: number; matricule: string }[];
    maintenanceOverdue: MaintenanceOverdueTruck[];
}

function KpiCard({
    icon, label, value, sublabel, variant, href,
}: {
    icon: React.ReactNode;
    label: string;
    value: number | string;
    sublabel?: string;
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

function SectionLabel({ children }: { children: React.ReactNode }) {
    return (
        <div className="text-xs uppercase tracking-wider font-semibold text-[var(--color-text-muted)] mt-2 mb-1">
            {children}
        </div>
    );
}

export default function HseDashboard({ kpis, recentInspections, trucksNeedingInspection, maintenanceOverdue }: Props) {
    const alertsCount =
        kpis.trucks_overdue_inspection +
        kpis.maintenance_overdue;

    return (
        <AuthenticatedLayout>
            <Head title="Tableau de bord HSE" />
            <div className="space-y-4">
                <div className="flex items-center gap-2">
                    <ShieldCheck size={22} className="text-emerald-500" />
                    <h1 className="text-xl font-semibold">Tableau de bord HSE</h1>
                    {alertsCount > 0 && (
                        <Badge variant="danger" className="ml-auto">{alertsCount} alerte{alertsCount > 1 ? 's' : ''}</Badge>
                    )}
                </div>

                {/* ── Activité ── */}
                <SectionLabel>Activité</SectionLabel>
                <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <KpiCard
                        icon={<Calendar size={18} />}
                        label="Inspections cette semaine"
                        value={kpis.inspections_this_week}
                        variant="info"
                        href="/hse/inspections"
                    />
                    <KpiCard
                        icon={<ClipboardList size={18} />}
                        label="Inspections 30 jours"
                        value={kpis.inspections_this_month}
                        variant="default"
                        href="/hse/inspections"
                    />
                    <KpiCard
                        icon={<Truck size={18} />}
                        label="Camions actifs"
                        value={kpis.active_trucks}
                        variant="default"
                        href="/trucks"
                    />
                </div>

                {/* ── Alertes ── */}
                <SectionLabel>Alertes &amp; non-conformités</SectionLabel>
                <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <KpiCard
                        icon={<AlertTriangle size={18} />}
                        label="Camions à inspecter"
                        value={kpis.trucks_overdue_inspection}
                        sublabel="aucune inspection > 30 j"
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

                {/* ── Inspections récentes ── */}
                <SectionLabel>Inspections récentes</SectionLabel>
                <Card>
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-base font-semibold flex items-center gap-2">
                            <ClipboardList size={16} className="text-emerald-500" /> 8 dernières inspections
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
                                        <th className="py-2 pr-2"></th>
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
                                            <td className="py-2 pr-2 w-12">
                                                {i.vehicle_photo_url ? (
                                                    <a href={i.vehicle_photo_url} target="_blank" rel="noopener noreferrer">
                                                        <img src={i.vehicle_photo_url} alt="" className="w-10 h-8 object-cover rounded border border-[var(--color-border)] cursor-zoom-in hover:opacity-90 transition" />
                                                    </a>
                                                ) : (
                                                    <span className="text-[var(--color-text-muted)] text-xs">—</span>
                                                )}
                                            </td>
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

                {/* ── 3 listes d'alerte côte à côte ── */}
                <SectionLabel>Listes de suivi</SectionLabel>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Card>
                        <h2 className="text-sm font-semibold flex items-center gap-2 mb-3">
                            <AlertTriangle size={14} className="text-amber-500" /> Camions à inspecter
                        </h2>
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
            </div>
        </AuthenticatedLayout>
    );
}
