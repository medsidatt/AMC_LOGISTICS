import { clsx } from 'clsx';

type Period = 'today' | 'week' | 'month' | 'year';

interface QuickFiltersProps {
    value: Period;
    onChange: (period: Period) => void;
}

const options: { value: Period; label: string }[] = [
    { value: 'today', label: "Aujourd'hui" },
    { value: 'week', label: 'Semaine' },
    { value: 'month', label: 'Mois' },
    { value: 'year', label: 'Année' },
];

export default function QuickFilters({ value, onChange }: QuickFiltersProps) {
    return (
        <div className="inline-flex items-center gap-1 p-1 rounded-xl bg-[var(--color-surface-hover)]">
            {options.map((opt) => (
                <button
                    key={opt.value}
                    onClick={() => onChange(opt.value)}
                    className={clsx(
                        'px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200',
                        value === opt.value
                            ? 'bg-[var(--color-primary)] text-white shadow-sm'
                            : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text)]',
                    )}
                >
                    {opt.label}
                </button>
            ))}
        </div>
    );
}
