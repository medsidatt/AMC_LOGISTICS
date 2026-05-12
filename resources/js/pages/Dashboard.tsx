import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import KpiCard from '@/components/dashboard/KpiCard';
import KpiGrid from '@/components/dashboard/KpiGrid';
import AlertBanner from '@/components/dashboard/AlertBanner';
import InsightCard from '@/components/dashboard/InsightCard';
import TonnageChart from '@/components/charts/TonnageChart';
import VehicleUtilization from '@/components/charts/VehicleUtilization';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import DataTable from '@/components/ui/DataTable';
import PeriodFilter from '@/components/dashboard/PeriodFilter';
import RatioCard from '@/components/dashboard/RatioCard';
import TopList from '@/components/dashboard/TopList';
import { usePolling } from '@/hooks/usePolling';
import { generateAdminInsights } from '@/utils/insights';
import { formatNumber, formatDate, calcChange } from '@/utils/formatters';
import { Truck, Users, Route, Weight, Wrench, Download, Activity, Target, Gauge, Fuel, Trophy, UserCheck } from 'lucide-react';
import { useExport } from '@/hooks/useExport';

interface KpiRatio {
    rate: number;
}

interface AvailabilityKpi extends KpiRatio { available: number; total: number; }
interface SaturationKpi extends KpiRatio { active: number; available: number; }
interface ProductionKpi extends KpiRatio { delivered: number; planned: number; monthly_target: number; }
interface LoadKpi extends KpiRatio { delivered: number; theoretical: number; avg_capacity: number; }
interface RotationsKpi { total: number; }
interface FuelYieldKpi { litres_per_tonne: number; litres: number; tonnage: number; }

interface TopTruckRow {
    id: number; label: string; rotations: number; tonnage: number;
    load_rate: number; fuel_yield: number | null; score: number;
}
interface TopDriverRow extends TopTruckRow {
    avg_load_rate: number; manual_points: number; checklist_on_time_rate: number;
    flagged_issues: number; gap_violations: number; gap_ratio: number; discipline_score: number;
}

interface Props {
    trucksCount: number;
    driversCount: number;
    tripsToday: number;
    tripsYesterday: number;
    tonnageMonth: number;
    tonnageLastMonth: number;
    unresolvedAlerts: number;
    recentTrackings: Array<{
        id: number;
        reference: string;
        truck: string | null;
        driver: string | null;
        provider: string | null;
        provider_net_weight: number | null;
        client_net_weight: number | null;
        gap: number | null;
        client_date: string | null;
    }>;
    months: string[];
    monthlyProvider: number[];
    monthlyClient: number[];
    monthlyTrips: number[];
    trucksDueMaintenance: Array<{
        id: number;
        matricule: string;
        maintenance_type: string;
        total_kilometers: number;
    }>;
    utilization: Array<{ label: string; value: number }>;
    kpi: {
        period: { from: string; to: string; days: number };
        kpis: {
            availability: AvailabilityKpi;
            saturation: SaturationKpi;
            production_target: ProductionKpi;
            load_rate: LoadKpi;
            rotations: RotationsKpi;
            fuel_yield: FuelYieldKpi;
        };
        topTrucks: TopTruckRow[];
        topDrivers: TopDriverRow[];
    };
    filter: { from: string; to: string; preset: 'day' | 'week' | 'month' | 'year' | 'custom' };
}

