import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { Plus, Trash2 } from 'lucide-react';
import { clsx } from 'clsx';

interface CalendarDayRow {
    id: number;
    date: string;
    day_type: 'WORKING_DAY' | 'HOLIDAY' | 'SHUTDOWN' | 'EXCEPTION';
    note: string | null;
}

export interface CalendarPanelData {
    calendar: { id: number; name: string; working_weekdays: number[] };
    days: CalendarDayRow[];
}

const WEEKDAYS: { iso: number; label: string }[] = [
    { iso: 1, label: 'Lun' }, { iso: 2, label: 'Mar' }, { iso: 3, label: 'Mer' },
    { iso: 4, label: 'Jeu' }, { iso: 5, label: 'Ven' }, { iso: 6, label: 'Sam' }, { iso: 7, label: 'Dim' },
];

const TYPE_LABEL: Record<CalendarDayRow['day_type'], string> = {
    WORKING_DAY: 'Jour travaillé', HOLIDAY: 'Férié', SHUTDOWN: 'Arrêt', EXCEPTION: 'Exception',
};
const TYPE_VARIANT: Record<CalendarDayRow['day_type'], 'success' | 'danger' | 'warning' | 'muted'> = {
    WORKING_DAY: 'success', HOLIDAY: 'danger', SHUTDOWN: 'warning', EXCEPTION: 'muted',
};

const today = () => new Date().toISOString().slice(0, 10);

/**
 * Operational calendar — presentational. Shared by the standalone
 * /settings/operations-calendar page and the Planning workspace section. All
 * writes post via the single calendar save paths (back()).
 */
export default function CalendarPanel({ calendar, days }: CalendarPanelData) {
    const [weekdays, setWeekdays] = useState<Set<number>>(new Set(calendar.working_weekdays));
    const [savingWeekdays, setSavingWeekdays] = useState(false);
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    const addForm = useForm({ date: today(), day_type: 'HOLIDAY', note: '' });

    const toggleWeekday = (iso: number) =>
        setWeekdays((prev) => { const n = new Set(prev); n.has(iso) ? n.delete(iso) : n.add(iso); return n; });

    const saveWeekdays = () => {
        setSavingWeekdays(true);
        router.put('/settings/operations-calendar/weekdays',
            { working_weekdays: [...weekdays].sort() },
            { preserveScroll: true, onFinish: () => setSavingWeekdays(false) });
    };

    const submitDay = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post('/settings/operations-calendar/days', { preserveScroll: true, onSuccess: () => addForm.reset('note') });
    };

    return (
        <div className="space-y-5">
            {/* Working week */}
            <Card header={<span className="text-sm font-semibold">Jours ouvrés</span>}>
                <div className="flex flex-wrap gap-2">
                    {WEEKDAYS.map((d) => (
                        <button
                            key={d.iso}
                            type="button"
                            aria-pressed={weekdays.has(d.iso)}
                            onClick={() => toggleWeekday(d.iso)}
                            className={clsx(
                                'px-4 py-2 rounded-lg text-sm font-medium border transition-colors cursor-pointer',
                                weekdays.has(d.iso)
                                    ? 'bg-[var(--color-primary)] text-white border-[var(--color-primary)]'
                                    : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)]',
                            )}
                        >
                            {d.label}
                        </button>
                    ))}
                </div>
                <div className="flex justify-end mt-4">
                    <Button onClick={saveWeekdays} loading={savingWeekdays} disabled={weekdays.size === 0}>Enregistrer</Button>
                </div>
            </Card>

            {/* Exception days */}
            <Card header={<span className="text-sm font-semibold">Jours exceptionnels (fériés, arrêts, exceptions)</span>}>
                <form onSubmit={submitDay} className="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end mb-5">
                    <FormInput label="Date" type="date" value={addForm.data.date} onChange={(e) => addForm.setData('date', e.target.value)} error={addForm.errors.date} wrapperClass="mb-0" required />
                    <FormSelect
                        label="Type"
                        options={[
                            { value: 'HOLIDAY', label: 'Férié' },
                            { value: 'SHUTDOWN', label: 'Arrêt' },
                            { value: 'EXCEPTION', label: 'Exception' },
                            { value: 'WORKING_DAY', label: 'Jour travaillé' },
                        ]}
                        value={addForm.data.day_type}
                        onChange={(v) => addForm.setData('day_type', String(v ?? 'HOLIDAY'))}
                        error={addForm.errors.day_type}
                        wrapperClass="mb-0"
                    />
                    <FormInput label="Note" value={addForm.data.note} onChange={(e) => addForm.setData('note', e.target.value)} error={addForm.errors.note} wrapperClass="mb-0" />
                    <Button type="submit" icon={<Plus size={16} />} loading={addForm.processing}>Ajouter</Button>
                </form>

                {days.length === 0 ? (
                    <p className="text-sm text-[var(--color-text-muted)]">Aucun jour exceptionnel.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)] text-[11px] uppercase tracking-wide text-[var(--color-text-secondary)]">
                                    <th className="px-4 py-2.5 text-left font-semibold">Date</th>
                                    <th className="px-4 py-2.5 text-left font-semibold">Type</th>
                                    <th className="px-4 py-2.5 text-left font-semibold">Note</th>
                                    <th className="px-4 py-2.5 text-right font-semibold w-16"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {days.map((d) => (
                                    <tr key={d.id}>
                                        <td className="px-4 py-2.5 font-mono">{d.date}</td>
                                        <td className="px-4 py-2.5"><Badge variant={TYPE_VARIANT[d.day_type]}>{TYPE_LABEL[d.day_type]}</Badge></td>
                                        <td className="px-4 py-2.5 text-[var(--color-text-secondary)]">{d.note ?? '—'}</td>
                                        <td className="px-4 py-2.5 text-right">
                                            <button onClick={() => setDeleteUrl(`/settings/operations-calendar/days/${d.id}`)} title="Supprimer" className="p-1.5 rounded-lg text-[var(--color-danger)] hover:bg-[var(--color-danger)]/10 cursor-pointer">
                                                <Trash2 size={15} />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </div>
    );
}
