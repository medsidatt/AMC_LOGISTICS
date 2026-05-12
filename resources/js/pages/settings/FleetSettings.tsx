import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import { Settings } from 'lucide-react';

interface Props {
    setting: {
        monthly_target_tonnage: number;
        weight_gap_threshold: number;
    };
}

export default function FleetSettingsPage({ setting }: Props) {
    const form = useForm({
        monthly_target_tonnage: String(setting.monthly_target_tonnage ?? 0),
        weight_gap_threshold: String(setting.weight_gap_threshold ?? 0.5),
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put('/settings/fleet');
    };

    return (
        <AuthenticatedLayout title="Paramètres flotte">
            <Head title="Paramètres flotte" />

            <Card
                header={
                    <div className="flex items-center gap-2">
                        <Settings size={16} />
                        <span className="text-sm font-semibold">Paramètres KPI flotte</span>
                    </div>
                }
            >
                <form onSubmit={submit} className="max-w-lg space-y-1">
                    <FormInput
                        label="Objectif mensuel (tonnes)"
                        name="monthly_target_tonnage"
                        type="number"
                        step="0.01"
                        value={form.data.monthly_target_tonnage}
                        onChange={(e) => form.setData('monthly_target_tonnage', e.target.value)}
                        error={form.errors.monthly_target_tonnage}
                        required
                    />
                    <p className="text-xs text-[var(--color-text-muted)] mb-3 -mt-2">
                        Utilisé pour le KPI "Objectif de production". L'objectif est proratisé sur la période filtrée.
                    </p>
                    <FormInput
                        label="Seuil écart de poids (tonnes)"
                        name="weight_gap_threshold"
                        type="number"
                        step="0.01"
                        value={form.data.weight_gap_threshold}
                        onChange={(e) => form.setData('weight_gap_threshold', e.target.value)}
                        error={form.errors.weight_gap_threshold}
                        required
                    />
                    <p className="text-xs text-[var(--color-text-muted)] mb-3 -mt-2">
                        Au-delà de ce seuil, une rotation compte comme "écart" pour le score discipline chauffeur.
                    </p>
                    <div className="flex gap-2 pt-4">
                        <Button type="submit" loading={form.processing}>Enregistrer</Button>
                    </div>
                </form>
            </Card>
        </AuthenticatedLayout>
    );
}
