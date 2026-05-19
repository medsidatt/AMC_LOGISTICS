import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import FormCheckbox from '@/components/ui/FormCheckbox';
import { ArrowLeft } from 'lucide-react';

interface TruckData {
    id: number;
    matricule: string;
    transporter_id: number;
    maintenance_type: string;
    km_maintenance_interval: number;
    capacity_tonnage: number | null;
    target_rotations_per_week: number | null;
    is_active: boolean;
    is_available: boolean;
}

interface Props {
    truck: TruckData;
    transporters: { value: number; label: string }[];
    defaultTargetRotationsPerWeek: number;
}

export default function TrucksEdit({ truck, transporters, defaultTargetRotationsPerWeek }: Props) {
    const form = useForm({
        matricule: truck.matricule,
        transporter_id: truck.transporter_id as string | number,
        km_maintenance_interval: String(truck.km_maintenance_interval ?? ''),
        capacity_tonnage: String(truck.capacity_tonnage ?? ''),
        target_rotations_per_week: truck.target_rotations_per_week != null ? String(truck.target_rotations_per_week) : '',
        is_available: truck.is_available !== false,
        change_note: '',
    });

    const originalCapacity = truck.capacity_tonnage != null ? String(truck.capacity_tonnage) : '';
    const originalTargetRotations = truck.target_rotations_per_week != null ? String(truck.target_rotations_per_week) : '';
    const objectiveChanged =
        form.data.capacity_tonnage !== originalCapacity ||
        form.data.target_rotations_per_week !== originalTargetRotations;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/trucks/${truck.id}/update`);
    };

    return (
        <AuthenticatedLayout title="Modifier camion">
            <Head title="Modifier camion" />

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
                    <FormInput label="Capacité (tonnes)" name="capacity_tonnage" type="number" value={form.data.capacity_tonnage} onChange={(e) => form.setData('capacity_tonnage', e.target.value)} error={form.errors.capacity_tonnage} />
                    <FormInput
                        label="Rotations cibles par semaine (optionnel)"
                        name="target_rotations_per_week"
                        type="number"
                        min="1"
                        max="14"
                        placeholder={`Défaut flotte : ${defaultTargetRotationsPerWeek}`}
                        value={form.data.target_rotations_per_week}
                        onChange={(e) => form.setData('target_rotations_per_week', e.target.value)}
                        error={form.errors.target_rotations_per_week}
                    />
                    <p className="text-xs text-[var(--color-text-muted)] -mt-2 mb-3">
                        Laisser vide pour utiliser le défaut flotte ({defaultTargetRotationsPerWeek} rot./sem).
                    </p>
                    <FormCheckbox label="Disponible (camion utilisable pour les rotations)" name="is_available" checked={form.data.is_available} onChange={(e) => form.setData('is_available', e.target.checked)} error={form.errors.is_available} />

                    {objectiveChanged && (
                        <div className="border-t border-[var(--color-border)] mt-4 pt-4">
                            <p className="text-xs uppercase tracking-wider font-semibold text-[var(--color-text-muted)] mb-2">
                                Justification du changement d'objectif
                            </p>
                            <textarea
                                className="w-full px-3 py-2 text-sm rounded border border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text)]"
                                rows={3}
                                placeholder="Ex : capacité réduite après rapport HSE pneus."
                                value={form.data.change_note}
                                onChange={(e) => form.setData('change_note', e.target.value)}
                                required
                            />
                            {form.errors.change_note && (
                                <p className="text-xs text-red-500 mt-1">{form.errors.change_note}</p>
                            )}
                            <p className="text-xs text-[var(--color-text-muted)] mt-1">
                                Cette note est archivée dans l'historique des objectifs (preuve d'audit).
                            </p>
                        </div>
                    )}

                    <div className="flex gap-2 pt-4">
                        <Button variant="secondary" onClick={() => window.history.back()}>Annuler</Button>
                        <Button type="submit" loading={form.processing}>Enregistrer</Button>
                    </div>
                </form>
            </Card>
        </AuthenticatedLayout>
    );
}
