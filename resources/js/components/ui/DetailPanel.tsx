import { type ReactNode } from 'react';
import { clsx } from 'clsx';

interface DetailItemProps {
    label: string;
    value: ReactNode;
    icon?: ReactNode;
}

/**
 * A single label/value tile. Use inside <DetailPanel>. Generalized from the
 * per-page "InfoItem" pattern so every detail view renders fields identically.
 */
export function DetailItem({ label, value, icon }: DetailItemProps) {
    return (
        <div className="p-3 rounded-lg bg-[var(--color-surface-hover)]">
            <div className="flex items-center gap-1.5 text-[var(--color-text-muted)] mb-1">
                {icon}
                <p className="text-xs uppercase tracking-wide">{label}</p>
            </div>
            <div className="text-sm font-medium text-[var(--color-text)]">{value ?? '—'}</div>
        </div>
    );
}

interface DetailPanelProps {
    children: ReactNode;
    columns?: 1 | 2 | 3 | 4;
    className?: string;
}

const colMap = {
    1: 'grid-cols-1',
    2: 'grid-cols-1 sm:grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
    4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
};

/**
 * Responsive grid of <DetailItem> tiles — the standard read-only "summary" layout
 * for detail drawers and profile headers.
 */
export default function DetailPanel({ children, columns = 2, className }: DetailPanelProps) {
    return <div className={clsx('grid gap-3', colMap[columns], className)}>{children}</div>;
}
