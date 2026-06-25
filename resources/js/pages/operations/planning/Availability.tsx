import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import AvailabilityPanel, { type AvailabilityPanelData } from '@/components/operations/AvailabilityPanel';

export default function PlanningAvailability(props: AvailabilityPanelData) {
    return (
        <AuthenticatedLayout title="Disponibilité flotte">
            <Head title="Disponibilité flotte" />
            <AvailabilityPanel
                {...props}
                onChangeMonth={(iso) => router.get('/planning/availability', { month: iso }, { preserveScroll: true })}
            />
        </AuthenticatedLayout>
    );
}
