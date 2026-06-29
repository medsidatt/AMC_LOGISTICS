import { useForm } from '@inertiajs/react';
import Drawer from '@/components/ui/Drawer';
import FormActions from '@/components/ui/FormActions';
import FormInput from '@/components/ui/FormInput';
import SectionTitle from '@/components/ui/SectionTitle';
import { Building2 } from 'lucide-react';
import type { Provider } from '../types';

interface Props {
    mode: 'create' | 'edit';
    provider?: Provider | null;
    onClose: () => void;
    onSaved: () => void;
}

/**
 * Create / edit a provider inside the workspace — replaces the legacy create/edit
 * Modals. Posts to the existing /providers/store and /providers/{id}/update
 * endpoints (validation unchanged). Only the container moved to a Drawer.
 */
export default function ProviderFormDrawer({ mode, provider, onClose, onSaved }: Props) {
    const form = useForm({
        name: provider?.name ?? '',
        phone: provider?.phone ?? '',
        email: provider?.email ?? '',
        address: provider?.address ?? '',
        website: provider?.website ?? '',
    });

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: onSaved };
        if (mode === 'create') form.post('/providers/store', opts);
        else if (provider) form.put(`/providers/${provider.id}/update`, opts);
    };

    return (
        <Drawer
            open
            onClose={onClose}
            size="md"
            icon={<Building2 size={18} className="text-[var(--color-primary)]" />}
            title={mode === 'create' ? 'Nouveau fournisseur' : `Modifier — ${provider?.name ?? ''}`}
            footer={<FormActions onCancel={onClose} onSubmit={submit} submitLabel={mode === 'create' ? 'Créer le fournisseur' : 'Enregistrer'} loading={form.processing} disabled={!form.data.name.trim()} />}
        >
            <SectionTitle>Coordonnées</SectionTitle>
            <FormInput label="Nom" name="name" wrapperClass="mb-0" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} required autoFocus />
            <FormInput label="Téléphone" name="phone" wrapperClass="mb-0" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} error={form.errors.phone} />
            <FormInput label="Email" type="email" name="email" wrapperClass="mb-0" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} />
            <FormInput label="Adresse" name="address" wrapperClass="mb-0" value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} error={form.errors.address} />
            <FormInput label="Site web" name="website" wrapperClass="mb-0" value={form.data.website} onChange={(e) => form.setData('website', e.target.value)} error={form.errors.website} />
        </Drawer>
    );
}
