import { useEffect, useRef, useState } from 'react';
import { TrendingUp, TrendingDown, Minus } from 'lucide-react';
import { clsx } from 'clsx';
import { formatNumber, formatPercent } from '@/utils/formatters';

interface KpiCardProps {
    label: string;
    value: number;
    unit?: string;
    change?: number;
    changeLabel?: string;
    icon: React.ReactNode;
    color?: string;
    decimals?: number;
}

function AnimatedCounter({ value, decimals = 0 }: { value: number; decimals?: number }) {
    const [display, setDisplay] = useState(0);
    const ref = useRef<number>(0);

    useEffect(() => {
        const start = ref.current;
        const diff = value - start;
        const duration = 600;
        const startTime = performance.now();

        const tick = (now: number) => {
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = start + diff * eased;
            setDisplay(current);
            if (progress < 1) requestAnimationFrame(tick);
            else ref.current = value;
        };

        requestAnimationFrame(tick);
    }, [value]);

    return <>{formatNumber(display, decimals)}</>;
}

export default function KpiCard({ label, value, unit, change, changeLabel, icon, color = 'var(--color-primary)', decimals = 0 }: KpiCardProps) {
    const TrendIcon = change === undefined || change === 0 ? Minus : change > 0 ? TrendingUp : TrendingDown;
    const trendColor = change === undefined || change === 0
        ? 'text-[var(--color-text-muted)]'
        : change > 0 ? 'text-emerald-500' : 'text-red-500';

    return (
        <div className="bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-5 shadow-[var(--shadow-sm)] hover:shadow-[var(--shadow-md)] transition-all duration-300 animate-slide-up">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <p className="text-xs font-medium uppercase tracking-wider text-[var(--color-text-muted)] mb-2">
                        {label}
                    </p>
                    <div className="flex items-baseline gap-1.5">
                        <span className="text-2xl font-bold text-[var(--color-text)]">
                            <AnimatedCounter value={value} decimals={decimals} />
                        </span>
                        {unit && (
                            <span className="text-sm font-medium text-[var(--color-text-secondary)]">{unit}</span>
                        )}
                    </div>
                    {change !== undefined && (
                        <div className={clsx('flex items-center gap-1 mt-2', trendColor)}>
                            <TrendIcon size={14} />
                            <span className="text-xs font-medium">{formatPercent(change)}</span>
                            {changeLabel && (
                                <span className="text-xs text-[var(--color-text-muted)]">{changeLabel}</span>
                            )}
                        </div>
                    )}
                </div>
                <div
                    className="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center"
                    style={{ background: `${color}15`, color }}
                >
                    {icon}
                </div>
            </div>
        </div>
    );
}
