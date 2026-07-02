import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import KpiCard from '@/components/dashboard/KpiCard';
import KpiGrid from '@/components/dashboard/KpiGrid';
import AlertBanner from '@/components/dashboard/AlertBanner';
import RatioCard from '@/components/dashboard/RatioCard';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import { usePolling } from '@/hooks/usePolling';
import { calcChange } from '@/utils/formatters';
import { Truck, Users, Route, Weight, Wrench, Activity } from 'lucide-react';

function SectionLabel({ children }: { children: React.ReactNode }) {
    return (
        <div className="text-xs uppercase tracking-wider font-semibold text-[var(--color-text-muted)] mt-6 mb-2">
            {children}
        </div>
    );
}

/**
 * An activity KPI that shows a clear empty state instead of a misleading 0 when the period
 * has no activity. Presentation only.
 */
function ActivityKpi({ label, value, unit, change, changeLabel, icon, color }: {
    label: string;
    value: number;
    unit?: string;
    change?: number;
    changeLabel?: string;
    icon: React.ReactNode;
    color?: string;
}) {
    if (!value) {
        const accent = color ?? 'var(--color-primary)';
        return (
            <div className="bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-5 shadow-[var(--shadow-sm)]">
                <div className="flex items-start justify-between">
                    <p className="text-xs font-medium uppercase tracking-wider text-[var(--color-text-muted)] mb-2">{label}</p>
                    <div className="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center" style={{ background: `${accent}15`, color: accent }}>
                        {icon}
                    </div>
                </div>
                <div className="flex items-baseline">
                    <span className="text-2xl font-bold text-[var(--color-text-muted)]">—</span>
                </div>
                <p className="text-xs text-[var(--color-text-muted)] mt-2">Aucune activité</p>
            </div>
        );
    }
    return <KpiCard label={label} value={value} unit={unit} change={change} changeLabel={changeLabel} icon={icon} color={color} />;
}

interface Props {
    driversCount: number;
    tripsToday: number;
    tripsYesterday: number;
    tonnageMonth: number;
    tonnageLastMonth: number;
    unresolvedAlerts: number;
    trucksDueMaintenance: Array<{ id: number; matricule: string; maintenance_type: string }>;
    // Owner-sourced headline KPIs (keyed by Business KPI id). Presentation reads value/components only.
    businessKpis: Record<string, { id: string; label: string; unit: string; value: number; components: Record<string, number> }>;
}

export default function Dashboard(props: Props) {
    usePolling({ interval: 30, only: ['driversCount', 'tripsToday', 'tonnageMonth', 'unresolvedAlerts', 'businessKpis', 'trucksDueMaintenance'] });

    const tripsChange = calcChange(props.tripsToday, props.tripsYesterday);
    const tonnageChange = calcChange(props.tonnageMonth, props.tonnageLastMonth);

    // Owner-sourced headline accessors (presentation only — pure lookups, never a calculation).
    const bkVal = (id: string) => props.businessKpis?.[id]?.value ?? 0;
    const bkComp = (id: string, key: string) => props.businessKpis?.[id]?.components?.[key] ?? 0;

    return (
        <AuthenticatedLayout title="Dashboard">
            <Head title="Dashboard" />

            <AlertBanner count={props.unresolvedAlerts} href="/logistics/dashboard" />

            <SectionLabel>Aperçu général</SectionLabel>
            <KpiGrid>
                <KpiCard
                    label="Camions"
                    value={bkVal('BI-FLT-001')}
                    icon={<Truck size={22} />}
                    color="var(--color-primary)"
                />
                <KpiCard
                    label="Conducteurs"
                    value={props.driversCount}
                    icon={<Users size={22} />}
                    color="var(--color-info)"
                />
                <ActivityKpi
                    label="Rotations aujourd'hui"
                    value={props.tripsToday}
                    change={tripsChange}
                    changeLabel="vs hier"
                    icon={<Route size={22} />}
                    color="var(--color-success)"
                />
                <ActivityKpi
                    label="Tonnage du mois"
                    value={props.tonnageMonth}
                    unit="T"
                    change={tonnageChange}
                    changeLabel="vs mois dernier"
                    icon={<Weight size={22} />}
                    color="var(--color-warning)"
                />
            </KpiGrid>

            <SectionLabel>État de la flotte</SectionLabel>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <RatioCard
                    label="Disponibilité flotte"
                    ratio={bkVal('BI-FLT-003')}
                    numerator={bkComp('BI-FLT-003', 'available')}
                    denominator={bkComp('BI-FLT-003', 'total')}
                    numeratorLabel=" disponibles"
                    denominatorLabel=" total"
                    icon={<Truck size={18} />}
                />
                <RatioCard
                    label="Taux de saturation"
                    ratio={bkVal('BI-FLT-004')}
                    numerator={bkComp('BI-FLT-004', 'active')}
                    denominator={bkComp('BI-FLT-004', 'available')}
                    numeratorLabel=" ayant roulé"
                    denominatorLabel=" disponibles"
                    icon={<Activity size={18} />}
                />
            </div>

            {props.trucksDueMaintenance.length > 0 && (
                <>
                    <SectionLabel>À traiter</SectionLabel>
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
                </>
            )}
        </AuthenticatedLayout>
    );
}
