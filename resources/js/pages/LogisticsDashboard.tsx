import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import DataTable from '@/components/ui/DataTable';
import AlertBanner from '@/components/dashboard/AlertBanner';
import KpiCard from '@/components/dashboard/KpiCard';
import KpiGrid from '@/components/dashboard/KpiGrid';
import { usePolling } from '@/hooks/usePolling';
import { formatDate } from '@/utils/formatters';
import { Wrench, AlertTriangle, ClipboardCheck, Bell } from 'lucide-react';

interface Props {
    dueEngineTrucks: Array<{
        id: number;
        matricule: string;
        total_kilometers: number;
        level: string;
    }>;
    unresolvedIssues: Array<{
        id: number;
        description: string;
        category: string;
        checklist_date: string;
        truck: string | null;
        driver: string | null;
    }>;
    lastChecklists: Array<{
        id: number;
        checklist_date: string;
        truck: string | null;
        driver: string | null;
        issues_count: number;
    }>;
    alerts: Array<{
        id: number;
        type: string;
        message: string;
        created_at: string;
    }>;
}

export default function LogisticsDashboard({ dueEngineTrucks, unresolvedIssues, lastChecklists, alerts }: Props) {
    usePolling({ interval: 30, only: ['alerts', 'unresolvedIssues'] });

    const redCount = dueEngineTrucks.filter((t) => t.level === 'red').length;
    const yellowCount = dueEngineTrucks.filter((t) => t.level === 'yellow').length;

    return (
        <AuthenticatedLayout title="Maintenance">
            <Head title="Maintenance" />

            <AlertBanner count={alerts.length} message={`${alerts.length} alerte${alerts.length > 1 ? 's' : ''} non résolue${alerts.length > 1 ? 's' : ''}`} />

            {/* KPIs */}
            <KpiGrid>
                <KpiCard
                    label="Maintenance urgente"
                    value={redCount}
                    icon={<Wrench size={22} />}
                    color="var(--color-danger)"
                />
                <KpiCard
                    label="Maintenance à prévoir"
                    value={yellowCount}
                    icon={<Wrench size={22} />}
                    color="var(--color-warning)"
                />
                <KpiCard
                    label="Problèmes non résolus"
                    value={unresolvedIssues.length}
                    icon={<AlertTriangle size={22} />}
                    color="var(--color-danger)"
                />
                <KpiCard
                    label="Alertes actives"
                    value={alerts.length}
                    icon={<Bell size={22} />}
                    color="var(--color-info)"
                />
            </KpiGrid>

            {/* Alerts + Due trucks */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-6">
                {/* Alerts */}
                {alerts.length > 0 && (
                    <Card header="Alertes récentes">
                        <div className="space-y-2">
                            {alerts.map((alert) => (
                                <div key={alert.id} className="flex items-start gap-3 p-3 rounded-lg bg-[var(--color-surface-hover)]">
                                    <Bell size={16} className="text-[var(--color-warning)] mt-0.5 flex-shrink-0" />
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm text-[var(--color-text)]">{alert.message}</p>
                                        <p className="text-xs text-[var(--color-text-muted)] mt-1">{alert.created_at}</p>
                                    </div>
                                    <Badge variant={alert.type === 'critical' ? 'danger' : 'warning'} size="sm">
                                        {alert.type}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}

                {/* Due trucks */}
                <Card
                    header={
                        <div className="flex items-center gap-2">
                            <Wrench size={16} className="text-[var(--color-danger)]" />
                            <span>Camions en maintenance</span>
                        </div>
                    }
                >
                    <DataTable
                        data={dueEngineTrucks}
                        columns={[
                            { key: 'matricule', label: 'Matricule' },
                            { key: 'total_kilometers', label: 'Kilométrage', render: (r) => `${r.total_kilometers?.toLocaleString('fr-FR') ?? 0} km` },
                            {
                                key: 'level', label: 'Statut',
                                render: (r) => (
                                    <Badge variant={r.level === 'red' ? 'danger' : 'warning'}>
                                        {r.level === 'red' ? 'Urgent' : 'À prévoir'}
                                    </Badge>
                                ),
                            },
                        ]}
                        perPage={10}
                        searchable={false}
                    />
                </Card>
            </div>

            {/* Issues + Checklists */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-6">
                <Card header="Problèmes non résolus">
                    <DataTable
                        data={unresolvedIssues}
                        columns={[
                            { key: 'checklist_date', label: 'Date', render: (r) => formatDate(r.checklist_date) },
                            { key: 'truck', label: 'Camion' },
                            { key: 'driver', label: 'Conducteur', hideOnMobile: true },
                            { key: 'description', label: 'Description', render: (r) => (
                                <span className="text-sm max-w-[200px] truncate block">{r.description || r.category}</span>
                            )},
                        ]}
                        perPage={10}
                    />
                </Card>

                <Card header="Dernières checklists">
                    <DataTable
                        data={lastChecklists}
                        columns={[
                            { key: 'checklist_date', label: 'Date', render: (r) => formatDate(r.checklist_date) },
                            { key: 'truck', label: 'Camion' },
                            { key: 'driver', label: 'Conducteur', hideOnMobile: true },
                            {
                                key: 'issues_count', label: 'Problèmes',
                                render: (r) => r.issues_count > 0
                                    ? <Badge variant="danger">{r.issues_count}</Badge>
                                    : <Badge variant="success">0</Badge>,
                            },
                        ]}
                        perPage={10}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
