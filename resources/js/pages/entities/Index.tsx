import { Head, useForm, router } from '@inertiajs/react';
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

interface Entity {
    id: number;
    name: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    logo: string | null;
}

interface Props {
    entities: { data: Entity[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    filters: { search?: string };
}

export default function EntitiesIndex({ entities, filters }: Props) {
    const [modal, setModal] = useState<'create' | 'edit' | 'show' | null>(null);
    const [selected, setSelected] = useState<Entity | null>(null);
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    const createForm = useForm({ name: '', phone: '', email: '', address: '', website: '', logo: null as File | null });
    const editForm = useForm({ name: '', phone: '', email: '', address: '', website: '', logo: null as File | null });

    const openEdit = (e: Entity) => {
        setSelected(e);
        editForm.setData({ name: e.name, phone: e.phone ?? '', email: e.email ?? '', address: e.address ?? '', website: e.website ?? '', logo: null });
        setModal('edit');
    };

    const openShow = (e: Entity) => { setSelected(e); setModal('show'); };

    const submitCreate = (ev: React.FormEvent) => {
        ev.preventDefault();
        router.post('/entities', createForm.data as any, { forceFormData: true, onSuccess: () => { setModal(null); createForm.reset(); } });
    };

    const submitEdit = (ev: React.FormEvent) => {
        ev.preventDefault();
        if (!selected) return;
        router.post(`/entities/${selected.id}`, { ...editForm.data, _method: 'PUT' } as any, { forceFormData: true, onSuccess: () => setModal(null) });
    };

    return (
        <AuthenticatedLayout title="Entités">
            <Head title="Entités" />

            <div className="flex justify-end mb-4">
                <Button icon={<Plus size={16} />} onClick={() => { createForm.reset(); setModal('create'); }}>Ajouter</Button>
            </div>

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={entities.data}
                        columns={[
                            { key: 'logo', label: '', sortable: false, render: (r) => r.logo ? <img src={r.logo} alt="" className="w-8 h-8 rounded-full object-cover" /> : <div className="w-8 h-8 rounded-full bg-[var(--color-surface-hover)]" /> },
                            { key: 'name', label: 'Nom' },
                            { key: 'phone', label: 'Téléphone', hideOnMobile: true },
                            { key: 'email', label: 'Email', hideOnMobile: true },
                            {
                                key: 'actions', label: 'Actions', sortable: false,
                                render: (r) => (
                                    <ActionButtons
                                        onView={() => openShow(r)}
                                        onEdit={() => openEdit(r)}
                                        onDelete={() => setDeleteUrl(`/entities/destroy/${r.id}`)}
                                    />
                                ),
                            },
                        ]}
                        perPage={entities.per_page}
                        searchable
                    />
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={entities} />
                </div>
            </Card>

            <Modal open={modal === 'create'} onClose={() => setModal(null)} title="Nouvelle entité">
                <form onSubmit={submitCreate}>
                    <FormInput label="Nom" name="name" value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} error={createForm.errors.name} required autoFocus />
                    <FormInput label="Téléphone" name="phone" value={createForm.data.phone} onChange={(e) => createForm.setData('phone', e.target.value)} error={createForm.errors.phone} />
                    <FormInput label="Email" type="email" name="email" value={createForm.data.email} onChange={(e) => createForm.setData('email', e.target.value)} error={createForm.errors.email} />
                    <FormInput label="Adresse" name="address" value={createForm.data.address} onChange={(e) => createForm.setData('address', e.target.value)} error={createForm.errors.address} />
                    <FormInput label="Site web" name="website" value={createForm.data.website} onChange={(e) => createForm.setData('website', e.target.value)} error={createForm.errors.website} />
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-[var(--color-text)] mb-1">Logo</label>
                        <input type="file" accept="image/*" onChange={(e) => createForm.setData('logo', e.target.files?.[0] ?? null)}
                            className="block w-full text-sm text-[var(--color-text-secondary)] file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[var(--color-primary)]/10 file:text-[var(--color-primary)] hover:file:bg-[var(--color-primary)]/20" />
                    </div>
                    <div className="flex justify-end gap-2 mt-6">
                        <Button variant="secondary" onClick={() => setModal(null)}>Annuler</Button>
                        <Button type="submit" loading={createForm.processing}>Créer</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={modal === 'edit'} onClose={() => setModal(null)} title="Modifier entité">
                <form onSubmit={submitEdit}>
                    <FormInput label="Nom" name="name" value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} error={editForm.errors.name} required autoFocus />
                    <FormInput label="Téléphone" name="phone" value={editForm.data.phone} onChange={(e) => editForm.setData('phone', e.target.value)} error={editForm.errors.phone} />
                    <FormInput label="Email" type="email" name="email" value={editForm.data.email} onChange={(e) => editForm.setData('email', e.target.value)} error={editForm.errors.email} />
                    <FormInput label="Adresse" name="address" value={editForm.data.address} onChange={(e) => editForm.setData('address', e.target.value)} error={editForm.errors.address} />
                    <FormInput label="Site web" name="website" value={editForm.data.website} onChange={(e) => editForm.setData('website', e.target.value)} error={editForm.errors.website} />
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-[var(--color-text)] mb-1">Logo</label>
                        {selected?.logo && <img src={selected.logo} alt="" className="w-12 h-12 rounded-full object-cover mb-2" />}
                        <input type="file" accept="image/*" onChange={(e) => editForm.setData('logo', e.target.files?.[0] ?? null)}
                            className="block w-full text-sm text-[var(--color-text-secondary)] file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[var(--color-primary)]/10 file:text-[var(--color-primary)] hover:file:bg-[var(--color-primary)]/20" />
                    </div>
                    <div className="flex justify-end gap-2 mt-6">
                        <Button variant="secondary" onClick={() => setModal(null)}>Annuler</Button>
                        <Button type="submit" loading={editForm.processing}>Enregistrer</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={modal === 'show'} onClose={() => setModal(null)} title={selected?.name ?? 'Entité'}>
                {selected && (
                    <div className="space-y-3">
                        {selected.logo && <img src={selected.logo} alt="" className="w-16 h-16 rounded-full object-cover" />}
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
