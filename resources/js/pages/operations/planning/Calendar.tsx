import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import CalendarPanel, { type CalendarPanelData } from '@/components/operations/CalendarPanel';

export default function PlanningCalendar(props: CalendarPanelData) {
    return (
        <AuthenticatedLayout title="Calendrier opérationnel">
            <Head title="Calendrier opérationnel" />
            <div className="max-w-3xl">
                <CalendarPanel {...props} />
            </div>
        </AuthenticatedLayout>
    );
}
