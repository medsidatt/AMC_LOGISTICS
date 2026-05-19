import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import FormTextarea from '@/components/ui/FormTextarea';
import { ArrowLeft } from 'lucide-react';

interface Truck { id: number; matricule: string }

interface Props {
    trucks: Truck[];
    reasons: Record<string, string>;
}

export default function RestWindowsCreate({ trucks, reasons }: Props) {
    const form = useForm({
        truck_id: '' as string | number,
        start_date: '',
        end_date: '',
        reason: 'driver_rest',
        notes: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/logistics/rest-windows');
    };

    return (
        <AuthenticatedLayout title="Nouvelle fenêtre de repos">
            <Head title="Nouvelle fenêtre de repos" />

            <div className="mb-4">
                <Button variant="ghost" icon={<ArrowLeft size={16} />} onClick={() => window.history.back()}>Retour</Button>
            </div>

            <Card>
                <form onSubmit={submit} className="max-w-lg space-y-1">
                    <FormSelect
                        label="Camion"
                        options={trucks.map((t) => ({ value: t.id, label: t.matricule }))}
                        value={form.data.truck_id || null}
                        onChange={(v) => form.setData('truck_id', v ?? '')}
                        error={form.errors.truck_id}
                        required
                    />
                    <div className="grid grid-cols-2 gap-3">
                        <FormInput
                            label="Date début"
                            name="start_date"
                            type="date"
                            value={form.data.start_date}
                            onChange={(e) => form.setData('start_date', e.target.value)}
                            error={form.errors.start_date}
                            required
                        />
                        <FormInput
                            label="Date fin"
                            name="end_date"
                            type="date"
                            value={form.data.end_date}
                            onChange={(e) => form.setData('end_date', e.target.value)}
                            error={form.errors.end_date}
                            required
                        />
                    </div>
                    <FormSelect
                        label="Raison"
                        options={Object.entries(reasons).map(([v, label]) => ({ value: v, label }))}
                        value={form.data.reason}
                        onChange={(v) => form.setData('reason', String(v ?? 'driver_rest'))}
                        error={form.errors.reason}
                        required
                    />
                    <FormTextarea
                        label="Notes"
                        name="notes"
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        error={form.errors.notes}
                    />
                    <div className="flex gap-2 pt-4">
                        <Button variant="secondary" onClick={() => window.history.back()}>Annuler</Button>
                        <Button type="submit" loading={form.processing}>Créer</Button>
                    </div>
                </form>
            </Card>
        </AuthenticatedLayout>
    );
}
