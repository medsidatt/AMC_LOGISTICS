import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import KpiCard from '@/components/dashboard/KpiCard';
import KpiGrid from '@/components/dashboard/KpiGrid';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import DataTable from '@/components/ui/DataTable';
import { usePolling } from '@/hooks/usePolling';
import { formatWeight, formatDate } from '@/utils/formatters';
import {
    Route, Weight, ClipboardCheck, Truck, Fuel, Gauge,
    Activity, Timer, ArrowRight,
} from 'lucide-react';

interface TruckInfo {
    id: number;
    matricule: string;
    total_kilometers: number;
    fuel_level: number | null;
    speed: number | null;
    movement_status: string | null;
    last_sync: string | null;
}

interface Props {
    driver: { id: number; name: string; email: string } | null;
    truck: TruckInfo | null;
    todayChecklistDone: boolean;
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
    parked: 'Stationn\u00e9',
};

const MOVEMENT_COLOR: Record<string, 'success' | 'warning' | 'muted'> = {
    moving: 'success',
    idle: 'warning',
    parked: 'muted',
};

export default function DriverDashboard(props: Props) {
    usePolling({ interval: 120 });

    if (!props.driver) {
        return (
            <AuthenticatedLayout title="Mon espace">
                <Head title="Mon espace" />
                <Card>
                    <div className="flex flex-col items-center py-12 text-center">
                        <div className="w-16 h-16 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mb-4">
                            <Truck size={28} className="text-amber-500" />
                        </div>
                        <h3 className="text-lg font-semibold text-[var(--color-text)] mb-2">Profil non li\u00e9</h3>
                        <p className="text-sm text-[var(--color-text-muted)] max-w-sm">
                            Votre profil conducteur n'est pas encore li\u00e9 \u00e0 votre compte. Contactez un administrateur pour activer votre espace conducteur.
                        </p>
                    </div>
                </Card>
            </AuthenticatedLayout>
        );
    }

    const t = props.truck;

    return (
        <AuthenticatedLayout title="Mon espace">
            <Head title="Mon espace" />

            {/* ── Driver + truck header ── */}
            <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5 mb-5">
                <div className="flex flex-wrap items-center gap-4 mb-4">
                    <div className="w-14 h-14 rounded-full bg-[var(--color-primary)]/10 flex items-center justify-center">
                        <span className="text-xl font-bold text-[var(--color-primary)]">
                            {props.driver.name.charAt(0)}
                        </span>
                    </div>
                    <div className="flex-1 min-w-0">
                        <h2 className="text-xl font-bold text-[var(--color-text)] truncate">{props.driver.name}</h2>
                        <p className="text-sm text-[var(--color-text-muted)]">{props.driver.email}</p>
                    </div>
                    {t && (
                        <a
                            href="/drivers/my-truck"
                            className="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[var(--color-primary)]/10 text-[var(--color-primary)] font-medium text-sm hover:bg-[var(--color-primary)]/20 transition"
                        >
                            <Truck size={16} />
                            {t.matricule}
                            <ArrowRight size={14} />
                        </a>
                    )}
                </div>

                {/* Live truck telemetry strip */}
                {t && (
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div className="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-[var(--color-surface-hover)]">
                            <Gauge size={16} className="text-blue-500 shrink-0" />
                            <div className="min-w-0">
                                <div className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">Compteur</div>
                                <div className="text-sm font-bold text-[var(--color-text)]">
                                    {Math.round(t.total_kilometers).toLocaleString('fr-FR')} km
                                </div>
                            </div>
                        </div>
                        <div className="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-[var(--color-surface-hover)]">
                            <Fuel size={16} className="text-amber-500 shrink-0" />
                            <div className="min-w-0">
                                <div className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">Carburant</div>
                                <div className="text-sm font-bold text-[var(--color-text)]">
                                    {t.fuel_level !== null ? `${t.fuel_level.toFixed(0)} L` : '-'}
                                </div>
                            </div>
                        </div>
                        <div className="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-[var(--color-surface-hover)]">
                            <Activity size={16} className="text-emerald-500 shrink-0" />
                            <div className="min-w-0">
                                <div className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">\u00c9tat</div>
                                <Badge variant={MOVEMENT_COLOR[t.movement_status ?? ''] ?? 'muted'} size="sm">
                                    {MOVEMENT_LABEL[t.movement_status ?? ''] ?? 'Inconnu'}
                                </Badge>
                            </div>
                        </div>
                        <div className="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-[var(--color-surface-hover)]">
                            <Timer size={16} className="text-[var(--color-text-muted)] shrink-0" />
                            <div className="min-w-0">
                                <div className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">Derni\u00e8re sync</div>
                                <div className="text-xs font-medium text-[var(--color-text)] truncate">
                                    {t.last_sync ?? '-'}
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* ── KPIs ── */}
            <KpiGrid columns={3}>
                <KpiCard
                    label="Rotations ce mois"
                    value={props.myTripsMonth}
                    icon={<Route size={22} />}
                    color="var(--color-primary)"
                />
                <KpiCard
                    label="Tonnage ce mois"
                    value={props.myTonnageMonth}
                    unit="kg"
                    icon={<Weight size={22} />}
                    color="var(--color-success)"
                />
                <KpiCard
                    label="Checklist aujourd'hui"
                    value={props.todayChecklistDone ? 1 : 0}
                    icon={<ClipboardCheck size={22} />}
                    color={props.todayChecklistDone ? 'var(--color-success)' : 'var(--color-danger)'}
                />
            </KpiGrid>

            {/* Quick action: checklist not done */}
            {!props.todayChecklistDone && (
                <div className="mt-5 rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-900/15 dark:border-amber-700 p-4 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <ClipboardCheck size={20} className="text-amber-600" />
                        <div>
                            <div className="font-semibold text-amber-800 dark:text-amber-300 text-sm">Checklist non soumise</div>
                            <div className="text-xs text-amber-600 dark:text-amber-400">N'oubliez pas de remplir votre checklist quotidien.</div>
                        </div>
                    </div>
                    <a
                        href="/drivers/checklist-page"
                        className="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium transition shrink-0"
                    >
                        Remplir
                    </a>
                </div>
            )}

            {/* ── Tables ── */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-6">
                <Card header="Mes derniers voyages">
                    <DataTable
                        data={props.recentTrips}
                        columns={[
                            { key: 'reference', label: 'R\u00e9f' },
                            { key: 'provider_date', label: 'Date', render: (r) => formatDate(r.provider_date) },
                            { key: 'truck', label: 'Camion' },
                            { key: 'provider_net_weight', label: 'Poids', render: (r) => r.provider_net_weight ? formatWeight(r.provider_net_weight) : '-' },
                        ]}
                        perPage={5}
                        searchable={false}
                    />
                </Card>

                <Card header="Historique checklists">
                    <DataTable
                        data={props.checklistHistory}
                        columns={[
                            { key: 'checklist_date', label: 'Date', render: (r) => formatDate(r.checklist_date) },
                            { key: 'issues_count', label: 'Probl\u00e8mes' },
                            {
                                key: 'unresolved_count', label: 'Non r\u00e9solus',
                                render: (r) => r.unresolved_count > 0
                                    ? <Badge variant="danger">{r.unresolved_count}</Badge>
                                    : <Badge variant="success">0</Badge>,
                            },
                        ]}
                        perPage={7}
                        searchable={false}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
