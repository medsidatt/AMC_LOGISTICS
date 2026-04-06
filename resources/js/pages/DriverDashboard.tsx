import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import KpiCard from '@/components/dashboard/KpiCard';
import KpiGrid from '@/components/dashboard/KpiGrid';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import DataTable from '@/components/ui/DataTable';
import { usePolling } from '@/hooks/usePolling';
import { formatWeight, formatDate } from '@/utils/formatters';
import { Route, Weight, ClipboardCheck, Truck } from 'lucide-react';

interface Props {
    driver: { id: number; name: string; email: string } | null;
    truck: { id: number; matricule: string } | null;
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
    }>;
    checklistHistory: Array<{
        id: number;
        checklist_date: string;
        issues_count: number;
        unresolved_count: number;
    }>;
}

export default function DriverDashboard(props: Props) {
    usePolling({ interval: 120 });

    if (!props.driver) {
        return (
            <AuthenticatedLayout title="Mon espace">
                <Head title="Mon espace" />
                <Card>
                    <p className="text-[var(--color-text-muted)] text-center py-8">
                        Votre profil conducteur n'est pas encore lié. Contactez un administrateur.
                    </p>
                </Card>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout title="Mon espace">
            <Head title="Mon espace" />

            {/* Driver info + Truck */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <Card>
                    <div className="flex items-center gap-4">
                        <div className="w-14 h-14 rounded-full bg-[var(--color-primary)]/10 flex items-center justify-center">
                            <span className="text-xl font-bold text-[var(--color-primary)]">
                                {props.driver.name.charAt(0)}
                            </span>
                        </div>
                        <div>
                            <h3 className="text-lg font-semibold text-[var(--color-text)]">{props.driver.name}</h3>
                            <p className="text-sm text-[var(--color-text-muted)]">{props.driver.email}</p>
                        </div>
                    </div>
                </Card>
                <Card>
                    <div className="flex items-center gap-4">
                        <div className="w-14 h-14 rounded-full bg-[var(--color-info)]/10 flex items-center justify-center">
                            <Truck size={24} className="text-[var(--color-info)]" />
                        </div>
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Camion assigné</p>
                            <h3 className="text-lg font-semibold text-[var(--color-text)]">
                                {props.truck?.matricule ?? 'Non assigné'}
                            </h3>
                        </div>
                    </div>
                </Card>
            </div>

            {/* KPIs */}
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

            {/* Recent trips + Checklist history */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-6">
                <Card header="Mes derniers voyages">
                    <DataTable
                        data={props.recentTrips}
                        columns={[
                            { key: 'reference', label: 'Réf' },
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
                            { key: 'issues_count', label: 'Problèmes' },
                            {
                                key: 'unresolved_count', label: 'Non résolus',
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
