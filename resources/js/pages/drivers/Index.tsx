import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import Modal from '@/components/ui/Modal';
import FormInput from '@/components/ui/FormInput';
import FormCheckbox from '@/components/ui/FormCheckbox';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import Pagination from '@/components/ui/Pagination';
import { Plus, Power, PowerOff } from 'lucide-react';
import { usePermission } from '@/hooks/usePermission';

interface Driver {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    is_active: boolean;
    created_at: string | null;
}

interface Props {
    drivers: { data: Driver[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    totals: { active: number; total: number };
}

export default function DriversIndex({ drivers, totals }: Props) {
    const [modal, setModal] = useState<'create' | 'edit' | null>(null);
    const [selected, setSelected] = useState<Driver | null>(null);
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);
    const { can } = usePermission();
    const canCreate = can('driver-create');
    const canEdit = can('driver-edit');
    const canDelete = can('driver-delete');

    const createForm = useForm({ name: '', email: '', phone: '', address: '', is_active: true });
    const editForm = useForm({ name: '', email: '', phone: '', address: '', is_active: true });

    const openEdit = (d: Driver) => {
        setSelected(d);
        editForm.setData({ name: d.name, email: d.email ?? '', phone: d.phone ?? '', address: d.address ?? '', is_active: d.is_active });
        setModal('edit');
    };

    const toggleActive = (d: Driver) => {
        router.post(`/drivers/${d.id}/toggle-active`, {}, { preserveScroll: true });
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/drivers/store', { onSuccess: () => { setModal(null); createForm.reset(); } });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selected) return;
        editForm.put(`/drivers/${selected.id}/update`, { onSuccess: () => setModal(null) });
    };

    return (
        <AuthenticatedLayout title="Conducteurs">
            <Head title="Conducteurs" />

            <div className="flex justify-between items-center mb-4">
                <div className="text-sm text-[var(--color-text-muted)]">
                    <span className="font-semibold text-[var(--color-success)]">{totals.active}</span> actifs sur <span className="font-semibold">{totals.total}</span> chauffeurs
                </div>
                {canCreate && (
                    <Button icon={<Plus size={16} />} onClick={() => { createForm.reset(); setModal('create'); }}>
                        Ajouter
                    </Button>
                )}
            </div>

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={drivers.data}
                        columns={[
                            { key: 'name', label: 'Nom' },
                            { key: 'is_active', label: 'Actif', render: (r) => <Badge variant={r.is_active ? 'success' : 'muted'}>{r.is_active ? 'Oui' : 'Non'}</Badge> },
                            { key: 'email', label: 'Email', hideOnMobile: true },
                            { key: 'phone', label: 'Téléphone', hideOnMobile: true },
                            { key: 'address', label: 'Adresse', hideOnMobile: true },
                            {
                                key: 'actions', label: 'Actions', sortable: false,
                                render: (r) => (
                                    <div className="flex items-center gap-1">
                                        {canEdit && (
                                            <button
                                                type="button"
                                                onClick={() => toggleActive(r)}
                                                title={r.is_active ? 'Désactiver' : 'Activer'}
                                                className="p-1.5 rounded hover:bg-[var(--color-surface-hover)] text-[var(--color-text-muted)]"
                                            >
                                                {r.is_active ? <PowerOff size={14} /> : <Power size={14} className="text-[var(--color-success)]" />}
                                            </button>
                                        )}
                                        <ActionButtons
                                            viewHref={`/drivers/${r.id}/show-page`}
                                            onEdit={canEdit ? () => openEdit(r) : undefined}
                                            onDelete={canDelete ? () => setDeleteUrl(`/drivers/${r.id}/destroy`) : undefined}
                                        />
                                    </div>
                                ),
                            },
                        ]}
                        perPage={drivers.per_page}
                        searchable
                        exportable
                    />
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={drivers} />
                </div>
            </Card>

            <Modal open={modal === 'create'} onClose={() => setModal(null)} title="Nouveau conducteur">
                <form onSubmit={submitCreate}>
                    <FormInput label="Nom" name="name" value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} error={createForm.errors.name} required autoFocus />
                    <FormInput label="Email" type="email" name="email" value={createForm.data.email} onChange={(e) => createForm.setData('email', e.target.value)} error={createForm.errors.email} />
                    <FormInput label="Téléphone" name="phone" value={createForm.data.phone} onChange={(e) => createForm.setData('phone', e.target.value)} error={createForm.errors.phone} />
                    <FormInput label="Adresse" name="address" value={createForm.data.address} onChange={(e) => createForm.setData('address', e.target.value)} error={createForm.errors.address} />
                    <FormCheckbox label="Actif (apparaît dans les dropdowns de rotation)" name="is_active" checked={createForm.data.is_active} onChange={(e) => createForm.setData('is_active', e.target.checked)} error={createForm.errors.is_active} />
                    <div className="flex justify-end gap-2 mt-6">
                        <Button variant="secondary" onClick={() => setModal(null)}>Annuler</Button>
                        <Button type="submit" loading={createForm.processing}>Créer</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={modal === 'edit'} onClose={() => setModal(null)} title="Modifier conducteur">
                <form onSubmit={submitEdit}>
                    <FormInput label="Nom" name="name" value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} error={editForm.errors.name} required autoFocus />
                    <FormInput label="Email" type="email" name="email" value={editForm.data.email} onChange={(e) => editForm.setData('email', e.target.value)} error={editForm.errors.email} />
                    <FormInput label="Téléphone" name="phone" value={editForm.data.phone} onChange={(e) => editForm.setData('phone', e.target.value)} error={editForm.errors.phone} />
                    <FormInput label="Adresse" name="address" value={editForm.data.address} onChange={(e) => editForm.setData('address', e.target.value)} error={editForm.errors.address} />
                    <FormCheckbox label="Actif" name="is_active" checked={editForm.data.is_active} onChange={(e) => editForm.setData('is_active', e.target.checked)} error={editForm.errors.is_active} />
                    <div className="flex justify-end gap-2 mt-6">
                        <Button variant="secondary" onClick={() => setModal(null)}>Annuler</Button>
                        <Button type="submit" loading={editForm.processing}>Enregistrer</Button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </AuthenticatedLayout>
    );
}
