import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import { ArrowLeft } from 'lucide-react';

interface Props {
    driver: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        created_at: string | null;
        updated_at: string | null;
    };
}

export default function DriversShow({ driver }: Props) {
    const fields = [
        ['Nom', driver.name],
        ['Email', driver.email],
        ['Téléphone', driver.phone],
        ['Adresse', driver.address],
        ['Créé le', driver.created_at],
        ['Modifié le', driver.updated_at],
    ];

    return (
        <AuthenticatedLayout title={driver.name}>
            <Head title={driver.name} />

            <div className="mb-4">
                <Button variant="ghost" icon={<ArrowLeft size={16} />} onClick={() => window.history.back()}>
                    Retour
                </Button>
            </div>

            <Card>
                <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Informations conducteur</h3>
                <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    {fields.map(([label, value]) => (
                        <div key={label as string}>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">{label}</p>
                            <p className="text-sm text-[var(--color-text)] mt-0.5">{value || '-'}</p>
                        </div>
                    ))}
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
