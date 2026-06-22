import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import DataTable from '@/components/ui/DataTable';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { Plus, Pencil, Archive, ArchiveRestore, Target } from 'lucide-react';
import { usePermission } from '@/hooks/usePermission';
import type { PlanningMode } from '@/types/achievement';

/**
 * Objectives list — definition + lifecycle. Creating/editing opens the authoring
 * page (period + target + truck allocation), which absorbed the old fleet-roster
 * screen. Planned-vs-Actual / achievement KPIs live in the Planning Dashboard.
 */
interface Objective {
    id: number;
    period_type: PlanningMode;
    start_date: string;
    end_date: string;
    target_tons: number;
    target_rotations: number;
    working_trucks: number;
    notes: string | null;
    archived: boolean;
    created_by: string | null;
}

interface Props {
    objectives: Objective[];
    showArchived: boolean;
}

const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR');
const TYPE_LABEL: Record<PlanningMode, string> = { WEEK: 'Semaine', MONTH: 'Mois', YEAR: 'Année', CUSTOM: 'Personnalisé' };
const TYPE_VARIANT: Record<PlanningMode, 'primary' | 'info' | 'success' | 'muted'> = { WEEK: 'primary', MONTH: 'info', YEAR: 'success', CUSTOM: 'muted' };

export default function ObjectivesIndex({ objectives, showArchived }: Props) {
    const [archiveTarget, setArchiveTarget] = useState<Objective | null>(null);
    const { can } = usePermission();
    const canManage = can('fleet-roster-plan');

    const confirmArchive = () => {
        if (!archiveTarget) return;
        router.post(`/logistics/objectives/${archiveTarget.id}/archive`, {}, { onFinish: () => setArchiveTarget(null) });
    };

    return (
        <AuthenticatedLayout title="Objectifs">
            <Head title="Objectifs de planification" />
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <div className="flex items-center gap-2">
                            <Target size={22} className="text-[var(--color-primary)]" />
                            <h1 className="text-xl font-semibold">Objectifs de planification</h1>
                        </div>
                        <p className="text-sm text-[var(--color-text-muted)] mt-1">
                            Définition des objectifs et répartition sur la flotte. Le suivi réalisé est dans le tableau de planification.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => router.get('/logistics/objectives', { archived: showArchived ? 0 : 1 }, { preserveScroll: true })}
                        >
                            {showArchived ? 'Masquer archivés' : 'Voir archivés'}
                        </Button>
                        {canManage && (
                            <Button icon={<Plus size={16} />} onClick={() => router.visit('/logistics/objectives/create')}>
                                Nouvel objectif
                            </Button>
                        )}
                    </div>
                </div>

                <Card padding={false}>
                    <div className="p-5">
                        <DataTable
                            data={objectives}
                            columns={[
                                { key: 'period_type', label: 'Type', render: (o) => <Badge variant={TYPE_VARIANT[o.period_type]}>{TYPE_LABEL[o.period_type]}</Badge> },
                                { key: 'start_date', label: 'Période', render: (o) => <span className="whitespace-nowrap">{o.start_date} → {o.end_date}</span> },
                                { key: 'target_tons', label: 'Objectif tonnage', render: (o) => <span className="font-mono">{fmt(o.target_tons)} t</span> },
                                { key: 'target_rotations', label: 'Rotations', render: (o) => <span className="font-mono">{fmt(o.target_rotations)}</span> },
                                { key: 'working_trucks', label: 'Camions', hideOnMobile: true, render: (o) => o.working_trucks },
                                { key: 'created_by', label: 'Créé par', hideOnMobile: true, render: (o) => o.created_by ?? '—' },
                                {
                                    key: 'actions', label: 'Actions', sortable: false, render: (o) => canManage ? (
                                        <div className="flex items-center gap-1">
                                            <button onClick={() => router.visit(`/logistics/objectives/create?objective=${o.id}`)} title="Modifier" className="p-1.5 rounded-lg text-[var(--color-primary)] hover:bg-[var(--color-primary)]/10 cursor-pointer">
                                                <Pencil size={15} />
                                            </button>
                                            <button onClick={() => setArchiveTarget(o)} title={o.archived ? 'Réactiver' : 'Archiver'} className="p-1.5 rounded-lg text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] cursor-pointer">
                                                {o.archived ? <ArchiveRestore size={15} /> : <Archive size={15} />}
                                            </button>
                                        </div>
                                    ) : null,
                                },
                            ]}
                            searchable
                            emptyMessage="Aucun objectif défini."
                        />
                    </div>
                </Card>
            </div>

            <ConfirmDialog
                open={!!archiveTarget}
                onClose={() => setArchiveTarget(null)}
                title={archiveTarget?.archived ? 'Réactiver l’objectif' : 'Archiver l’objectif'}
                message={archiveTarget?.archived ? 'L’objectif redeviendra actif et sera pris en compte dans le suivi.' : 'L’objectif sera conservé pour l’historique mais exclu du suivi.'}
                confirmLabel={archiveTarget?.archived ? 'Réactiver' : 'Archiver'}
                onConfirm={confirmArchive}
            />
        </AuthenticatedLayout>
    );
}
