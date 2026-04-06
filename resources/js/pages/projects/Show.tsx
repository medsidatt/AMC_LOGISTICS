import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import DataTable from '@/components/ui/DataTable';
import { ArrowLeft } from 'lucide-react';

interface Props {
    project: {
        id: number;
        name: string;
        code: string;
        description: string | null;
        logo: string | null;
        start_date: string | null;
        end_date: string | null;
        address: string | null;
        phone: string | null;
        email: string | null;
        entity: { id: number; name: string } | null;
        users: { id: number; name: string; email: string; role: string | null }[];
    };
}

export default function ProjectsShow({ project }: Props) {
    return (
        <AuthenticatedLayout title={project.name}>
            <Head title={project.name} />

            <div className="mb-4">
                <Button variant="ghost" icon={<ArrowLeft size={16} />} onClick={() => window.history.back()}>Retour</Button>
            </div>

            <div className="grid gap-6">
                <Card>
                    <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Informations</h3>
                    <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {[
                            ['Nom', project.name],
                            ['Code', project.code],
                            ['Entité', project.entity?.name],
                            ['Date début', project.start_date],
                            ['Date fin', project.end_date],
                            ['Téléphone', project.phone],
                            ['Email', project.email],
                            ['Adresse', project.address],
                        ].map(([label, value]) => (
                            <div key={label as string}>
                                <p className="text-xs text-[var(--color-text-muted)] uppercase">{label}</p>
                                <p className="text-sm text-[var(--color-text)] mt-0.5">{value || '-'}</p>
                            </div>
                        ))}
                    </div>
                    {project.description && (
                        <div className="mt-4">
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Description</p>
                            <p className="text-sm text-[var(--color-text)] mt-0.5">{project.description}</p>
                        </div>
                    )}
                </Card>

                <Card>
                    <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Utilisateurs assignés</h3>
                    <DataTable
                        data={project.users}
                        columns={[
                            { key: 'name', label: 'Nom' },
                            { key: 'email', label: 'Email', hideOnMobile: true },
                            { key: 'role', label: 'Rôle', render: (r) => r.role ? <Badge variant="primary">{r.role}</Badge> : '-' },
                        ]}
                        searchable={false}
                        emptyMessage="Aucun utilisateur assigné"
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
