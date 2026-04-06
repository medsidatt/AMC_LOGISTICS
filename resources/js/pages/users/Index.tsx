import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import Modal from '@/components/ui/Modal';
import FormInput from '@/components/ui/FormInput';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import Pagination from '@/components/ui/Pagination';
import { Plus, Ban, CheckCircle2 } from 'lucide-react';

interface Role {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    is_suspended: boolean;
    roles: Role[];
    created_at: string | null;
}

interface Props {
    users: { data: User[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    roles: Role[];
}

export default function UsersIndex({ users, roles }: Props) {
    const [modal, setModal] = useState<'create' | 'edit' | 'show' | null>(null);
    const [selected, setSelected] = useState<User | null>(null);
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    const createForm = useForm({ name: '', email: '', roles: [] as number[] });
    const editForm = useForm({ name: '', email: '', roles: [] as number[] });

    const openEdit = (u: User) => {
        setSelected(u);
        editForm.setData({ name: u.name, email: u.email, roles: u.roles.map((r) => r.id) });
        setModal('edit');
    };

    const openShow = (u: User) => { setSelected(u); setModal('show'); };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/users/store', { onSuccess: () => { setModal(null); createForm.reset(); } });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selected) return;
        editForm.put(`/users/update/${selected.id}`, { onSuccess: () => setModal(null) });
    };

    const toggleRole = (form: typeof createForm, roleId: number) => {
        const current = form.data.roles;
        form.setData('roles', current.includes(roleId) ? current.filter((r) => r !== roleId) : [...current, roleId]);
    };

    const RoleCheckboxes = ({ form }: { form: typeof createForm }) => (
        <div className="mb-4">
            <label className="block text-sm font-medium text-[var(--color-text)] mb-2">Rôles</label>
            <div className="flex flex-wrap gap-2">
                {roles.map((role) => (
                    <label key={role.id} className="flex items-center gap-2 rounded-lg border border-[var(--color-border)] px-3 py-2 text-sm cursor-pointer hover:bg-[var(--color-surface-hover)] transition">
                        <input type="checkbox" checked={form.data.roles.includes(role.id)} onChange={() => toggleRole(form, role.id)} className="rounded" />
                        <span className="text-[var(--color-text)]">{role.name}</span>
                    </label>
                ))}
            </div>
            {form.errors.roles && <p className="mt-1 text-xs text-[var(--color-danger)]">{form.errors.roles}</p>}
        </div>
    );

    return (
        <AuthenticatedLayout title="Utilisateurs">
            <Head title="Utilisateurs" />

            <div className="flex justify-end mb-4">
                <Button icon={<Plus size={16} />} onClick={() => { createForm.reset(); setModal('create'); }}>Ajouter</Button>
            </div>

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={users.data}
                        columns={[
                            { key: 'name', label: 'Nom' },
                            { key: 'email', label: 'Email', hideOnMobile: true },
                            { key: 'roles', label: 'Rôles', render: (r) => (
                                <div className="flex flex-wrap gap-1">
                                    {r.roles.map((role) => <Badge key={role.id} variant="primary">{role.name}</Badge>)}
                                </div>
                            )},
                            { key: 'is_suspended', label: 'Statut', render: (r) => (
                                <Badge variant={r.is_suspended ? 'danger' : 'success'}>{r.is_suspended ? 'Suspendu' : 'Actif'}</Badge>
                            )},
                            {
                                key: 'actions', label: 'Actions', sortable: false,
                                render: (r) => (
                                    <div className="flex items-center gap-1">
                                        <ActionButtons
                                            onView={() => openShow(r)}
                                            onEdit={() => openEdit(r)}
                                            onDelete={() => setDeleteUrl(`/users/destroy/${r.id}`)}
                                        />
                                        <button
                                            onClick={() => router.get(`/users/suspend/${r.id}`)}
                                            className="p-1.5 rounded-lg transition-colors text-[var(--color-warning)] hover:bg-[var(--color-warning)]/10"
                                            title={r.is_suspended ? 'Activer' : 'Suspendre'}
                                        >
                                            {r.is_suspended ? <CheckCircle2 size={14} /> : <Ban size={14} />}
                                        </button>
                                    </div>
                                ),
                            },
                        ]}
                        perPage={users.per_page}
                        searchable
                    />
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={users} />
                </div>
            </Card>

            <Modal open={modal === 'create'} onClose={() => setModal(null)} title="Nouvel utilisateur">
                <form onSubmit={submitCreate}>
                    <FormInput label="Nom" name="name" value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} error={createForm.errors.name} required autoFocus />
                    <FormInput label="Email" type="email" name="email" value={createForm.data.email} onChange={(e) => createForm.setData('email', e.target.value)} error={createForm.errors.email} required />
                    <RoleCheckboxes form={createForm} />
                    <div className="flex justify-end gap-2 mt-6">
                        <Button variant="secondary" onClick={() => setModal(null)}>Annuler</Button>
                        <Button type="submit" loading={createForm.processing}>Créer</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={modal === 'edit'} onClose={() => setModal(null)} title="Modifier utilisateur">
                <form onSubmit={submitEdit}>
                    <FormInput label="Nom" name="name" value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} error={editForm.errors.name} required autoFocus />
                    <FormInput label="Email" type="email" name="email" value={editForm.data.email} onChange={(e) => editForm.setData('email', e.target.value)} error={editForm.errors.email} required />
                    <RoleCheckboxes form={editForm} />
                    <div className="flex justify-end gap-2 mt-6">
                        <Button variant="secondary" onClick={() => setModal(null)}>Annuler</Button>
                        <Button type="submit" loading={editForm.processing}>Enregistrer</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={modal === 'show'} onClose={() => setModal(null)} title={selected?.name ?? 'Utilisateur'}>
                {selected && (
                    <div className="space-y-3">
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Nom</p>
                            <p className="text-sm text-[var(--color-text)]">{selected.name}</p>
                        </div>
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Email</p>
                            <p className="text-sm text-[var(--color-text)]">{selected.email}</p>
                        </div>
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Rôles</p>
                            <div className="flex flex-wrap gap-1 mt-1">
                                {selected.roles.map((r) => <Badge key={r.id} variant="primary">{r.name}</Badge>)}
                            </div>
                        </div>
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Statut</p>
                            <Badge variant={selected.is_suspended ? 'danger' : 'success'}>{selected.is_suspended ? 'Suspendu' : 'Actif'}</Badge>
                        </div>
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Créé le</p>
                            <p className="text-sm text-[var(--color-text)]">{selected.created_at ?? '-'}</p>
                        </div>
                    </div>
                )}
            </Modal>

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </AuthenticatedLayout>
    );
}
