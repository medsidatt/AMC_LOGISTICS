import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import { ShieldCheck, Plus } from 'lucide-react';

interface InspectionRow {
    id: number;
    inspection_date: string | null;
    truck: { id: number; matricule: string } | null;
    inspector: string | null;
    category: string;
    status: string;
    issues_count: number;
    validator: string | null;
    validated_at: string | null;
}

interface Props {
    inspections: {
        data: InspectionRow[];
        links: any[];
    };
    options: {
        categories: Record<string, string>;
        conditions: Record<string, string>;
    };
}

const STATUS_VARIANT: Record<string, 'default' | 'success' | 'warning' | 'danger'> = {
    draft: 'default',
    submitted: 'warning',
    validated: 'success',
    rejected: 'danger',
};

export default function InspectionsIndex({ inspections, options }: Props) {
    return (
        <AuthenticatedLayout>
            <Head title="Inspections HSE" />
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <ShieldCheck size={22} className="text-emerald-500" />
                        <h1 className="text-xl font-semibold">Inspections HSE</h1>
                    </div>
                    <Link href="/hse/inspections/create">
                        <Button>
                            <Plus size={16} className="mr-1" />
                            Nouvelle inspection
                        </Button>
                    </Link>
                </div>

                <Card>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="text-left border-b border-[var(--color-border)]">
                                    <th className="py-2 px-3">Date</th>
                                    <th className="py-2 px-3">Camion</th>
                                    <th className="py-2 px-3">Inspecteur</th>
                                    <th className="py-2 px-3">Catégorie</th>
                                    <th className="py-2 px-3">Statut</th>
                                    <th className="py-2 px-3">Issues</th>
                                    <th className="py-2 px-3">Validé par</th>
                                    <th className="py-2 px-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {inspections.data.length === 0 ? (
                                    <tr><td colSpan={8} className="py-6 text-center text-[var(--color-text-muted)]">Aucune inspection.</td></tr>
                                ) : inspections.data.map((row) => (
                                    <tr key={row.id} className="border-b border-[var(--color-border)]">
                                        <td className="py-2 px-3">{row.inspection_date}</td>
                                        <td className="py-2 px-3">{row.truck?.matricule ?? '—'}</td>
                                        <td className="py-2 px-3">{row.inspector ?? '—'}</td>
                                        <td className="py-2 px-3">{options.categories[row.category] ?? row.category}</td>
                                        <td className="py-2 px-3">
                                            <Badge variant={STATUS_VARIANT[row.status] ?? 'default'}>{row.status}</Badge>
                                        </td>
                                        <td className="py-2 px-3">{row.issues_count}</td>
                                        <td className="py-2 px-3">{row.validator ?? '—'}</td>
                                        <td className="py-2 px-3">
                                            <Link
                                                href={`/hse/inspections/${row.id}`}
                                                className="text-[var(--color-primary)] hover:underline"
                                            >
                                                Voir
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
