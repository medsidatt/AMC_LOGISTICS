import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import DataTable from '@/components/ui/DataTable';
import { usePolling } from '@/hooks/usePolling';
import { usePermission } from '@/hooks/usePermission';
import {
    Route, Weight, ClipboardCheck, Truck, Fuel, Gauge,
    Activity, Timer, ArrowRight, AlertTriangle, Wrench, Calendar,
} from 'lucide-react';

interface TruckInfo {
    id: number;
    matricule: string;
    total_kilometers: number;
    fuel_level: number | null;
    speed: number | null;
    movement_status: string | null;
    last_sync: string | null;
    maintenance_level: string;
}

interface Props {
    driver: { id: number; name: string; email: string } | null;
    truck: TruckInfo | null;
    weekChecklistDone: boolean;
    openIssuesCount: number;
    myTripsWeek: number;
    myTripsMonth: number;
    myTonnageMonth: number;
    recentTrips: Array<{
        id: number;
        reference: string;
        truck: string | null;
        provider: string | null;
        provider_net_weight: number | null;
        client_net_weight: number | null;
        provider_date: string | null;
        client_date: string | null;
    }>;
    checklistHistory: Array<{
        id: number;
        checklist_date: string;
        issues_count: number;
        unresolved_count: number;
    }>;
}

const MOVEMENT_LABEL: Record<string, string> = {
    moving: 'En mouvement',
    idle: 'Ralenti',
    parked: 'Stationné',
};

const MOVEMENT_VARIANT: Record<string, 'success' | 'warning' | 'muted'> = {
    moving: 'success',
    idle: 'warning',
    parked: 'muted',
};

const MAINT_LEVEL_VARIANT: Record<string, 'success' | 'warning' | 'danger'> = {
    green: 'success', yellow: 'warning', red: 'danger',
};
const MAINT_LEVEL_LABEL: Record<string, string> = {
    green: 'OK', yellow: 'Bientôt', red: 'Urgent',
};

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

