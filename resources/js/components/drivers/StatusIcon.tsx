import { CheckCircle2, AlertTriangle, XCircle } from 'lucide-react';
import { clsx } from 'clsx';

export type StatusVariant = 'success' | 'warning' | 'danger';

const MAP: Record<StatusVariant, { Icon: typeof CheckCircle2; cls: string }> = {
    success: { Icon: CheckCircle2, cls: 'text-emerald-600 dark:text-emerald-400' },
    warning: { Icon: AlertTriangle, cls: 'text-amber-600 dark:text-amber-400' },
    danger: { Icon: XCircle, cls: 'text-red-600 dark:text-red-400' },
};

/** ✓ / ⚠ / ✗ status icon — shared by the driver checklist and issue screens. */
export default function StatusIcon({ variant, size = 16, className }: { variant: StatusVariant; size?: number; className?: string }) {
    const { Icon, cls } = MAP[variant];
    return <Icon size={size} className={clsx(cls, className)} />;
}
