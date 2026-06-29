import { useState, type ReactNode } from 'react';
import { clsx } from 'clsx';
import Card from '@/components/ui/Card';
import { Filter, X } from 'lucide-react';

interface FilterBarProps {
    /** The filter field controls (laid out by the consumer, e.g. a grid of selects). */
    children: ReactNode;
    /** Number of active filters — shows a count badge and enables Reset. */
    activeCount?: number;
    onReset?: () => void;
    /** Open by default; defaults to open when there are active filters. */
    defaultOpen?: boolean;
    /** Extra row below the fields (e.g. quick date presets). */
    footer?: ReactNode;
    className?: string;
}

/**
 * Collapsible filter shell — header toggle, active-count badge, reset. The fields
 * themselves are provided by the consumer so the shell stays generic and reusable
 * across modules (server-side filters that submit via router.get live in the
 * consumer). First consumer: Transport Tracking.
 */
export default function FilterBar({ children, activeCount = 0, onReset, defaultOpen, footer, className }: FilterBarProps) {
    const [open, setOpen] = useState(defaultOpen ?? activeCount > 0);

    return (
        <Card className={clsx('mb-4', className)}>
            <div className="flex items-center justify-between gap-2">
                <button
                    type="button"
                    onClick={() => setOpen((o) => !o)}
                    className="inline-flex items-center gap-2 text-sm font-medium text-[var(--color-text)]"
                >
                    <Filter size={16} className="text-[var(--color-primary)]" /> Filtres
                    {activeCount > 0 && (
                        <span className="ml-1 inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-[var(--color-primary)] text-white text-xs">
                            {activeCount}
                        </span>
                    )}
                </button>
                {activeCount > 0 && onReset && (
                    <button
                        type="button"
                        onClick={onReset}
                        className="text-xs text-[var(--color-danger)] hover:underline inline-flex items-center gap-1"
                    >
                        <X size={12} /> Réinitialiser
                    </button>
                )}
            </div>
            {open && (
                <div className="mt-4 space-y-4">
                    {children}
                    {footer}
                </div>
            )}
        </Card>
    );
}
