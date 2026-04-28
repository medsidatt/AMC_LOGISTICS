import { Head, useForm, Link } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import Modal from '@/components/ui/Modal';
import FormTextarea from '@/components/ui/FormTextarea';
import { ShieldCheck } from 'lucide-react';

interface Row {
    id: number;
    inspection_date: string | null;
    truck: string | null;
    inspector: string | null;
    category: string;
    issues_count: number;
    critical_count: number;
}

interface Props {
    inspections: { data: Row[] };
}

export default function PendingInspections({ inspections }: Props) {
    const [target, setTarget] = useState<Row | null>(null);
    const [decision, setDecision] = useState<'validated' | 'rejected'>('validated');
    const form = useForm({ decision: 'validated', validation_notes: '' });

    const open = (row: Row, dec: 'validated' | 'rejected') => {
        setTarget(row);
        setDecision(dec);
        form.setData({ decision: dec, validation_notes: '' });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!target) return;
        form.post(`/logistics/validation/inspections/${target.id}/validate`, {
            onSuccess: () => setTarget(null),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Inspections HSE en attente" />
            <div className="space-y-4">
                <div className="flex items-center gap-2">
                    <ShieldCheck size={22} className="text-emerald-500" />
                    <h1 className="text-xl font-semibold">Inspections HSE en attente</h1>
                </div>

                <Card>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="text-left border-b border-[var(--color-border)]">
                                    <th className="py-2 px-3">Date</th>
                                    <th className="py-2 px-3">Camion</th>
                                    <th className="py-2 px-3">Inspecteur</th>
                                    <th className="py-2 px-3">Catégorie</th>
                                    <th className="py-2 px-3">Issues</th>
                                    <th className="py-2 px-3">Critiques</th>
                                    <th className="py-2 px-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {inspections.data.length === 0 ? (
                                    <tr><td colSpan={7} className="py-6 text-center text-[var(--color-text-muted)]">Aucune inspection en attente.</td></tr>
                                ) : inspections.data.map((row) => (
                                    <tr key={row.id} className="border-b border-[var(--color-border)]">
                                        <td className="py-2 px-3">{row.inspection_date}</td>
                                        <td className="py-2 px-3">{row.truck ?? '—'}</td>
                                        <td className="py-2 px-3">{row.inspector ?? '—'}</td>
                                        <td className="py-2 px-3">{row.category}</td>
                                        <td className="py-2 px-3">{row.issues_count}</td>
                                        <td className="py-2 px-3">{row.critical_count > 0 ? <Badge variant="danger">{row.critical_count}</Badge> : '—'}</td>
                                        <td className="py-2 px-3 flex gap-2">
                                            <Link href={`/hse/inspections/${row.id}`} className="text-[var(--color-primary)] hover:underline text-xs">Voir</Link>
                                            <Button size="sm" onClick={() => open(row, 'validated')}>Valider</Button>
                                            <Button size="sm" variant="secondary" onClick={() => open(row, 'rejected')}>Rejeter</Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

            <Modal isOpen={!!target} onClose={() => setTarget(null)} title={decision === 'validated' ? 'Valider l\'inspection' : 'Rejeter l\'inspection'}>
                <form onSubmit={submit} className="space-y-3">
                    <FormTextarea
                        label="Notes (optionnel)"
                        value={form.data.validation_notes}
                        onChange={(e) => form.setData('validation_notes', e.target.value)}
                        rows={3}
                    />
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="secondary" onClick={() => setTarget(null)}>Annuler</Button>
                        <Button type="submit" disabled={form.processing}>{decision === 'validated' ? 'Valider' : 'Rejeter'}</Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
