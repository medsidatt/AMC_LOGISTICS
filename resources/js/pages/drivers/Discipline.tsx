import { Head, useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import FormInput from '@/components/ui/FormInput';
import { ArrowLeft, Trash2 } from 'lucide-react';

interface Record {
    id: number;
    recorded_at: string | null;
    recorded_at_display: string | null;
    points: number;
    reason: string;
    recorded_by: string | null;
}

interface Props {
    driver: { id: number; name: string };
    records: Record[];
    totals: { sum: number; count: number };
}

export default function DriverDiscipline({ driver, records, totals }: Props) {
    const today = new Date().toISOString().slice(0, 10);
    const form = useForm({
        recorded_at: today,
        points: '',
        reason: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/drivers/${driver.id}/discipline`, {
            preserveScroll: true,
            onSuccess: () => form.reset('points', 'reason'),
        });
    };

    const remove = (id: number) => {
        if (!confirm('Supprimer cette entrée ?')) return;
        router.delete(`/drivers/discipline/${id}`, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout title={`Discipline — ${driver.name}`}>
            <Head title={`Discipline — ${driver.name}`} />

            <div className="mb-4">
                <Button variant="ghost" icon={<ArrowLeft size={16} />} onClick={() => window.history.back()}>
                    Retour
                </Button>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <Card>
                    <p className="text-xs text-[var(--color-text-muted)] uppercase">Total cumulé</p>
                    <p className={`text-3xl font-bold mt-1 ${totals.sum >= 0 ? 'text-[var(--color-success)]' : 'text-[var(--color-danger)]'}`}>
                        {totals.sum > 0 ? '+' : ''}{totals.sum} pts
                    </p>
                    <p className="text-xs text-[var(--color-text-muted)] mt-1">{totals.count} entrée(s) historique(s)</p>
                </Card>

                <Card className="lg:col-span-2" header={<span className="text-sm font-semibold">Ajouter une entrée</span>}>
                    <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <FormInput
                            label="Date"
                            type="date"
                            name="recorded_at"
                            value={form.data.recorded_at}
                            onChange={(e) => form.setData('recorded_at', e.target.value)}
                            error={form.errors.recorded_at}
                            required
                        />
                        <FormInput
                            label="Points (+/-)"
                            type="number"
                            name="points"
                            value={form.data.points}
                            onChange={(e) => form.setData('points', e.target.value)}
                            error={form.errors.points}
                            required
                        />
                        <div className="md:col-span-3">
                            <FormInput
                                label="Motif"
                                name="reason"
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                                error={form.errors.reason}
                                required
                            />
                        </div>
                        <div className="md:col-span-3 flex justify-end">
                            <Button type="submit" loading={form.processing}>Ajouter</Button>
                        </div>
                    </form>
                </Card>
            </div>

            <Card className="mt-4" header={<span className="text-sm font-semibold">Historique</span>}>
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-xs text-[var(--color-text-muted)] uppercase border-b border-[var(--color-border)]">
                            <th className="text-left py-2">Date</th>
                            <th className="text-left py-2">Points</th>
                            <th className="text-left py-2">Motif</th>
                            <th className="text-left py-2">Par</th>
                            <th className="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {records.length === 0 && (
                            <tr><td colSpan={5} className="py-6 text-center text-[var(--color-text-muted)]">Aucune entrée.</td></tr>
                        )}
                        {records.map((r) => (
                            <tr key={r.id} className="border-b border-[var(--color-border)] last:border-0">
                                <td className="py-2">{r.recorded_at_display}</td>
                                <td className="py-2">
                                    <Badge variant={r.points >= 0 ? 'success' : 'danger'}>
                                        {r.points > 0 ? '+' : ''}{r.points}
                                    </Badge>
                                </td>
                                <td className="py-2">{r.reason}</td>
                                <td className="py-2 text-[var(--color-text-muted)]">{r.recorded_by ?? '-'}</td>
                                <td className="py-2 text-right">
                                    <button
                                        onClick={() => remove(r.id)}
                                        className="p-1.5 rounded hover:bg-[var(--color-surface-hover)] text-[var(--color-text-muted)] hover:text-[var(--color-danger)]"
                                    >
                                        <Trash2 size={14} />
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Card>
        </AuthenticatedLayout>
    );
}
