import { clsx } from 'clsx';
import { formatNumber } from '@/utils/formatters';

interface RatioCardProps {
    label: string;
    ratio: number;
    numerator?: number;
    denominator?: number;
    numeratorLabel?: string;
    denominatorLabel?: string;
    unit?: string;
    icon: React.ReactNode;
    color?: string;
    suffix?: string;
    invertColor?: boolean;
}

function ratioColor(ratio: number, invert: boolean): string {
    const v = invert ? 1 - Math.min(1, ratio) : Math.min(1, ratio);
    if (v >= 0.8) return 'var(--color-success)';
    if (v >= 0.5) return 'var(--color-warning)';
    return 'var(--color-danger)';
}

export default function RatioCard({
    label,
    ratio,
    numerator,
    denominator,
    numeratorLabel,
    denominatorLabel,
    unit,
    icon,
    color,
    suffix = '%',
    invertColor = false,
}: RatioCardProps) {
    const pct = Math.max(0, Math.min(1, Number.isFinite(ratio) ? ratio : 0));
    const display = Number.isFinite(ratio) ? ratio * 100 : 0;
    const barColor = color ?? ratioColor(ratio, invertColor);

    return (
        <div className="bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-5 shadow-[var(--shadow-sm)] hover:shadow-[var(--shadow-md)] transition-all duration-300 animate-slide-up">
            <div className="flex items-start justify-between mb-3">
                <p className="text-xs font-medium uppercase tracking-wider text-[var(--color-text-muted)]">
                    {label}
                </p>
                <div
                    className="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center"
                    style={{ background: `${barColor}15`, color: barColor }}
                >
                    {icon}
                </div>
            </div>

            <div className="flex items-baseline gap-1.5">
                <span className="text-2xl font-bold text-[var(--color-text)]">
                    {formatNumber(display, 1)}
                </span>
                <span className="text-sm font-medium text-[var(--color-text-secondary)]">{unit ?? suffix}</span>
            </div>

            <div className="mt-3 h-2 rounded-full bg-[var(--color-border)] overflow-hidden">
                <div
                    className={clsx('h-full rounded-full transition-all duration-500')}
                    style={{ width: `${pct * 100}%`, background: barColor }}
                />
            </div>

            {(numerator !== undefined || denominator !== undefined) && (
                <p className="text-xs text-[var(--color-text-muted)] mt-2">
                    {numerator !== undefined && (
                        <>
                            <span className="font-medium text-[var(--color-text)]">{formatNumber(numerator, 2)}</span>
                            {numeratorLabel && <span className="ml-0.5">{numeratorLabel}</span>}
                        </>
                    )}
                    {denominator !== undefined && (
                        <>
                            <span className="mx-1">/</span>
                            <span className="font-medium text-[var(--color-text)]">{formatNumber(denominator, 2)}</span>
                            {denominatorLabel && <span className="ml-0.5">{denominatorLabel}</span>}
                        </>
                    )}
                </p>
            )}
        </div>
    );
}
