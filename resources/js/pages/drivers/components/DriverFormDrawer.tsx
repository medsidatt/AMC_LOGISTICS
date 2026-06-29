import { useForm } from '@inertiajs/react';
import Drawer from '@/components/ui/Drawer';
import FormActions from '@/components/ui/FormActions';
import FormInput from '@/components/ui/FormInput';
import FormCheckbox from '@/components/ui/FormCheckbox';
import { User } from 'lucide-react';

export interface DriverEditData {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    is_active: boolean;
}

interface Props {
    mode: 'create' | 'edit';
    driver?: DriverEditData | null;
    onClose: () => void;
}

/**
 * Create / edit a driver inside the Conducteurs workspace — replaces the legacy
 * modal forms with the shared Drawer standard. Reuses the existing store/update
 * endpoints (validation unchanged); the redirect refreshes the list via Inertia.
 */
export default function DriverFormDrawer({ mode, driver, onClose }: Props) {
    const form = useForm({
        name: driver?.name ?? '',
        email: driver?.email ?? '',
        phone: driver?.phone ?? '',
        address: driver?.address ?? '',
        is_active: driver?.is_active ?? true,
    });

    const submit = () => {
        if (mode === 'create') form.post('/drivers/store', { preserveScroll: true, onSuccess: onClose });
        else if (driver) form.put(`/drivers/${driver.id}/update`, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Drawer
            open
            onClose={onClose}
            icon={<User size={18} className="text-[var(--color-primary)]" />}
            title={mode === 'create' ? 'Nouveau conducteur' : `Modifier ${driver?.name ?? ''}`}
            footer={
                <FormActions
                    onCancel={onClose}
                    onSubmit={submit}
                    submitLabel={mode === 'create' ? 'Créer' : 'Enregistrer'}
                    loading={form.processing}
                    disabled={form.data.name.trim() === ''}
                />
            }
        >
            <FormInput label="Nom" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} required autoFocus />
            <FormInput label="Email" type="email" name="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} />
            <FormInput label="Téléphone" name="phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} error={form.errors.phone} />
            <FormInput label="Adresse" name="address" value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} error={form.errors.address} />
            <FormCheckbox label="Actif (apparaît dans les dropdowns de rotation)" name="is_active" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} error={form.errors.is_active} />
        </Drawer>
    );
}
