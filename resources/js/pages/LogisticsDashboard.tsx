import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import AlertBanner from '@/components/dashboard/AlertBanner';
import KpiCard from '@/components/dashboard/KpiCard';
import KpiGrid from '@/components/dashboard/KpiGrid';
import { usePolling } from '@/hooks/usePolling';
import { formatDate } from '@/utils/formatters';
import { Wrench, AlertTriangle, ClipboardCheck, Bell, CheckCircle2, ChevronRight, RotateCcw } from 'lucide-react';

interface Rotation {
    id: number;
    reference: string;
    truck: string | null;
    driver: string | null;
    start_km: number;
    end_km: number;
    distance: number;
    date: string | null;
}

interface Props {
    dueEngineTrucks: Array<{ id: number; matricule: string; total_kilometers: number; level: string }>;
    unresolvedIssues: Array<{ id: number; description: string; category: string; checklist_date: string; truck: string | null; driver: string | null }>;
    lastChecklists: Array<{ id: number; checklist_date: string; truck: string | null; driver: string | null; issues_count: number }>;
    alerts: Array<{ id: number; type: string; message: string; created_at: string }>;
    unvalidatedRotations: Rotation[];
}

export default function LogisticsDashboard({ dueEngineTrucks, unresolvedIssues, lastChecklists, alerts, unvalidatedRotations }: Props) {
    usePolling({ interval: 30, only: ['alerts', 'unresolvedIssues', 'dueEngineTrucks', 'lastChecklists', 'unvalidatedRotations'] });
    const [resolvingId, setResolvingId] = useState<number | null>(null);
    const [validatingId, setValidatingId] = useState<number | null>(null);

    const redCount = dueEngineTrucks.filter((t) => t.level === 'red').length;

    const resolveIssue = (id: number) => {
        router.post(`/logistics/daily-issues/${id}/resolve`, {}, { preserveScroll: true, onFinish: () => setResolvingId(null) });
    };

    const validateRotation = (id: number) => {
        setValidatingId(id);
        router.post(`/logistics/rotations/${id}/validate`, {}, { preserveScroll: true, onFinish: () => setValidatingId(null) });
    };

    return (
        <AuthenticatedLayout title="Logistique">
            <Head title="Logistique" />

            {alerts.length > 0 && <AlertBanner count={alerts.length} message={`${alerts.length} alerte${alerts.length > 1 ? 's' : ''} active${alerts.length > 1 ? 's' : ''}`} />}

            <KpiGrid>
                <KpiCard label="Maintenance urgente" value={redCount} icon={<Wrench size={22} />} color="var(--color-danger)" />
                <KpiCard label="Rotations à valider" value={unvalidatedRotations?.length ?? 0} icon={<RotateCcw size={22} />} color="var(--color-info)" />
                <KpiCard label="Problèmes ouverts" value={unresolvedIssues.length} icon={<AlertTriangle size={22} />} color={unresolvedIssues.length > 0 ? 'var(--color-danger)' : 'var(--color-success)'} />
                <KpiCard label="Checklists récentes" value={lastChecklists.length} icon={<ClipboardCheck size={22} />} color="var(--color-info)" />
            </KpiGrid>

            {/* Link to full maintenance dashboard */}
            <div className="mt-4">
                <a href="/maintenance" className="inline-flex items-center gap-1 text-sm text-[var(--color-primary)] hover:underline">
                    <Wrench size={14} /> Voir le tableau de maintenance complet <ChevronRight size={14} />
                </a>
            </div>

            {/* Unvalidated rotations */}
            {unvalidatedRotations && unvalidatedRotations.length > 0 && (
                <Card className="mt-6">
                    <div className="flex items-center gap-2 mb-4">
                        <RotateCcw size={18} className="text-[var(--color-info)]" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Rotations à valider</h3>
                        <Badge variant="info">{unvalidatedRotations.length}</Badge>
                    </div>
                    <div className="space-y-2">
                        {unvalidatedRotations.map((r) => (
                            <div key={r.id} className="flex items-center justify-between p-3 rounded-lg border border-[var(--color-border)]">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-3 text-sm">
                                        <span className="font-medium text-[var(--color-text)]">{r.reference}</span>
                                        <span className="text-[var(--color-text-secondary)]">{r.truck}</span>
                                        <span className="text-[var(--color-text-muted)]">{r.driver}</span>
                                    </div>
                                    <div className="flex items-center gap-3 text-xs text-[var(--color-text-muted)] mt-1">
                                        <span>{r.start_km?.toLocaleString('fr-FR')} → {r.end_km?.toLocaleString('fr-FR')} km</span>
                                        <Badge variant="info">{r.distance?.toLocaleString('fr-FR')} km</Badge>
                                        <span>{r.date}</span>
                                    </div>
                                </div>
                                <Button size="sm" loading={validatingId === r.id} onClick={() => validateRotation(r.id)}>
                                    Valider
                                </Button>
                            </div>
                        ))}
                    </div>
                </Card>
            )}

            {/* Issues + Checklists */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <AlertTriangle size={18} className="text-[var(--color-danger)]" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Problèmes non résolus</h3>
                        {unresolvedIssues.length > 0 && <Badge variant="danger">{unresolvedIssues.length}</Badge>}
                    </div>
                    {unresolvedIssues.length === 0 ? (
                        <div className="text-center py-8 text-[var(--color-text-muted)]">
                            <CheckCircle2 size={32} className="mx-auto mb-2 text-emerald-500" />
                            <p className="text-sm">Aucun problème en cours</p>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {unresolvedIssues.slice(0, 10).map((issue) => (
                                <div key={issue.id} className="flex items-start gap-3 p-3 rounded-lg border border-[var(--color-border)]">
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 mb-1">
                                            <Badge variant="danger">{issue.category}</Badge>
                                            <span className="text-xs text-[var(--color-text-muted)]">{formatDate(issue.checklist_date)}</span>
                                        </div>
                                        {issue.description && <p className="text-sm text-[var(--color-text)] mb-1">{issue.description}</p>}
                                        <div className="flex gap-3 text-xs text-[var(--color-text-secondary)]">
                                            {issue.truck && <span>Camion: {issue.truck}</span>}
                                            {issue.driver && <span>Conducteur: {issue.driver}</span>}
                                        </div>
                                    </div>
                                    <Button size="sm" variant="ghost" loading={resolvingId === issue.id}
                                        onClick={() => { setResolvingId(issue.id); resolveIssue(issue.id); }}>
                                        <CheckCircle2 size={16} className="text-emerald-500" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>

                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <ClipboardCheck size={18} className="text-[var(--color-info)]" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Dernières checklists</h3>
                    </div>
                    {lastChecklists.length === 0 ? (
                        <div className="text-center py-8 text-[var(--color-text-muted)]">
                            <ClipboardCheck size={32} className="mx-auto mb-2 opacity-40" />
                            <p className="text-sm">Aucune checklist récente</p>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {lastChecklists.map((cl) => (
                                <div key={cl.id} className="flex items-center justify-between p-3 rounded-lg border border-[var(--color-border)]">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-medium text-[var(--color-text)]">{cl.truck ?? '-'}</span>
                                            <span className="text-xs text-[var(--color-text-muted)]">{formatDate(cl.checklist_date)}</span>
                                        </div>
                                        {cl.driver && <p className="text-xs text-[var(--color-text-secondary)] mt-0.5">{cl.driver}</p>}
                                    </div>
                                    <Badge variant={cl.issues_count > 0 ? 'danger' : 'success'}>
                                        {cl.issues_count > 0 ? `${cl.issues_count} problème${cl.issues_count > 1 ? 's' : ''}` : 'OK'}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>

            {/* Alerts */}
            {alerts.length > 0 && (
                <Card className="mt-6">
                    <div className="flex items-center gap-2 mb-4">
                        <Bell size={18} className="text-[var(--color-warning)]" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Alertes</h3>
                        <Badge variant="warning">{alerts.length}</Badge>
                    </div>
                    <div className="space-y-2">
                        {alerts.map((alert) => (
                            <div key={alert.id} className="flex items-start gap-3 p-3 rounded-lg bg-[var(--color-surface-hover)]">
                                <Bell size={16} className={alert.type === 'critical' ? 'text-[var(--color-danger)]' : 'text-[var(--color-warning)]'} />
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm text-[var(--color-text)]">{alert.message}</p>
                                    <p className="text-xs text-[var(--color-text-muted)] mt-1">{alert.created_at}</p>
                                </div>
                                <Badge variant={alert.type === 'critical' ? 'danger' : 'warning'} size="sm">{alert.type}</Badge>
                            </div>
                        ))}
                    </div>
                </Card>
            )}
        </AuthenticatedLayout>
    );
}
