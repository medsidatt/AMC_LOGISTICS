import { type ReactNode } from 'react';
import { clsx } from 'clsx';

interface EmptyStateProps {
    title: string;
    description?: string;
    icon?: ReactNode;
    /** Optional call-to-action (typically a button). */
    action?: ReactNode;
    className?: string;
}

/**
 * Consistent empty state for sections/lists with no data — centered icon + message
 * + optional action. Replaces ad-hoc "Aucune donnée" centered paragraphs.
 */
export default function EmptyState({ title, description, icon, action, className }: EmptyStateProps) {
    return (
        <div className={clsx('flex flex-col items-center justify-center text-center py-10 px-4', className)}>
            {icon && <div className="text-[var(--color-text-muted)] mb-2">{icon}</div>}
            <p className="text-sm font-medium text-[var(--color-text)]">{title}</p>
            {description && <p className="text-xs text-[var(--color-text-muted)] mt-1 max-w-sm">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
