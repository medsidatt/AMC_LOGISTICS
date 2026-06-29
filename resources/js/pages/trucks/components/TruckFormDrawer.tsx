import { useForm } from '@inertiajs/react';
import Drawer from '@/components/ui/Drawer';
import FormActions from '@/components/ui/FormActions';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import FormCheckbox from '@/components/ui/FormCheckbox';
import FormTextarea from '@/components/ui/FormTextarea';
import { Truck as TruckIcon } from 'lucide-react';

export interface TruckEditData {
    id: number;
    matricule: string;
    transporter_id: number | null;
    km_maintenance_interval: number | null;
    target_rotations_per_week: number | null;
    is_available: boolean;
}

interface Props {
    mode: 'create' | 'edit';
    truck?: TruckEditData | null;
    transporters: { value: number; label: string }[];
    defaultCapacityTonnage: number;
    defaultTargetRotationsPerWeek: number;
    onClose: () => void;
}

/**
 * Create / edit a truck inside the Trucks workspace — no page navigation. Posts to
 * the existing store/update endpoints (unchanged validation). On success the server
 * redirect refreshes the list via Inertia; on validation error the drawer stays open
 * with field errors (preserveState). The change-note appears only when a rotations
 * objective is set/changed, mirroring the backend's audit requirement.
 */
export default function TruckFormDrawer({
    mode, truck, transporters, defaultCapacityTonnage, defaultTargetRotationsPerWeek, onClose,
}: Props) {
    const form = useForm({
        matricule: truck?.matricule ?? '',
        transporter_id: (truck?.transporter_id ?? '') as number | string,
        km_maintenance_interval: (truck?.km_maintenance_interval ?? '') as number | string,
        target_rotations_per_week: (truck?.target_rotations_per_week ?? '') as number | string,
        is_available: truck?.is_available ?? true,
        change_note: '',
    });

    const initialTarget = mode === 'edit' ? (truck?.target_rotations_per_week ?? null) : null;
    const targetProvided = String(form.data.target_rotations_per_week ?? '') !== '';
    const targetChanged = String(form.data.target_rotations_per_week ?? '') !== String(initialTarget ?? '');
    const showChangeNote = mode === 'create' ? targetProvided : targetChanged;

    const submit = () => {
        const opts = { preserveScroll: true, preserveState: true as const, onSuccess: () => onClose() };
        if (mode === 'create') form.post('/trucks/store', opts);
        else if (truck) form.put(`/trucks/${truck.id}/update`, opts);
    };

    const canSubmit = String(form.data.matricule).trim() !== '' && String(form.data.transporter_id) !== '';

    return (
        <Drawer
            open
            onClose={onClose}
            icon={<TruckIcon size={18} className="text-[var(--color-primary)]" />}
            title={mode === 'create' ? 'Nouveau camion' : `Modifier ${truck?.matricule ?? ''}`}
            footer={
                <FormActions
                    onCancel={onClose}
                    onSubmit={submit}
                    submitLabel={mode === 'create' ? 'Créer' : 'Enregistrer'}
                    loading={form.processing}
                    disabled={!canSubmit}
                />
            }
        >
            <FormInput
                label="Matricule" name="matricule" value={form.data.matricule}
                onChange={(e) => form.setData('matricule', e.target.value)}
                error={form.errors.matricule} required autoFocus
            />
            <FormSelect
                label="Transporteur" options={transporters} value={form.data.transporter_id}
                onChange={(v) => form.setData('transporter_id', v ?? '')}
                error={form.errors.transporter_id} required
            />
            <FormInput
                label="Intervalle maintenance (km)" name="km_maintenance_interval" type="number"
                value={form.data.km_maintenance_interval}
                onChange={(e) => form.setData('km_maintenance_interval', e.target.value)}
                error={form.errors.km_maintenance_interval}
            />

            <div className="mb-4">
                <label className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">Capacité (tonnes)</label>
                <div className="px-3 py-2 text-sm rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-hover)] text-[var(--color-text-muted)]">
                    {defaultCapacityTonnage} t — valeur unique de la flotte
                </div>
                <p className="text-xs text-[var(--color-text-muted)] mt-1">
                    La capacité est la même pour toute la flotte. Modifiez-la dans{' '}
                    <a href="/settings/fleet" className="text-[var(--color-primary)] hover:underline">Paramètres flotte</a>.
                </p>
            </div>

            <FormInput
                label="Rotations cibles par semaine (optionnel)" name="target_rotations_per_week"
                type="number" min="1" max="14"
                placeholder={`Défaut flotte : ${defaultTargetRotationsPerWeek}`}
                value={form.data.target_rotations_per_week}
                onChange={(e) => form.setData('target_rotations_per_week', e.target.value)}
                error={form.errors.target_rotations_per_week}
            />
            <FormCheckbox
                label="Disponible" name="is_available" checked={form.data.is_available}
                onChange={(e) => form.setData('is_available', e.target.checked)}
                error={form.errors.is_available}
            />

            {showChangeNote && (
                <div>
                    <FormTextarea
                        label="Justification de l'objectif de rotations"
                        value={form.data.change_note}
                        onChange={(e) => form.setData('change_note', e.target.value)}
                        rows={3} required
                        error={form.errors.change_note}
                        placeholder="Ex : camion neuf avec benne renforcée, capacité revue à la hausse."
                        wrapperClass="mb-1"
                    />
                    <p className="text-xs text-[var(--color-text-muted)]">
                        Cette note est archivée dans l'historique des objectifs (preuve d'audit).
                    </p>
                </div>
            )}
        </Drawer>
    );
}
