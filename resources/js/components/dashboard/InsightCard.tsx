import { Lightbulb, AlertTriangle, TrendingUp, CheckCircle } from 'lucide-react';
import { clsx } from 'clsx';
import type { Insight } from '@/types/models';

const iconMap = {
    info: Lightbulb,
    warning: AlertTriangle,
    success: CheckCircle,
    danger: TrendingUp,
};

const colorMap = {
    info: 'text-[var(--color-info)] bg-[var(--color-info)]/10',
    warning: 'text-amber-500 bg-amber-500/10',
    success: 'text-emerald-500 bg-emerald-500/10',
    danger: 'text-red-500 bg-red-500/10',
};

export default function InsightCard({ insights }: { insights: Insight[] }) {
    if (insights.length === 0) return null;

    return (
        <div className="bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-4 shadow-[var(--shadow-sm)]">
            <h3 className="text-sm font-semibold text-[var(--color-text)] mb-3 flex items-center gap-2">
                <Lightbulb size={16} className="text-[var(--color-primary)]" />
                Insights
            </h3>
            <div className="space-y-2.5">
                {insights.map((insight, i) => {
                    const Icon = iconMap[insight.type];
                    return (
                        <div key={i} className="flex items-start gap-3">
                            <div className={clsx('flex-shrink-0 w-7 h-7 rounded-lg flex items-center justify-center', colorMap[insight.type])}>
                                <Icon size={14} />
                            </div>
                            <p className="text-sm text-[var(--color-text-secondary)] leading-snug">
                                {insight.message}
                                {insight.metric && (
                                    <span className="ml-1 font-semibold text-[var(--color-text)]">{insight.metric}</span>
                                )}
                            </p>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
