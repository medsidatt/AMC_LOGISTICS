import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import DispatchBoard, { type DispatchBoardData } from '@/components/operations/DispatchBoard';

export default function Dispatch(props: DispatchBoardData) {
    return (
        <AuthenticatedLayout title="Répartition">
            <Head title="Répartition" />
            <DispatchBoard
                {...props}
                onGotoDate={(iso) => router.get('/dispatch', { date: iso }, { preserveState: false })}
                weeklyHref="/realisation"
            />
        </AuthenticatedLayout>
    );
}
