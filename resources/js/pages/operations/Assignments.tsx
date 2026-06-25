import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import AssignmentPanel, { type AssignmentPanelData } from '@/components/operations/AssignmentPanel';

export default function Assignments(props: AssignmentPanelData) {
    return (
        <AuthenticatedLayout title="Affectations">
            <Head title="Affectations" />
            <AssignmentPanel {...props} />
        </AuthenticatedLayout>
    );
}
