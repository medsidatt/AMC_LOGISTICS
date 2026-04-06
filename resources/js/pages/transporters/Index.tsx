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

interface Transporter {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    website: string | null;
}

interface Props {
    transporters: { data: Transporter[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    filters: { search?: string };
}

export default function TransportersIndex({ transporters, filters }: Props) {
    const [modal, setModal] = useState<'create' | 'edit' | 'show' | null>(null);
    const [selected, setSelected] = useState<Transporter | null>(null);
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    const createForm = useForm({ name: '', phone: '', email: '', address: '', website: '' });
    const editForm = useForm({ name: '', phone: '', email: '', address: '', website: '' });

    const openEdit = (t: Transporter) => {
        setSelected(t);
        editForm.setData({ name: t.name, phone: t.phone ?? '', email: t.email ?? '', address: t.address ?? '', website: t.website ?? '' });
        setModal('edit');
    };

    const openShow = (t: Transporter) => { setSelected(t); setModal('show'); };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/transporters', { onSuccess: () => { setModal(null); createForm.reset(); } });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selected) return;
        editForm.put(`/transporters/${selected.id}`, { onSuccess: () => setModal(null) });
    };

    return (
        <AuthenticatedLayout title="Transporteurs">
            <Head title="Transporteurs" />

            <div className="flex justify-end mb-4">
                <Button icon={<Plus size={16} />} onClick={() => { createForm.reset(); setModal('create'); }}>Ajouter</Button>
            </div>

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={transporters.data}
                        columns={[
                            { key: 'name', label: 'Nom' },
                            { key: 'phone', label: 'Téléphone', hideOnMobile: true },
                            { key: 'email', label: 'Email', hideOnMobile: true },
                            { key: 'address', label: 'Adresse', hideOnMobile: true },
                            {
                                key: 'actions', label: 'Actions', sortable: false,
                                render: (r) => (
                                    <ActionButtons
                                        onView={() => openShow(r)}
                                        onEdit={() => openEdit(r)}
                                        onDelete={() => setDeleteUrl(`/transporters/${r.id}`)}
                                    />
                                ),
                            },
                        ]}
                        perPage={transporters.per_page}
                        searchable
                    />
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={transporters} />
                </div>
            </Card>

            <Modal open={modal === 'create'} onClose={() => setModal(null)} title="Nouveau transporteur">
                <form onSubmit={submitCreate}>
                    <FormInput label="Nom" name="name" value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} error={createForm.errors.name} required autoFocus />
                    <FormInput label="Téléphone" name="phone" value={createForm.data.phone} onChange={(e) => createForm.setData('phone', e.target.value)} error={createForm.errors.phone} />
                    <FormInput label="Email" type="email" name="email" value={createForm.data.email} onChange={(e) => createForm.setData('email', e.target.value)} error={createForm.errors.email} />
                    <FormInput label="Adresse" name="address" value={createForm.data.address} onChange={(e) => createForm.setData('address', e.target.value)} error={createForm.errors.address} />
                    <FormInput label="Site web" name="website" value={createForm.data.website} onChange={(e) => createForm.setData('website', e.target.value)} error={createForm.errors.website} />
                    <div className="flex justify-end gap-2 mt-6">
                        <Button variant="secondary" onClick={() => setModal(null)}>Annuler</Button>
                        <Button type="submit" loading={createForm.processing}>Créer</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={modal === 'edit'} onClose={() => setModal(null)} title="Modifier transporteur">
                <form onSubmit={submitEdit}>
                    <FormInput label="Nom" name="name" value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} error={editForm.errors.name} required autoFocus />
                    <FormInput label="Téléphone" name="phone" value={editForm.data.phone} onChange={(e) => editForm.setData('phone', e.target.value)} error={editForm.errors.phone} />
                    <FormInput label="Email" type="email" name="email" value={editForm.data.email} onChange={(e) => editForm.setData('email', e.target.value)} error={editForm.errors.email} />
                    <FormInput label="Adresse" name="address" value={editForm.data.address} onChange={(e) => editForm.setData('address', e.target.value)} error={editForm.errors.address} />
                    <FormInput label="Site web" name="website" value={editForm.data.website} onChange={(e) => editForm.setData('website', e.target.value)} error={editForm.errors.website} />
                    <div className="flex justify-end gap-2 mt-6">
                        <Button variant="secondary" onClick={() => setModal(null)}>Annuler</Button>
                        <Button type="submit" loading={editForm.processing}>Enregistrer</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={modal === 'show'} onClose={() => setModal(null)} title={selected?.name ?? 'Transporteur'}>
                {selected && (
                    <div className="space-y-3">
                        {[['Nom', selected.name], ['Téléphone', selected.phone], ['Email', selected.email], ['Adresse', selected.address], ['Site web', selected.website]].map(([label, value]) => (
                            <div key={label as string}>
                                <p className="text-xs text-[var(--color-text-muted)] uppercase">{label}</p>
                                <p className="text-sm text-[var(--color-text)]">{value || '-'}</p>
                            </div>
                        ))}
                    </div>
                )}
            </Modal>

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </AuthenticatedLayout>
    );
}
