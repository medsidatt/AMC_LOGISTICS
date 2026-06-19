import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import FormCheckbox from '@/components/ui/FormCheckbox';
import { ArrowLeft } from 'lucide-react';

interface Props {
    transporters: { value: number; label: string }[];
    defaultTargetRotationsPerWeek: number;
    defaultCapacityTonnage: number;
}

export default function TrucksCreate({ transporters, defaultTargetRotationsPerWeek, defaultCapacityTonnage }: Props) {
    const form = useForm({
        matricule: '',
        transporter_id: '' as string | number,
        km_maintenance_interval: '',
        target_rotations_per_week: '',
        is_available: true,
        change_note: '',
    });

    const objectiveProvided = form.data.target_rotations_per_week !== '';

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
                    <div className="mb-3">
                        <label className="block text-sm font-medium text-[var(--color-text-secondary)] mb-1">Capacité (tonnes)</label>
                        <div className="px-3 py-2 text-sm rounded border border-[var(--color-border)] bg-[var(--color-surface-hover)] text-[var(--color-text-muted)]">
                            {defaultCapacityTonnage} t — valeur unique de la flotte
                        </div>
                        <p className="text-xs text-[var(--color-text-muted)] mt-1">
                            La capacité est la même pour toute la flotte. Modifiez-la dans <a href="/settings/fleet" className="text-[var(--color-primary)] hover:underline">Paramètres flotte</a>.
                        </p>
                    </div>
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
                    <FormCheckbox label="Disponible dès la création" name="is_available" checked={form.data.is_available} onChange={(e) => form.setData('is_available', e.target.checked)} error={form.errors.is_available} />

                    {objectiveProvided && (
                        <div className="border-t border-[var(--color-border)] mt-4 pt-4">
                            <p className="text-xs uppercase tracking-wider font-semibold text-[var(--color-text-muted)] mb-2">
                                Justification de l'objectif de rotations
                            </p>
                            <textarea
                                className="w-full px-3 py-2 text-sm rounded border border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text)]"
                                rows={3}
                                placeholder="Ex : camion neuf avec benne renforcée, capacité revue à la hausse."
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
                        <Button type="submit" loading={form.processing}>Créer</Button>
                    </div>
                </form>
            </Card>
        </AuthenticatedLayout>
    );
}
