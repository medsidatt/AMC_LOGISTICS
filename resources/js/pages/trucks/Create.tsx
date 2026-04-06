import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import { ArrowLeft } from 'lucide-react';

interface Props {
    transporters: { value: number; label: string }[];
}

export default function TrucksCreate({ transporters }: Props) {
    const form = useForm({ matricule: '', transporter_id: '' as string | number, km_maintenance_interval: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/trucks/store');
    };

    return (
        <AuthenticatedLayout title="Nouveau camion">
            <Head title="Nouveau camion" />

            <div className="mb-4">
                <Button variant="ghost" icon={<ArrowLeft size={16} />} onClick={() => window.history.back()}>
                    Retour
                </Button>
            </div>

            <Card>
                <form onSubmit={submit} className="max-w-lg space-y-1">
                    <FormInput label="Matricule" name="matricule" value={form.data.matricule} onChange={(e) => form.setData('matricule', e.target.value)} error={form.errors.matricule} required autoFocus />
                    <FormSelect label="Transporteur" options={transporters} value={form.data.transporter_id} onChange={(v) => form.setData('transporter_id', v ?? '')} error={form.errors.transporter_id} required />
                    <FormInput label="Intervalle maintenance (km)" name="km_maintenance_interval" type="number" value={form.data.km_maintenance_interval} onChange={(e) => form.setData('km_maintenance_interval', e.target.value)} error={form.errors.km_maintenance_interval} />
                    <div className="flex gap-2 pt-4">
                        <Button variant="secondary" onClick={() => window.history.back()}>Annuler</Button>
                        <Button type="submit" loading={form.processing}>Créer</Button>
                    </div>
                </form>
            </Card>
        </AuthenticatedLayout>
    );
}