export default function DriverDashboard(props: Props) {
    usePolling({ interval: 120 });
    const { isAdmin } = usePermission();

    if (!props.driver) {
        return (
            <AuthenticatedLayout title="Mon espace">
                <Head title="Mon espace" />
                <Card>
                    <div className="flex flex-col items-center py-12 text-center">
                        <div className="w-16 h-16 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mb-4">
                            <Truck size={28} className="text-amber-500" />
                        </div>
                        <h3 className="text-lg font-semibold text-[var(--color-text)] mb-2">Profil non lié</h3>
                        <p className="text-sm text-[var(--color-text-muted)] max-w-sm">
                            Votre profil conducteur n'est pas encore lié à votre compte. Contactez un administrateur pour activer votre espace conducteur.
                        </p>
                    </div>
                </Card>
            </AuthenticatedLayout>
        );
    }

    const t = props.truck;
    const alertsCount = (props.weekChecklistDone ? 0 : 1) + props.openIssuesCount + (t?.maintenance_level === 'red' ? 1 : 0);

    return (
        <AuthenticatedLayout title="Mon espace">
            <Head title="Mon espace" />

            <div className="space-y-4">
                {/* ── Driver + truck identity ── */}
                <Card>
                    <div className="flex flex-wrap items-center gap-4">
                        <div className="w-14 h-14 rounded-full bg-[var(--color-primary)]/10 flex items-center justify-center shrink-0">
                            <span className="text-xl font-bold text-[var(--color-primary)]">
                                {props.driver.name.charAt(0)}
                            </span>
                        </div>
                        <div className="flex-1 min-w-0">
                            <h2 className="text-xl font-bold text-[var(--color-text)] truncate">{props.driver.name}</h2>
                            <p className="text-sm text-[var(--color-text-muted)] truncate">{props.driver.email}</p>
                        </div>
                        {t && (
                            <Link
                                href="/drivers/my-truck"
                                className="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[var(--color-primary)]/10 text-[var(--color-primary)] font-medium text-sm hover:bg-[var(--color-primary)]/20 transition"
                            >
                                <Truck size={16} />
                                {t.matricule}
                                <ArrowRight size={14} />
                            </Link>
                        )}
                        {alertsCount > 0 && (
                            <Badge variant="danger">{alertsCount} à faire</Badge>
                        )}
                    </div>
                </Card>

                {/* ── Telemetry strip (live truck data) ── */}
                {t && (
                    <>
                        <SectionLabel>Camion en temps réel</SectionLabel>
                        <div className={`grid grid-cols-2 ${isAdmin ? 'sm:grid-cols-4' : 'sm:grid-cols-3'} gap-3`}>
                            <Card>
                                <div className="flex items-center gap-2">
                                    <Gauge size={18} className="text-blue-500 shrink-0" />
                                    <div className="min-w-0">
                                        <div className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">Compteur</div>
                                        <div className="text-sm font-bold">{Math.round(t.total_kilometers).toLocaleString('fr-FR')} km</div>
                                    </div>
                                </div>
                            </Card>
                            <Card>
                                <div className="flex items-center gap-2">
                                    <Fuel size={18} className="text-amber-500 shrink-0" />
                                    <div className="min-w-0">
                                        <div className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">Carburant</div>
                                        <div className="text-sm font-bold">{t.fuel_level !== null ? `${t.fuel_level.toFixed(0)} L` : '—'}</div>
                                    </div>
                                </div>
                            </Card>
                            <Card>
                                <div className="flex items-center gap-2">
                                    <Activity size={18} className="text-emerald-500 shrink-0" />
                                    <div className="min-w-0">
                                        <div className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">État</div>
                                        <Badge variant={MOVEMENT_VARIANT[t.movement_status ?? ''] ?? 'muted'} size="sm">
                                            {MOVEMENT_LABEL[t.movement_status ?? ''] ?? 'Inconnu'}
                                        </Badge>
                                    </div>
                                </div>
                            </Card>
                            {isAdmin && (
                                <Card>
                                    <div className="flex items-center gap-2">
                                        <Timer size={18} className="text-[var(--color-text-muted)] shrink-0" />
                                        <div className="min-w-0">
                                            <div className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">Dernière sync</div>
                                            <div className="text-xs font-medium truncate">{t.last_sync ?? '—'}</div>
                                        </div>
                                    </div>
                                </Card>
                            )}
                        </div>
                    </>
                )}

                {/* ── Activité ── */}
                <SectionLabel>Mon activité</SectionLabel>
                <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <KpiCard
                        icon={<Calendar size={18} />}
                        label="Cette semaine"
                        value={props.myTripsWeek}
                        sublabel="rotations"
                        variant="info"
                        href="/drivers/my-trips"
                    />
                    <KpiCard
                        icon={<Route size={18} />}
                        label="Ce mois"
                        value={props.myTripsMonth}
                        sublabel="rotations"
                        variant="default"
                        href="/drivers/my-trips"
                    />
                    <KpiCard
                        icon={<Weight size={18} />}
                        label="Tonnage du mois"
                        value={`${props.myTonnageMonth.toLocaleString('fr-FR', { maximumFractionDigits: 1 })} t`}
                        variant="default"
                    />
                </div>

                {/* ── Actions à faire ── */}
                <SectionLabel>À faire</SectionLabel>
                <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <KpiCard
                        icon={<ClipboardCheck size={18} />}
                        label="Checklist de la semaine"
                        value={props.weekChecklistDone ? 'Faite' : 'À faire'}
                        variant={props.weekChecklistDone ? 'success' : 'warning'}
                        href="/drivers/checklist-page"
                    />
                    <KpiCard
                        icon={<AlertTriangle size={18} />}
                        label="Problèmes non résolus"
                        value={props.openIssuesCount}
                        variant={props.openIssuesCount > 0 ? 'warning' : 'success'}
                        href="/drivers/issues"
                    />
                    {t && (
                        <KpiCard
                            icon={<Wrench size={18} />}
                            label="État maintenance"
                            value={MAINT_LEVEL_LABEL[t.maintenance_level] ?? '—'}
                            variant={MAINT_LEVEL_VARIANT[t.maintenance_level] ?? 'default'}
                            href="/drivers/my-truck"
                        />
                    )}
                </div>

                {/* ── Mes derniers voyages ── */}
                <SectionLabel>Derniers voyages</SectionLabel>
                <Card padding={false}>
                    <div className="p-5">
                        <DataTable
                            data={props.recentTrips}
                            columns={[
                                { key: 'reference', label: 'Réf' },
                                { key: 'provider_date', label: 'Date' },
                                { key: 'truck', label: 'Camion' },
                                { key: 'provider', label: 'Fournisseur', hideOnMobile: true },
                                {
                                    key: 'provider_net_weight', label: 'Poids',
                                    render: (r) => r.provider_net_weight != null
                                        ? `${Number(r.provider_net_weight).toLocaleString('fr-FR', { maximumFractionDigits: 2 })} t`
                                        : '—',
                                },
                            ]}
                            perPage={5}
                            searchable={false}
                            emptyMessage="Aucun voyage enregistré."
                        />
                    </div>
                    <div className="px-5 pb-4">
                        <Link href="/drivers/my-trips" className="text-sm text-[var(--color-primary)] hover:underline">
                            Voir tous mes voyages →
                        </Link>
                    </div>
                </Card>

                {/* ── Historique checklists ── */}
                <SectionLabel>Historique des checklists</SectionLabel>
                <Card padding={false}>
                    <div className="p-5">
                        <DataTable
                            data={props.checklistHistory}
                            columns={[
                                { key: 'checklist_date', label: 'Date' },
                                { key: 'issues_count', label: 'Problèmes' },
                                {
                                    key: 'unresolved_count', label: 'Non résolus',
                                    render: (r) => r.unresolved_count > 0
                                        ? <Badge variant="danger">{r.unresolved_count}</Badge>
                                        : <Badge variant="success">0</Badge>,
                                },
                            ]}
                            perPage={5}
                            searchable={false}
                            emptyMessage="Aucune checklist enregistrée."
                        />
                    </div>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
