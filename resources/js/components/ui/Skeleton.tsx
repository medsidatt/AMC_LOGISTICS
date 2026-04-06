import { clsx } from 'clsx';

interface SkeletonProps {
    className?: string;
    variant?: 'text' | 'card' | 'chart';
}

export default function Skeleton({ className, variant = 'text' }: SkeletonProps) {
    if (variant === 'card') {
        return (
            <div className={clsx('bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-5', className)}>
                <div className="flex items-center gap-3">
                    <div className="w-12 h-12 rounded-full bg-[var(--color-surface-hover)] animate-pulse" />
                    <div className="flex-1 space-y-2">
                        <div className="h-6 w-20 rounded bg-[var(--color-surface-hover)] animate-pulse" />
                        <div className="h-3 w-28 rounded bg-[var(--color-surface-hover)] animate-pulse" />
                    </div>
                </div>
            </div>
        );
    }

    if (variant === 'chart') {
        return (
            <div className={clsx('bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-5', className)}>
                <div className="h-5 w-32 rounded bg-[var(--color-surface-hover)] animate-pulse mb-4" />
                <div className="h-64 rounded-lg bg-[var(--color-surface-hover)] animate-pulse" />
            </div>
        );
    }

    return (
        <div className={clsx('h-4 rounded bg-[var(--color-surface-hover)] animate-pulse', className)} />
    );
}
