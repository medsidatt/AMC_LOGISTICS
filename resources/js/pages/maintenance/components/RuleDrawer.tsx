import { useForm } from '@inertiajs/react';
import Drawer from '@/components/ui/Drawer';
import FormActions from '@/components/ui/FormActions';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import { SlidersHorizontal } from 'lucide-react';
import type { MaintenanceTypeOpt } from '../types';

interface Props {
    trucks: { id: number; matricule: string }[];
    maintenanceTypes: MaintenanceTypeOpt[];
    onClose: () => void;
}

/** Create a maintenance interval rule (TruckMaintenanceProfile). Posts to the
 * existing /maintenance/rules endpoint. Interval is immutable after creation. */
export default function RuleDrawer({ trucks, maintenanceTypes, onClose }: Props) {
    const form = useForm<Record<string, any>>({
        truck_id: '' as string | number,
        maintenance_type: 'general',
        interval_km: '',
        warning_threshold_km: '',
    });

    const submit = () => form.post('/maintenance/rules', { preserveScroll: true, onSuccess: onClose });

    const truckOpts = trucks.map((t) => ({ value: t.id, label: t.matricule }));

    return (
        <Drawer
            open
            onClose={onClose}
            icon={<SlidersHorizontal size={18} className="text-[var(--color-primary)]" />}
            title="Nouvelle règle de maintenance"
            footer={<FormActions onCancel={onClose} onSubmit={submit} submitLabel="Créer la règle" loading={form.processing} disabled={!form.data.truck_id || form.data.interval_km === ''} />}
        >
            <FormSelect label="Camion" options={truckOpts} value={form.data.truck_id} onChange={(v) => form.setData('truck_id', v ?? '')} error={form.errors.truck_id} required />
            <FormSelect label="Type" options={maintenanceTypes} value={form.data.maintenance_type} onChange={(v) => form.setData('maintenance_type', String(v ?? ''))} error={form.errors.maintenance_type} required />
            <FormInput label="Intervalle (km)" type="number" min="1" value={form.data.interval_km} onChange={(e) => form.setData('interval_km', e.target.value)} error={form.errors.interval_km} required />
            <FormInput label="Seuil d'alerte (km avant échéance)" type="number" min="0" value={form.data.warning_threshold_km} onChange={(e) => form.setData('warning_threshold_km', e.target.value)} error={form.errors.warning_threshold_km} />
            <p className="text-xs text-[var(--color-text-muted)]">L'intervalle est définitif après création (créez une nouvelle règle pour le modifier).</p>
        </Drawer>
    );
}
