import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import DataTable from '@/components/ui/DataTable';

interface IssueFrequency {
    category: string;
    total: number;
    open_count: number;
}

interface Props {
    issueFrequency: IssueFrequency[];
    totalIssues: number;
    from: string;
}

export default function Reports({ issueFrequency, totalIssues, from }: Props) {
    return (
        <AuthenticatedLayout title="Rapports Logistique">
            <Head title="Rapports Logistique" />

            <div className="grid sm:grid-cols-2 gap-4 mb-6">
                <Card>
                    <p className="text-xs text-[var(--color-text-muted)] uppercase">Total problèmes</p>
                    <p className="text-2xl font-bold text-[var(--color-text)] mt-1">{totalIssues}</p>
                    <p className="text-xs text-[var(--color-text-muted)] mt-1">Depuis le {from}</p>
                </Card>
                <Card>
                    <p className="text-xs text-[var(--color-text-muted)] uppercase">Catégories</p>
                    <p className="text-2xl font-bold text-[var(--color-text)] mt-1">{issueFrequency.length}</p>
                </Card>
            </div>

            <Card>
                <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Fréquence des problèmes par catégorie</h3>
                <DataTable
                    data={issueFrequency}
                    columns={[
                        { key: 'category', label: 'Catégorie' },
                        { key: 'total', label: 'Total' },
                        { key: 'open_count', label: 'Ouverts', render: (r) => (
                            <Badge variant={r.open_count > 0 ? 'danger' : 'success'}>{r.open_count}</Badge>
                        )},
                        { key: 'resolved', label: 'Résolus', render: (r) => (
                            <Badge variant="success">{r.total - r.open_count}</Badge>
                        )},
                    ]}
                    searchable={false}
                    emptyMessage="Aucun problème signalé"
                />
            </Card>
        </AuthenticatedLayout>
    );
}
