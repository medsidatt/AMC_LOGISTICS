import { router } from '@inertiajs/react';
import { useState } from 'react';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import DataTable from '@/components/ui/DataTable';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { Plus, Pencil, Archive, ArchiveRestore } from 'lucide-react';
import type { PlanningMode } from '@/types/achievement';

export interface ObjectiveRow {
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
    objectives: ObjectiveRow[];
    canManage: boolean;
}

const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR');
const TYPE_LABEL: Record<PlanningMode, string> = { WEEK: 'Semaine', MONTH: 'Mois', YEAR: 'Année', CUSTOM: 'Personnalisé' };
const TYPE_VARIANT: Record<PlanningMode, 'primary' | 'info' | 'success' | 'muted'> = { WEEK: 'primary', MONTH: 'info', YEAR: 'success', CUSTOM: 'muted' };

/**
 * Objectives list (commitments) — presentational. Shared by the standalone
 * /logistics/objectives page and the Planning workspace section. Archive uses
 * back(); create/edit open the authoring page.
 */
export default function ObjectivesPanel({ objectives, canManage }: Props) {
    const [archiveTarget, setArchiveTarget] = useState<ObjectiveRow | null>(null);

    const confirmArchive = () => {
        if (!archiveTarget) return;
        router.post(`/logistics/objectives/${archiveTarget.id}/archive`, {}, { preserveScroll: true, onFinish: () => setArchiveTarget(null) });
    };

    return (
        <div className="space-y-3">
            {canManage && (
                <div className="flex justify-end">
                    <Button icon={<Plus size={16} />} onClick={() => router.visit('/logistics/objectives/create')}>
                        Nouvel objectif
                    </Button>
                </div>
            )}

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
                        emptyMessage="Aucun objectif défini."
                    />
                </div>
            </Card>

            <ConfirmDialog
                open={!!archiveTarget}
                onClose={() => setArchiveTarget(null)}
                title={archiveTarget?.archived ? 'Réactiver l’objectif' : 'Archiver l’objectif'}
                message={archiveTarget?.archived ? 'L’objectif redeviendra actif et sera pris en compte dans le suivi.' : 'L’objectif sera conservé pour l’historique mais exclu du suivi.'}
                confirmLabel={archiveTarget?.archived ? 'Réactiver' : 'Archiver'}
                onConfirm={confirmArchive}
            />
        </div>
    );
}
