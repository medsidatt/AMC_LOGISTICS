import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Button from '@/components/ui/Button';
import Modal from '@/components/ui/Modal';
import FormInput from '@/components/ui/FormInput';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import Pagination from '@/components/ui/Pagination';
import { Plus } from 'lucide-react';

interface Driver {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    created_at: string | null;
}

interface Props {
    drivers: { data: Driver[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
}

export default function DriversIndex({ drivers }: Props) {
    const [modal, setModal] = useState<'create' | 'edit' | null>(null);
    const [selected, setSelected] = useState<Driver | null>(null);
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    const createForm = useForm({ name: '', email: '', phone: '', address: '' });
    const editForm = useForm({ name: '', email: '', phone: '', address: '' });

    const openEdit = (d: Driver) => {
        setSelected(d);
        editForm.setData({ name: d.name, email: d.email ?? '', phone: d.phone ?? '', address: d.address ?? '' });
        setModal('edit');
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

            <div className="flex justify-end mb-4">
                <Button icon={<Plus size={16} />} onClick={() => { createForm.reset(); setModal('create'); }}>
                    Ajouter
                </Button>
            </div>

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={drivers.data}
                        columns={[
                            { key: 'name', label: 'Nom' },
                            { key: 'email', label: 'Email', hideOnMobile: true },
                            { key: 'phone', label: 'Téléphone', hideOnMobile: true },
                            { key: 'address', label: 'Adresse', hideOnMobile: true },
                            {
                                key: 'actions', label: 'Actions', sortable: false,
                                render: (r) => (
                                    <ActionButtons
                                        viewHref={`/drivers/${r.id}/show-page`}
                                        onEdit={() => openEdit(r)}
                                        onDelete={() => setDeleteUrl(`/drivers/${r.id}/destroy`)}
                                    />
                                ),
                            },
                        ]}
                        perPage={drivers.per_page}
                        searchable
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
