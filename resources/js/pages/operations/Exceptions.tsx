import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import ExceptionsList, { type ExceptionItem } from '@/components/operations/ExceptionsList';

interface Props {
    items: ExceptionItem[];
}

export default function Exceptions({ items }: Props) {
    return (
        <AuthenticatedLayout title="Exceptions">
            <Head title="Exceptions" />
            <Card padding={false}>
                {items.length === 0 ? (
                    <p className="text-sm text-[var(--color-text-muted)] py-10 text-center">Aucune exception ouverte.</p>
                ) : (
                    <ExceptionsList items={items} padded />
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
