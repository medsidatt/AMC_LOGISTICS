import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, CalendarDays } from 'lucide-react';
import { clsx } from 'clsx';
import { useState } from 'react';
import type { PlanningMode } from '@/types/achievement';

const MODES: { value: PlanningMode; label: string }[] = [
    { value: 'WEEK', label: 'Semaine' },
    { value: 'MONTH', label: 'Mois' },
    { value: 'YEAR', label: 'Année' },
    { value: 'CUSTOM', label: 'Personnalisé' },
];

const BASE = '/logistics/planning/weekly';

function shift(iso: string, mode: PlanningMode, dir: number): string {
    const d = new Date(iso + 'T00:00:00');
    if (mode === 'WEEK') d.setDate(d.getDate() + dir * 7);
    else if (mode === 'MONTH') d.setMonth(d.getMonth() + dir);
    else if (mode === 'YEAR') d.setFullYear(d.getFullYear() + dir);
    return d.toISOString().slice(0, 10);
}

function periodLabel(mode: PlanningMode, start: string, end: string): string {
    const s = new Date(start + 'T00:00:00');
    if (mode === 'MONTH') return s.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
    if (mode === 'YEAR') return String(s.getFullYear());
    return `${start} → ${end}`;
}

/**
 * Period selector for the planning scoreboard: Week / Month / Year / Custom.
 * Navigation re-requests the same route with the resolved mode + anchor, keeping
 * the URL shareable.
 */
export default function PeriodSwitcher({ mode, period }: { mode: PlanningMode; period: { start: string; end: string } }) {
    const [customStart, setCustomStart] = useState(period.start);
    const [customEnd, setCustomEnd] = useState(period.end);

    const go = (params: Record<string, string>) =>
        router.get(BASE, params, { preserveState: false, preserveScroll: true });

    const setMode = (m: PlanningMode) =>
        m === 'CUSTOM' ? go({ mode: 'CUSTOM', start: customStart, end: customEnd }) : go({ mode: m, anchor: period.start });

    const nav = (dir: number) => go({ mode, anchor: shift(period.start, mode, dir) });

    const dateInput = 'px-2 py-1.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm text-[var(--color-text)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20';

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div role="group" aria-label="Type de période" className="inline-flex rounded-lg border border-[var(--color-border)] p-0.5 bg-[var(--color-surface)] self-start">
                {MODES.map((m) => (
                    <button
                        key={m.value}
                        type="button"
                        aria-pressed={mode === m.value}
                        onClick={() => setMode(m.value)}
                        className={clsx(
                            'px-3 py-1.5 text-sm font-medium rounded-md transition-colors cursor-pointer',
                            mode === m.value
                                ? 'bg-[var(--color-primary)] text-white shadow-sm'
                                : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)]',
                        )}
                    >
                        {m.label}
                    </button>
                ))}
            </div>

            {mode === 'CUSTOM' ? (
                <div className="flex items-center gap-2 flex-wrap">
                    <input type="date" aria-label="Date de début" value={customStart} onChange={(e) => setCustomStart(e.target.value)} className={dateInput} />
                    <span className="text-[var(--color-text-muted)]">→</span>
                    <input type="date" aria-label="Date de fin" value={customEnd} onChange={(e) => setCustomEnd(e.target.value)} className={dateInput} />
                    <button type="button" onClick={() => go({ mode: 'CUSTOM', start: customStart, end: customEnd })} className="px-3 py-1.5 text-sm font-medium rounded-lg bg-[var(--color-primary)] text-white hover:bg-[var(--color-primary-dark)] cursor-pointer">
                        Appliquer
                    </button>
                </div>
            ) : (
                <div className="flex items-center gap-2">
                    <button type="button" onClick={() => nav(-1)} aria-label="Période précédente" className="p-2 rounded-lg hover:bg-[var(--color-surface-hover)] cursor-pointer">
                        <ChevronLeft size={18} />
                    </button>
                    <span className="text-sm font-medium inline-flex items-center gap-1.5 min-w-[9rem] justify-center capitalize">
                        <CalendarDays size={14} className="text-[var(--color-text-muted)]" />
                        {periodLabel(mode, period.start, period.end)}
                    </span>
                    <button type="button" onClick={() => nav(1)} aria-label="Période suivante" className="p-2 rounded-lg hover:bg-[var(--color-surface-hover)] cursor-pointer">
                        <ChevronRight size={18} />
                    </button>
                </div>
            )}
        </div>
    );
}
