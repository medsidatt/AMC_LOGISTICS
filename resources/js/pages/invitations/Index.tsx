import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import Modal from '@/components/ui/Modal';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import Pagination from '@/components/ui/Pagination';
import { Plus } from 'lucide-react';

interface Invitation {
    id: number;
    email: string;
    role_name: string;
    is_used: boolean;
    expires_at: string;
    created_at: string | null;
}

interface Props {
    invitations: { data: Invitation[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    roles: { name: string }[];
}

export default function InvitationsIndex({ invitations, roles }: Props) {
    const [modal, setModal] = useState<'create' | 'edit' | null>(null);
    const [selected, setSelected] = useState<Invitation | null>(null);
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    const roleOpts = roles.map((r) => ({ value: r.name, label: r.name }));

    const createForm = useForm({ email: '', role_name: '' });
    const editForm = useForm({ email: '', role_name: '' });

    const openEdit = (inv: Invitation) => {
        setSelected(inv);
        editForm.setData({ email: inv.email, role_name: inv.role_name });
        setModal('edit');
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/auth/invitations/send', { onSuccess: () => { setModal(null); createForm.reset(); } });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selected) return;
        editForm.put(`/auth/invitations/update/${selected.id}`, { onSuccess: () => setModal(null) });
    };

    const isExpired = (date: string) => new Date(date) < new Date();

    return (
        <AuthenticatedLayout title="Invitations">
            <Head title="Invitations" />

            <div className="flex justify-end mb-4">
                <Button icon={<Plus size={16} />} onClick={() => { createForm.reset(); setModal('create'); }}>Inviter</Button>
            </div>

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={invitations.data}
                        columns={[
                            { key: 'email', label: 'Email' },
                            { key: 'role_name', label: 'Rôle', render: (r) => <Badge variant="primary">{r.role_name}</Badge> },
                            { key: 'is_used', label: 'Utilisée', render: (r) => (
                                <Badge variant={r.is_used ? 'muted' : 'success'}>{r.is_used ? 'Oui' : 'Non'}</Badge>
                            )},
                            { key: 'expires_at', label: 'Expiration', hideOnMobile: true, render: (r) => (
                                <Badge variant={isExpired(r.expires_at) ? 'danger' : 'info'}>
                                    {r.expires_at}
                                </Badge>
                            )},
                            { key: 'created_at', label: 'Envoyée le', hideOnMobile: true },
                            {
                                key: 'actions', label: 'Actions', sortable: false,
                                render: (r) => (
                                    <ActionButtons
                                        onEdit={() => openEdit(r)}
                                        onDelete={() => setDeleteUrl(`/auth/invitations/destroy/${r.id}`)}
                                    />
                                ),
                            },
                        ]}
                        perPage={invitations.per_page}
                        searchable
                        exportable
                    />
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={invitations} />
                </div>
            </Card>

            <Modal open={modal === 'create'} onClose={() => setModal(null)} title="Nouvelle invitation">
                <form onSubmit={submitCreate}>
                    <FormInput label="Email" type="email" name="email" value={createForm.data.email} onChange={(e) => createForm.setData('email', e.target.value)} error={createForm.errors.email} required autoFocus />
                    <FormSelect label="Rôle" options={roleOpts} value={createForm.data.role_name} onChange={(v) => createForm.setData('role_name', String(v ?? ''))} error={createForm.errors.role_name} required />
                    <div className="flex justify-end gap-2 mt-6">
                        <Button variant="secondary" onClick={() => setModal(null)}>Annuler</Button>
                        <Button type="submit" loading={createForm.processing}>Envoyer</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={modal === 'edit'} onClose={() => setModal(null)} title="Modifier invitation">
                <form onSubmit={submitEdit}>
                    <FormInput label="Email" type="email" name="email" value={editForm.data.email} onChange={(e) => editForm.setData('email', e.target.value)} error={editForm.errors.email} required autoFocus />
                    <FormSelect label="Rôle" options={roleOpts} value={editForm.data.role_name} onChange={(v) => editForm.setData('role_name', String(v ?? ''))} error={editForm.errors.role_name} required />
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
