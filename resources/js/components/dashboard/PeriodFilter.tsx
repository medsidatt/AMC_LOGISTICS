import { useState } from 'react';
import { router } from '@inertiajs/react';
import { clsx } from 'clsx';
import { Calendar } from 'lucide-react';

interface PeriodFilterProps {
    from: string;
    to: string;
    preset: 'day' | 'week' | 'month' | 'year' | 'custom';
    routeName?: string;
}

const PRESETS: { key: 'day' | 'week' | 'month' | 'year'; label: string }[] = [
    { key: 'day', label: 'Jour' },
    { key: 'week', label: 'Semaine' },
    { key: 'month', label: 'Mois' },
    { key: 'year', label: 'Année' },
];

export default function PeriodFilter({ from, to, preset, routeName = '/dashboard' }: PeriodFilterProps) {
    const [customMode, setCustomMode] = useState(preset === 'custom');
    const [customFrom, setCustomFrom] = useState(from);
    const [customTo, setCustomTo] = useState(to);

    const apply = (params: Record<string, string>) => {
        router.get(routeName, params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const choosePreset = (key: 'day' | 'week' | 'month' | 'year') => {
        setCustomMode(false);
        apply({ preset: key });
    };

    const applyCustom = () => {
        if (!customFrom || !customTo) return;
        apply({ from: customFrom, to: customTo });
    };

    return (
        <div className="flex flex-wrap items-center gap-2 mb-5 p-3 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl">
            <Calendar size={16} className="text-[var(--color-text-muted)]" />
            <span className="text-xs font-medium text-[var(--color-text-muted)] uppercase mr-1">Période</span>

            {PRESETS.map((p) => (
                <button
                    key={p.key}
                    type="button"
                    onClick={() => choosePreset(p.key)}
                    className={clsx(
                        'px-3 py-1.5 rounded-lg text-xs font-medium transition',
                        preset === p.key && !customMode
                            ? 'bg-[var(--color-primary)] text-white'
                            : 'bg-[var(--color-surface-hover)] text-[var(--color-text)] hover:bg-[var(--color-border)]',
                    )}
                >
                    {p.label}
                </button>
            ))}

            <button
                type="button"
                onClick={() => setCustomMode((v) => !v)}
                className={clsx(
                    'px-3 py-1.5 rounded-lg text-xs font-medium transition',
                    customMode || preset === 'custom'
                        ? 'bg-[var(--color-primary)] text-white'
                        : 'bg-[var(--color-surface-hover)] text-[var(--color-text)] hover:bg-[var(--color-border)]',
                )}
            >
                Personnalisé
            </button>

            {customMode && (
                <div className="flex items-center gap-2 ml-2">
                    <input
                        type="date"
                        value={customFrom}
                        onChange={(e) => setCustomFrom(e.target.value)}
                        className="px-2 py-1 text-xs rounded border border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text)]"
                    />
                    <span className="text-xs text-[var(--color-text-muted)]">→</span>
                    <input
                        type="date"
                        value={customTo}
                        onChange={(e) => setCustomTo(e.target.value)}
                        className="px-2 py-1 text-xs rounded border border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text)]"
                    />
                    <button
                        type="button"
                        onClick={applyCustom}
                        className="px-3 py-1 text-xs font-medium rounded bg-[var(--color-primary)] text-white hover:opacity-90"
                    >
                        Appliquer
                    </button>
                </div>
            )}

            <span className="ml-auto text-xs text-[var(--color-text-muted)]">
                {from} → {to}
            </span>
        </div>
    );
}
