import { clsx } from 'clsx';
import StatusIcon, { StatusVariant } from './StatusIcon';

export interface BigChoiceOption {
    value: string;
    label: string;
    /** Drives the active color and the icon (success ✓ / warning ⚠ / danger ✗). */
    variant?: StatusVariant | 'primary';
}

const ACTIVE: Record<string, string> = {
    success: 'border-emerald-500 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    warning: 'border-amber-500 bg-amber-500/15 text-amber-700 dark:text-amber-300',
    danger: 'border-red-500 bg-red-500/15 text-red-700 dark:text-red-300',
    primary: 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-text)]',
};

/**
 * Large, finger-friendly choice buttons (min 56px) for mobile drivers.
 * Used for issue severity and other single-select options.
 */
export default function BigChoice({ value, options, onChange, disabled }: {
    value: string | null;
    options: BigChoiceOption[];
    onChange: (v: string) => void;
    disabled?: boolean;
}) {
    return (
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
            {options.map((o) => {
                const active = value === o.value;
                const variant = o.variant ?? 'primary';
                return (
                    <button
                        key={o.value}
                        type="button"
                        disabled={disabled}
                        onClick={() => onChange(o.value)}
                        className={clsx(
                            'flex items-center justify-center gap-2 min-h-[56px] px-3 rounded-xl border-2 text-sm font-medium transition',
                            active ? ACTIVE[variant] : 'border-[var(--color-border)] bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)] hover:bg-[var(--color-surface)]',
                        )}
                    >
                        {variant !== 'primary' && <StatusIcon variant={variant} size={18} />}
                        {o.label}
                    </button>
                );
            })}
        </div>
    );
}
