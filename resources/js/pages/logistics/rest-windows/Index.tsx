import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { Plus } from 'lucide-react';

interface NameRef { id: number; matricule?: string; name?: string }

interface RestWindow {
    id: number;
    truck: NameRef | null;
    start_date: string;
    end_date: string;
    reason: string;
    reason_label: string;
    notes: string | null;
    creator: NameRef | null;
}

interface Props {
    from: string;
    to: string;
    windows: RestWindow[];
    reasons: Record<string, string>;
}

export default function RestWindowsIndex({ from, to, windows }: Props) {
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    return (
        <AuthenticatedLayout title="Fenêtres de repos">
            <Head title="Fenêtres de repos" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div className="text-sm text-[var(--color-text-muted)]">
                    Du {from} au {to}
                </div>
                <Button icon={<Plus size={16} />} onClick={() => router.get('/logistics/rest-windows/create')}>Nouvelle fenêtre</Button>
            </div>

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={windows}
                        columns={[
                            { key: 'truck', label: 'Camion', render: (r) => r.truck?.matricule ?? '—' },
                            { key: 'start_date', label: 'Début' },
                            { key: 'end_date', label: 'Fin' },
                            { key: 'reason_label', label: 'Raison', render: (r) => <Badge variant="muted">{r.reason_label}</Badge> },
                            { key: 'creator', label: 'Créé par', render: (r) => r.creator?.name ?? '—' },
                            {
                                key: 'actions', label: '', sortable: false,
                                render: (r) => (
                                    <ActionButtons onDelete={() => setDeleteUrl(`/logistics/rest-windows/${r.id}`)} />
                                ),
                            },
                        ]}
                        searchable
                    />
                </div>
            </Card>

            <ConfirmDialog
                open={deleteUrl !== null}
                title="Supprimer cette fenêtre ?"
                onClose={() => setDeleteUrl(null)}
                onConfirm={() => {
                    if (deleteUrl) router.delete(deleteUrl);
                    setDeleteUrl(null);
                }}
            />
        </AuthenticatedLayout>
    );
}
