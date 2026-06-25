import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import ObjectivesPanel, { type ObjectiveRow } from '@/components/operations/ObjectivesPanel';
import { usePermission } from '@/hooks/usePermission';

interface Props {
    objectives: ObjectiveRow[];
}

export default function PlanningObjectives({ objectives }: Props) {
    const { can } = usePermission();
    return (
        <AuthenticatedLayout title="Objectifs">
            <Head title="Objectifs" />
            <ObjectivesPanel objectives={objectives} canManage={can('fleet-roster-plan')} />
        </AuthenticatedLayout>
    );
}
