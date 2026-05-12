import { Link } from '@inertiajs/react';
import { formatNumber } from '@/utils/formatters';

export interface TopRow {
    id: number;
    label: string;
    score: number;
    rotations: number;
    tonnage: number;
    [key: string]: number | string | null;
}

interface TopListProps {
    rows: TopRow[];
    hrefPrefix?: string;
    emptyLabel?: string;
    extraColumn?: { key: string; label: string; format?: 'number' | 'percent' | 'text' };
}

function scoreColor(score: number): string {
    if (score >= 75) return 'var(--color-success)';
    if (score >= 50) return 'var(--color-warning)';
    return 'var(--color-danger)';
}

export default function TopList({ rows, hrefPrefix, emptyLabel = 'Aucune donnée', extraColumn }: TopListProps) {
    if (!rows || rows.length === 0) {
        return (
            <p className="text-sm text-[var(--color-text-muted)] py-6 text-center">{emptyLabel}</p>
        );
    }

    return (
        <ol className="space-y-3">
            {rows.map((r, idx) => {
                const color = scoreColor(r.score);
                const inner = (
                    <div className="flex items-center gap-3">
                        <span
                            className="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                            style={{ background: `${color}15`, color }}
                        >
                            {idx + 1}
                        </span>
                        <div className="flex-1 min-w-0">
                            <div className="flex items-center justify-between mb-1">
                                <p className="text-sm font-semibold text-[var(--color-text)] truncate">{r.label}</p>
                                <span className="text-sm font-bold" style={{ color }}>
                                    {formatNumber(r.score, 1)}%
                                </span>
                            </div>
                            <div className="h-1.5 rounded-full bg-[var(--color-border)] overflow-hidden">
                                <div
                                    className="h-full rounded-full transition-all duration-500"
                                    style={{ width: `${Math.max(0, Math.min(100, r.score))}%`, background: color }}
                                />
                            </div>
                            <p className="text-xs text-[var(--color-text-muted)] mt-1">
                                {r.rotations} rot. · {formatNumber(r.tonnage, 1)} T
                                {extraColumn && r[extraColumn.key] !== undefined && r[extraColumn.key] !== null && (
                                    <>
                                        {' · '}
                                        <span>
                                            {extraColumn.format === 'percent'
                                                ? `${formatNumber(Number(r[extraColumn.key]), 1)}%`
                                                : extraColumn.format === 'number'
                                                    ? formatNumber(Number(r[extraColumn.key]), 2)
                                                    : String(r[extraColumn.key])}
                                            {' '}{extraColumn.label}
                                        </span>
                                    </>
                                )}
                            </p>
                        </div>
                    </div>
                );

                if (hrefPrefix) {
                    return (
                        <li key={r.id}>
                            <Link
                                href={`${hrefPrefix}/${r.id}`}
                                className="block p-2 -mx-2 rounded-lg hover:bg-[var(--color-surface-hover)] transition"
                            >
                                {inner}
                            </Link>
                        </li>
                    );
                }
                return <li key={r.id} className="p-2 -mx-2">{inner}</li>;
            })}
        </ol>
    );
}