export default function Dashboard(props: Props) {
    usePolling({ interval: 30, only: ['trucksCount', 'driversCount', 'tripsToday', 'unresolvedAlerts'] });

    const { download } = useExport();

    const insights = generateAdminInsights({
        trucksCount: props.trucksCount,
        driversCount: props.driversCount,
        tripsToday: props.tripsToday,
        tripsTodayYesterday: props.tripsYesterday,
        tonnageMonth: props.tonnageMonth,
        tonnageLastMonth: props.tonnageLastMonth,
        unresolvedAlerts: props.unresolvedAlerts,
        trucksDueMaintenance: props.trucksDueMaintenance.length,
    });

    const tripsChange = calcChange(props.tripsToday, props.tripsYesterday);
    const tonnageChange = calcChange(props.tonnageMonth, props.tonnageLastMonth);

    const k = props.kpi.kpis;

    const trackingColumns = [
        { key: 'reference', label: 'Réf' },
        { key: 'client_date', label: 'Date', render: (r: any) => formatDate(r.client_date) },
        { key: 'truck', label: 'Camion' },
        { key: 'driver', label: 'Conducteur', hideOnMobile: true },
        { key: 'provider_net_weight', label: 'Poids Fourni.', render: (r: any) => r.provider_net_weight ? `${formatNumber(r.provider_net_weight)} T` : '-' },
        { key: 'client_net_weight', label: 'Poids Client', render: (r: any) => r.client_net_weight ? `${formatNumber(r.client_net_weight)} T` : '-' },
        {
            key: 'gap', label: 'Perte / Exc.', render: (r: any) => {
                const g = r.gap ?? 0;
                if (g < 0) return <Badge variant="danger">Perte {formatNumber(Math.abs(g), 2)} T</Badge>;
                if (g > 0) return <Badge variant="info">Exc. +{formatNumber(g, 2)} T</Badge>;
                return <Badge variant="success">OK</Badge>;
            },
        },
    ];

    return (
        <AuthenticatedLayout title="Dashboard">
            <Head title="Dashboard" />

            <AlertBanner count={props.unresolvedAlerts} href="/logistics/dashboard" />

            <PeriodFilter
                from={props.filter.from}
                to={props.filter.to}
                preset={props.filter.preset}
            />

            {/* Compteurs simples */}
            <KpiGrid>
                <KpiCard
                    label="Camions"
                    value={props.trucksCount}
                    icon={<Truck size={22} />}
                    color="var(--color-primary)"
                />
                <KpiCard
                    label="Conducteurs"
                    value={props.driversCount}
                    icon={<Users size={22} />}
                    color="var(--color-info)"
                />
                <KpiCard
                    label="Rotations aujourd'hui"
                    value={props.tripsToday}
                    change={tripsChange}
                    changeLabel="vs hier"
                    icon={<Route size={22} />}
                    color="var(--color-success)"
                />
                <KpiCard
                    label="Tonnage du mois"
                    value={props.tonnageMonth}
                    unit="T"
                    change={tonnageChange}
                    changeLabel="vs mois dernier"
                    icon={<Weight size={22} />}
                    color="var(--color-warning)"
                />
            </KpiGrid>

            {/* KPIs flotte sur la période filtrée */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-6">
                <RatioCard
                    label="Disponibilité flotte"
                    ratio={k.availability.rate}
                    numerator={k.availability.available}
                    denominator={k.availability.total}
                    numeratorLabel=" disponibles"
                    denominatorLabel=" total"
                    icon={<Truck size={18} />}
                />
                <RatioCard
                    label="Taux de saturation"
                    ratio={k.saturation.rate}
                    numerator={k.saturation.active}
                    denominator={k.saturation.available}
                    numeratorLabel=" actifs"
                    denominatorLabel=" disponibles"
                    icon={<Activity size={18} />}
                />
                <RatioCard
                    label="Objectif de production"
                    ratio={k.production_target.rate}
                    numerator={k.production_target.delivered}
                    denominator={k.production_target.planned}
                    numeratorLabel=" T livrées"
                    denominatorLabel=" T planifiées"
                    icon={<Target size={18} />}
                />
                <RatioCard
                    label="Taux de chargement"
                    ratio={k.load_rate.rate}
                    numerator={k.load_rate.delivered}
                    denominator={k.load_rate.theoretical}
                    numeratorLabel=" T"
                    denominatorLabel=" T théorique"
                    icon={<Gauge size={18} />}
                />
                <div className="bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-5 shadow-[var(--shadow-sm)] hover:shadow-[var(--shadow-md)] transition-all duration-300 animate-slide-up">
                    <div className="flex items-start justify-between mb-3">
                        <p className="text-xs font-medium uppercase tracking-wider text-[var(--color-text-muted)]">
                            Nombre de rotations
                        </p>
                        <div
                            className="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center"
                            style={{ background: 'var(--color-primary)15', color: 'var(--color-primary)' }}
                        >
                            <Route size={18} />
                        </div>
                    </div>
                    <div className="flex items-baseline gap-1.5">
                        <span className="text-3xl font-bold text-[var(--color-text)]">
                            {formatNumber(k.rotations.total)}
                        </span>
                        <span className="text-sm font-medium text-[var(--color-text-secondary)]">rotations</span>
                    </div>
                    <p className="text-xs text-[var(--color-text-muted)] mt-2">
                        Sur {props.kpi.period.days} jour(s)
                    </p>
                </div>
                <div className="bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-5 shadow-[var(--shadow-sm)] hover:shadow-[var(--shadow-md)] transition-all duration-300 animate-slide-up">
                    <div className="flex items-start justify-between mb-3">
                        <p className="text-xs font-medium uppercase tracking-wider text-[var(--color-text-muted)]">
                            Rendement carburant
                        </p>
                        <div
                            className="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center"
                            style={{ background: 'var(--color-warning)15', color: 'var(--color-warning)' }}
                        >
                            <Fuel size={18} />
                        </div>
                    </div>
                    <div className="flex items-baseline gap-1.5">
                        <span className="text-3xl font-bold text-[var(--color-text)]">
                            {formatNumber(k.fuel_yield.litres_per_tonne, 2)}
                        </span>
                        <span className="text-sm font-medium text-[var(--color-text-secondary)]">L/T</span>
                    </div>
                    <p className="text-xs text-[var(--color-text-muted)] mt-2">
                        {formatNumber(k.fuel_yield.litres, 0)} L · {formatNumber(k.fuel_yield.tonnage, 1)} T livrées
                    </p>
                </div>
            </div>

            {/* Top Camions / Top Chauffeurs */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-6">
                <Card
                    header={
                        <div className="flex items-center gap-2">
                            <Trophy size={16} className="text-[var(--color-warning)]" />
                            <span className="text-sm font-semibold">Top 5 Camions</span>
                        </div>
                    }
                >
                    <TopList
                        rows={props.kpi.topTrucks as any}
                        hrefPrefix="/trucks"
                        extraColumn={{ key: 'fuel_yield', label: 'L/T', format: 'number' }}
                    />
                </Card>

                <Card
                    header={
                        <div className="flex items-center gap-2">
                            <UserCheck size={16} className="text-[var(--color-success)]" />
                            <span className="text-sm font-semibold">Top 5 Chauffeurs</span>
                        </div>
                    }
                >
                    <TopList
                        rows={props.kpi.topDrivers as any}
                        hrefPrefix="/drivers"
                        extraColumn={{ key: 'discipline_score', label: 'discipline', format: 'percent' }}
                    />
                </Card>
            </div>

            {/* Charts row */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-6">
                <Card header="Tonnage mensuel" className="lg:col-span-2">
                    <TonnageChart
                        months={props.months}
                        providerData={props.monthlyProvider}
                        clientData={props.monthlyClient}
                    />
                </Card>

                <div className="space-y-4">
                    <InsightCard insights={insights} />
                    {props.utilization.length > 0 && (
                        <Card header="Utilisation flotte">
                            <VehicleUtilization
                                labels={props.utilization.map((u) => u.label)}
                                values={props.utilization.map((u) => u.value)}
                                height={220}
                            />
                        </Card>
                    )}
                </div>
            </div>

            {/* Maintenance + Recent trackings */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-6">
                {props.trucksDueMaintenance.length > 0 && (
                    <Card
                        header={
                            <div className="flex items-center gap-2">
                                <Wrench size={16} className="text-[var(--color-danger)]" />
                                <span className="text-sm font-semibold">Maintenance requise</span>
                            </div>
                        }
                    >
                        <div className="space-y-3">
                            {props.trucksDueMaintenance.map((truck) => (
                                <a
                                    key={truck.id}
                                    href={`/trucks/${truck.id}`}
                                    className="flex items-center justify-between p-3 rounded-lg bg-red-50 dark:bg-red-900/10 hover:bg-red-100 dark:hover:bg-red-900/20 transition"
                                >
                                    <div>
                                        <p className="text-sm font-semibold text-[var(--color-text)]">{truck.matricule}</p>
                                        <p className="text-xs text-[var(--color-text-muted)]">{truck.maintenance_type}</p>
                                    </div>
                                    <Badge variant="danger">Urgent</Badge>
                                </a>
                            ))}
                        </div>
                    </Card>
                )}

                <Card
                    className={props.trucksDueMaintenance.length > 0 ? 'lg:col-span-2' : 'lg:col-span-3'}
                    header={
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-semibold">Dernières rotations</span>
                            <button
                                onClick={() => download(props.recentTrackings, [
                                    { key: 'reference', label: 'Référence' },
                                    { key: 'client_date', label: 'Date' },
                                    { key: 'truck', label: 'Camion' },
                                    { key: 'driver', label: 'Conducteur' },
                                    { key: 'provider_net_weight', label: 'Poids Fournisseur' },
                                    { key: 'client_net_weight', label: 'Poids Client' },
                                    { key: 'gap', label: 'Perte' },
                                ], 'rotations.csv')}
                                className="p-1.5 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-muted)]"
                                title="Exporter CSV"
                            >
                                <Download size={14} />
                            </button>
                        </div>
                    }
                >
                    <DataTable
                        data={props.recentTrackings}
                        columns={trackingColumns}
                        perPage={5}
                        searchable={false}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
