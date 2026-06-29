import { type ReactNode } from 'react';
import { useForm } from '@inertiajs/react';
import Drawer from '@/components/ui/Drawer';
import FormActions from '@/components/ui/FormActions';
import FormInput from '@/components/ui/FormInput';
import SectionTitle from '@/components/ui/SectionTitle';
import type { Counterparty } from './types';

interface Props {
    mode: 'create' | 'edit';
    /** Route prefix, e.g. '/providers' or '/transporters'. */
    basePath: string;
    /** Singular masculine label, e.g. 'fournisseur' / 'transporteur'. */
    entityLabel: string;
    icon: ReactNode;
    record?: Counterparty | null;
    onClose: () => void;
    onSaved: () => void;
}

/**
 * Shared create/edit drawer for contact "counterparty" master-data (Providers,
 * Transporters). The form fields + submit wiring are identical across these
 * modules (proven duplication), so they live here; modules pass only their
 * basePath/label/icon. Posts to the existing {basePath}/store and
 * {basePath}/{id}/update endpoints — validation stays server-side and unchanged.
 */
export default function CounterpartyFormDrawer({ mode, basePath, entityLabel, icon, record, onClose, onSaved }: Props) {
    const form = useForm({
        name: record?.name ?? '',
        phone: record?.phone ?? '',
        email: record?.email ?? '',
        address: record?.address ?? '',
        website: record?.website ?? '',
    });

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: onSaved };
        if (mode === 'create') form.post(`${basePath}/store`, opts);
        else if (record) form.put(`${basePath}/${record.id}/update`, opts);
    };

    return (
        <Drawer
            open
            onClose={onClose}
            size="md"
            icon={icon}
            title={mode === 'create' ? `Nouveau ${entityLabel}` : `Modifier — ${record?.name ?? ''}`}
            footer={<FormActions onCancel={onClose} onSubmit={submit} submitLabel={mode === 'create' ? `Créer le ${entityLabel}` : 'Enregistrer'} loading={form.processing} disabled={!form.data.name.trim()} />}
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
