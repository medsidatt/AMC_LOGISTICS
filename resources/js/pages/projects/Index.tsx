import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Button from '@/components/ui/Button';
import Modal from '@/components/ui/Modal';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import Pagination from '@/components/ui/Pagination';
import { Plus } from 'lucide-react';

interface Project {
    id: number;
    name: string;
    code: string;
    description: string | null;
    logo: string | null;
    start_date: string | null;
    end_date: string | null;
    entity: { id: number; name: string; logo: string | null } | null;
}

interface Props {
    projects: { data: Project[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    entities: { id: number; name: string }[];
}

export default function ProjectsIndex({ projects, entities }: Props) {
    const [modal, setModal] = useState<'create' | 'edit' | null>(null);
    const [selected, setSelected] = useState<Project | null>(null);
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    const entityOpts = entities.map((e) => ({ value: e.id, label: e.name }));

    const createForm = useForm({ name: '', code: '', description: '', entity_id: '' as string | number, start_date: '', end_date: '' });
    const editForm = useForm({ name: '', code: '', description: '', entity_id: '' as string | number, start_date: '', end_date: '' });

    const openEdit = (p: Project) => {
        setSelected(p);
        editForm.setData({ name: p.name, code: p.code, description: p.description ?? '', entity_id: p.entity?.id ?? '', start_date: p.start_date ?? '', end_date: p.end_date ?? '' });
        setModal('edit');
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/projects', { onSuccess: () => { setModal(null); createForm.reset(); } });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selected) return;
        editForm.put(`/projects/${selected.id}`, { onSuccess: () => setModal(null) });
    };

    return (
        <AuthenticatedLayout title="Projets">
            <Head title="Projets" />

            <div className="flex justify-end mb-4">
                <Button icon={<Plus size={16} />} onClick={() => { createForm.reset(); setModal('create'); }}>Ajouter</Button>
            </div>

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={projects.data}
                        columns={[
                            { key: 'name', label: 'Nom' },
                            { key: 'code', label: 'Code' },
                            { key: 'entity', label: 'Entité', hideOnMobile: true, render: (r) => r.entity?.name ?? '-' },
                            { key: 'start_date', label: 'Début', hideOnMobile: true },
                            { key: 'end_date', label: 'Fin', hideOnMobile: true },
                            {
                                key: 'actions', label: 'Actions', sortable: false,
                                render: (r) => (
                                    <ActionButtons
                                        viewHref={`/projects/${r.id}`}
                                        onEdit={() => openEdit(r)}
                                        onDelete={() => setDeleteUrl(`/projects/${r.id}`)}
                                    />
                                ),
                            },
                        ]}
                        perPage={projects.per_page}
                        searchable
                    />
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={projects} />
                </div>
            </Card>

            <Modal open={modal === 'create'} onClose={() => setModal(null)} title="Nouveau projet">
                <form onSubmit={submitCreate}>
                    <FormInput label="Nom" name="name" value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} error={createForm.errors.name} required autoFocus />
                    <FormInput label="Code" name="code" value={createForm.data.code} onChange={(e) => createForm.setData('code', e.target.value)} error={createForm.errors.code} required />
                    <FormSelect label="Entité" options={entityOpts} value={createForm.data.entity_id} onChange={(v) => createForm.setData('entity_id', v ?? '')} error={createForm.errors.entity_id} />
                    <FormInput label="Description" name="description" value={createForm.data.description} onChange={(e) => createForm.setData('description', e.target.value)} error={createForm.errors.description} />
                    <FormInput label="Date début" type="date" name="start_date" value={createForm.data.start_date} onChange={(e) => createForm.setData('start_date', e.target.value)} error={createForm.errors.start_date} />
                    <FormInput label="Date fin" type="date" name="end_date" value={createForm.data.end_date} onChange={(e) => createForm.setData('end_date', e.target.value)} error={createForm.errors.end_date} />
                    <div className="flex justify-end gap-2 mt-6">
                        <Button variant="secondary" onClick={() => setModal(null)}>Annuler</Button>
                        <Button type="submit" loading={createForm.processing}>Créer</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={modal === 'edit'} onClose={() => setModal(null)} title="Modifier projet">
                <form onSubmit={submitEdit}>
                    <FormInput label="Nom" name="name" value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} error={editForm.errors.name} required autoFocus />
                    <FormInput label="Code" name="code" value={editForm.data.code} onChange={(e) => editForm.setData('code', e.target.value)} error={editForm.errors.code} required />
                    <FormSelect label="Entité" options={entityOpts} value={editForm.data.entity_id} onChange={(v) => editForm.setData('entity_id', v ?? '')} error={editForm.errors.entity_id} />
                    <FormInput label="Description" name="description" value={editForm.data.description} onChange={(e) => editForm.setData('description', e.target.value)} error={editForm.errors.description} />
                    <FormInput label="Date début" type="date" name="start_date" value={editForm.data.start_date} onChange={(e) => editForm.setData('start_date', e.target.value)} error={editForm.errors.start_date} />
                    <FormInput label="Date fin" type="date" name="end_date" value={editForm.data.end_date} onChange={(e) => editForm.setData('end_date', e.target.value)} error={editForm.errors.end_date} />
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
