import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import KpiCard from '@/components/dashboard/KpiCard';
import KpiGrid from '@/components/dashboard/KpiGrid';
import InsightCard from '@/components/dashboard/InsightCard';
import TonnageChart from '@/components/charts/TonnageChart';
import RotationTimeline from '@/components/charts/RotationTimeline';
import DistributionPie from '@/components/charts/DistributionPie';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Select from '@/components/ui/Select';
import DateRangePicker from '@/components/ui/DateRangePicker';
import { useFilters } from '@/hooks/useFilters';
import { usePolling } from '@/hooks/usePolling';
import { useExport } from '@/hooks/useExport';
import { generateTransportInsights } from '@/utils/insights';
import { formatNumber } from '@/utils/formatters';
import { Weight, Scale, AlertTriangle, Route, TrendingDown, CheckCircle, Download, Filter, RotateCcw } from 'lucide-react';

interface FilterOption {
    value: number;
    label: string;
}

interface Props {
    filters: Record<string, string | null>;
    filterOptions: {
        transporters: FilterOption[];
        trucks: FilterOption[];
        drivers: FilterOption[];
        providers: FilterOption[];
    };
    kpis: {
        totalTransported: number;
        totalReceived: number;
        totalDifference: number;
        totalPoidsAnomalies: number;
        totalCount: number;
        rotationsPerdues: number;
        rotationsNormal: number;
        pctReceived: number;
        pctDifference: number;
        pctAnomalies: number;
        pctPerdues: number;
        pctNormal: number;
    };
    months: string[];
    monthlyWeights: number[];
    timelineEvents: Array<{
        truck: string;
        driver: string;
        start: string;
        end: string;
        reference: string;
        hasConflict: boolean;
    }>;
}

export default function TransportDashboard({ filters: initialFilters, filterOptions, kpis, months, monthlyWeights, timelineEvents }: Props) {
    const { filters, updateFilter, applyFilters, resetFilters, loading } = useFilters(
        initialFilters as any,
        '/transport_tracking/dashboard',
    );
    const { download } = useExport();
    usePolling({ interval: 60, only: ['kpis'] });

    const insights = generateTransportInsights({
        totalGap: kpis.totalDifference,
        totalTransported: kpis.totalTransported,
        anomaliesCount: kpis.totalCount - kpis.rotationsNormal,
        totalTrips: kpis.totalCount,
        suspiciousDrivers: 0,
    });

    const conflictCount = timelineEvents.filter((e) => e.hasConflict).length;

    return (
        <AuthenticatedLayout title="Dashboard Transport">
            <Head title="Dashboard Transport" />

            {/* Filters */}
            <Card className="mb-6">
                <div className="flex flex-wrap items-end gap-3">
                    <DateRangePicker
                        startDate={filters.start_date ?? ''}
                        endDate={filters.end_date ?? ''}
                        onStartChange={(v) => updateFilter('start_date', v)}
                        onEndChange={(v) => updateFilter('end_date', v)}
                    />
                    <Select
                        label="Transporteur"
                        options={filterOptions.transporters as any}
                        value={filters.transporter_id}
                        onChange={(v) => updateFilter('transporter_id', v)}
                        className="w-44"
                    />
                    <Select
                        label="Camion"
                        options={filterOptions.trucks as any}
                        value={filters.truck_id}
                        onChange={(v) => updateFilter('truck_id', v)}
                        className="w-44"
                    />
                    <Select
                        label="Conducteur"
                        options={filterOptions.drivers as any}
                        value={filters.driver_id}
                        onChange={(v) => updateFilter('driver_id', v)}
                        className="w-44"
                    />
                    <Select
                        label="Fournisseur"
                        options={filterOptions.providers as any}
                        value={filters.provider_id}
                        onChange={(v) => updateFilter('provider_id', v)}
                        className="w-44"
                    />
                    <div className="flex items-end gap-2">
                        <Button onClick={() => applyFilters()} loading={loading} icon={<Filter size={14} />}>
                            Filtrer
                        </Button>
                        <Button variant="ghost" onClick={resetFilters} icon={<RotateCcw size={14} />}>
                            Reset
                        </Button>
                    </div>
                </div>
            </Card>

            {/* KPIs Row 1 */}
            <KpiGrid>
                <KpiCard
                    label="Poids transporté"
                    value={kpis.totalTransported}
                    unit="kg"
                    icon={<Weight size={22} />}
                    color="var(--color-primary)"
                />
                <KpiCard
                    label="Poids reçu"
                    value={kpis.totalReceived}
                    unit="kg"
                    change={kpis.pctReceived - 100}
                    changeLabel="vs transporté"
                    icon={<Scale size={22} />}
                    color="var(--color-success)"
                />
                <KpiCard
                    label="Poids perdu"
                    value={kpis.totalDifference}
                    unit="kg"
                    icon={<TrendingDown size={22} />}
                    color="var(--color-danger)"
                />
                <KpiCard
                    label="Anomalies poids"
                    value={kpis.totalPoidsAnomalies}
                    unit="kg"
                    icon={<AlertTriangle size={22} />}
                    color="var(--color-warning)"
                />
            </KpiGrid>

            {/* KPIs Row 2 */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4">
                <div className="bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-4 text-center">
                    <p className="text-2xl font-bold text-[var(--color-text)]">{formatNumber(kpis.totalCount)}</p>
                    <p className="text-xs text-[var(--color-text-muted)] mt-1">Rotations totales</p>
                </div>
                <div className="bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-4 text-center">
                    <p className="text-2xl font-bold text-[var(--color-danger)]">{formatNumber(kpis.rotationsPerdues)}</p>
                    <p className="text-xs text-[var(--color-text-muted)] mt-1">Perdues ({kpis.pctPerdues}%)</p>
                </div>
                <div className="bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-4 text-center">
                    <p className="text-2xl font-bold text-[var(--color-success)]">{formatNumber(kpis.rotationsNormal)}</p>
                    <p className="text-xs text-[var(--color-text-muted)] mt-1">Normales ({kpis.pctNormal}%)</p>
                </div>
                <div className="bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-4 text-center">
                    <p className="text-2xl font-bold text-amber-500">{conflictCount}</p>
                    <p className="text-xs text-[var(--color-text-muted)] mt-1">Conflits détectés</p>
                </div>
            </div>

            {/* Charts */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-6">
                <Card header="Poids mensuel" className="lg:col-span-2">
                    <TonnageChart months={months} providerData={monthlyWeights} />
                </Card>
                <div className="space-y-4">
                    <InsightCard insights={insights} />
                    <Card
                        header={
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-semibold">Distribution rotations</span>
                            </div>
                        }
                    >
                        <DistributionPie
                            labels={['Normales', 'Perdues']}
                            values={[kpis.rotationsNormal, kpis.rotationsPerdues]}
                            height={220}
                        />
                    </Card>
                </div>
            </div>

            {/* Timeline / Gantt */}
            <Card header="Timeline des rotations" className="mt-6">
                <RotationTimeline events={timelineEvents} height={400} />
            </Card>
        </AuthenticatedLayout>
    );
}
