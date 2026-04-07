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
import { usePolling } from '@/hooks/usePolling';
import { generateAdminInsights } from '@/utils/insights';
import { formatNumber, formatWeight, formatDate, calcChange } from '@/utils/formatters';
import { Truck, Users, Route, Weight, Wrench, Download } from 'lucide-react';
import { useExport } from '@/hooks/useExport';

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

    const trackingColumns = [
        { key: 'reference', label: 'Réf' },
        { key: 'client_date', label: 'Date', render: (r: any) => formatDate(r.client_date) },
        { key: 'truck', label: 'Camion' },
        { key: 'driver', label: 'Conducteur', hideOnMobile: true },
        { key: 'provider_net_weight', label: 'Poids Fourni.', render: (r: any) => r.provider_net_weight ? `${formatNumber(r.provider_net_weight)} T` : '-' },
        { key: 'client_net_weight', label: 'Poids Client', render: (r: any) => r.client_net_weight ? `${formatNumber(r.client_net_weight)} T` : '-' },
        {
            key: 'gap', label: 'Écart', render: (r: any) => {
                const gap = r.gap ?? 0;
                const variant = gap === 0 ? 'success' : gap < 0 ? 'danger' : 'warning';
                return <Badge variant={variant}>{formatNumber(gap, 2)} T</Badge>;
            },
        },
    ];

    return (
        <AuthenticatedLayout title="Dashboard">
            <Head title="Dashboard" />

            <AlertBanner count={props.unresolvedAlerts} href="/logistics/dashboard" />

            {/* KPIs */}
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
                                    { key: 'gap', label: 'Écart' },
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
