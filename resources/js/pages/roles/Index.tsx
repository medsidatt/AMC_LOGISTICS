import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import Pagination from '@/components/ui/Pagination';
import { Plus } from 'lucide-react';

interface Permission {
    id: number;
    name: string;
}

interface Role {
    id: number;
    name: string;
    guard_name: string;
    permissions: Permission[];
}

interface Props {
    roles: { data: Role[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
}

export default function RolesIndex({ roles }: Props) {
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    return (
        <AuthenticatedLayout title="Rôles">
            <Head title="Rôles" />

            <div className="flex justify-end mb-4">
                <Button icon={<Plus size={16} />} onClick={() => window.location.href = '/roles/create'}>Ajouter</Button>
            </div>

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={roles.data}
                        columns={[
                            { key: 'name', label: 'Nom' },
                            { key: 'permissions', label: 'Permissions', render: (r) => (
                                <div className="flex flex-wrap gap-1 max-w-md">
                                    {r.permissions.slice(0, 5).map((p) => <Badge key={p.id} variant="muted">{p.name}</Badge>)}
                                    {r.permissions.length > 5 && <Badge variant="info">+{r.permissions.length - 5}</Badge>}
                                </div>
                            )},
                            {
                                key: 'actions', label: 'Actions', sortable: false,
                                render: (r) => (
                                    <ActionButtons
                                        viewHref={`/roles/${r.id}`}
                                        editHref={`/roles/${r.id}/edit`}
                                        onDelete={() => setDeleteUrl(`/roles/${r.id}`)}
                                    />
                                ),
                            },
                        ]}
                        perPage={roles.per_page}
                        searchable
                    />
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={roles} />
                </div>
            </Card>

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </AuthenticatedLayout>
    );
}
